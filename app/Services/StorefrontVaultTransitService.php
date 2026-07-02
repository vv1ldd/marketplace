<?php

namespace App\Services;

use App\Models\Architecture\ExecutionRecord;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\ProductInventory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorefrontVaultTransitService
{
    public function __construct(
        private readonly StorefrontDecisionService $decisions,
        private readonly IntentLedgerService $intentLedger,
        private readonly MeanlyAnalyticsService $analytics,
        private readonly MarketplaceIdentityResolver $identityResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $identity
     * @return array{secret: string, entitlement_id: string, first_reveal: bool}
     */
    public function revealEntitlement(string $entitlementId, array $identity, Request $request): array
    {
        $buyerAddress = strtolower((string) data_get($identity, 'entity_l1_address'));
        abort_if($buyerAddress === '', 403);

        $orderItemId = $this->parseEntitlementId($entitlementId);
        $item = OrderItems::query()
            ->with(['order.items'])
            ->findOrFail($orderItemId);
        $order = $item->order;
        abort_if(! $order instanceof Order, 404);

        $this->authorizeBuyer($order, $buyerAddress);

        $decision = $this->decisions->orderSafeDecision($order);
        if (! ($decision['paid'] ?? false)) {
            throw ValidationException::withMessages([
                'entitlement' => 'This entitlement is not paid yet.',
            ]);
        }

        if (! $this->isInventoryEligible($order, $item, $decision)) {
            throw ValidationException::withMessages([
                'entitlement' => 'This entitlement is not ready for reveal.',
            ]);
        }

        $secret = $this->resolveItemSecret($order, $item);
        if ($secret === '') {
            throw ValidationException::withMessages([
                'entitlement' => 'No secret is available for this entitlement yet.',
            ]);
        }

        $user = $this->identityResolver->resolveFromIdentity($identity);
        $firstReveal = $this->recordRevealAudit($order, $item, $entitlementId, $request, $user instanceof User ? $user : null);

        return [
            'secret' => $secret,
            'entitlement_id' => $entitlementId,
            'first_reveal' => $firstReveal,
        ];
    }

    private function parseEntitlementId(string $entitlementId): int
    {
        if (! preg_match('/^ent_(\d+)$/', $entitlementId, $matches)) {
            abort(404, 'Entitlement not found.');
        }

        return (int) $matches[1];
    }

    private function authorizeBuyer(Order $order, string $buyerAddress): void
    {
        $orderAddress = strtolower((string) (
            data_get($order->client_info, 'buyer_l1_address')
            ?: data_get($order->client_info, 'simple_l1.entity_l1_address')
            ?: data_get($order->info, 'simple_l1.entity_l1_address')
        ));

        abort_if($orderAddress === '' || ! hash_equals($orderAddress, $buyerAddress), 403);
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function isInventoryEligible(Order $order, OrderItems $item, array $decision): bool
    {
        if ($decision['ready'] ?? false) {
            return true;
        }

        $executionId = data_get($order->info, 'order_safe.execution_record_id');
        if (filled($executionId)) {
            $state = ExecutionRecord::query()->whereKey($executionId)->value('state');

            return $state === ExecutionRecord::STATE_ISSUED;
        }

        return filled($item->original_code);
    }

    private function resolveItemSecret(Order $order, OrderItems $item): string
    {
        $safeSource = (string) data_get($order->info, 'order_safe.source', 'local');

        if ($safeSource === 'provider') {
            $providerCode = collect((array) data_get($item->client_info, 'provider_redemption.codes', []))
                ->map(fn ($code): string => trim((string) $code))
                ->first(fn (string $code): bool => $code !== '');

            if (is_string($providerCode) && $providerCode !== '') {
                return $providerCode;
            }
        }

        $inventory = ProductInventory::query()
            ->where('order_item_id', $item->id)
            ->where('is_used', true)
            ->where('status', 'sold')
            ->first();

        if ($inventory && filled($inventory->voucher)) {
            return trim((string) $inventory->voucher);
        }

        return trim((string) $item->original_code);
    }

    private function recordRevealAudit(
        Order $order,
        OrderItems $item,
        string $entitlementId,
        Request $request,
        ?User $user,
    ): bool {
        $info = $order->info ?? [];
        $wasAlreadyOpened = filled(data_get($info, 'order_safe.opened_at'))
            || filled(data_get($info, 'order_safe.scratch_proof'));
        $revealedAt = now()->toJSON();
        $scratchProof = (string) (data_get($info, 'order_safe.scratch_proof')
            ?: ('vault-dashboard:'.Str::slug($entitlementId).':'.hash('sha256', $revealedAt.$item->id)));

        data_set($info, 'order_safe.opened_at', data_get($info, 'order_safe.opened_at') ?: $revealedAt);
        data_set($info, 'order_safe.last_opened_at', $revealedAt);
        data_set($info, 'order_safe.open_count', (int) data_get($info, 'order_safe.open_count', 0) + 1);
        data_set($info, 'order_safe.scratched_at', data_get($info, 'order_safe.scratched_at') ?: $revealedAt);
        data_set($info, 'order_safe.scratch_proof', $scratchProof);
        data_set($info, 'order_safe.delivery_status', 'final_delivered');
        $order->forceFill(['info' => $info, 'status' => 'COMPLETED'])->save();

        $itemInfo = $item->client_info ?? [];
        data_set($itemInfo, 'delivery_status', 'final_delivered');
        $item->forceFill([
            'purchase_status' => 'completed',
            'client_info' => $itemInfo,
        ])->save();

        $firstReveal = ! $wasAlreadyOpened;

        if ($firstReveal) {
            $this->intentLedger->recordForOrder(
                order: $order,
                eventType: 'VAULT_ENTITLEMENT_REVEAL_INTENT',
                intentType: 'vault.entitlement.reveal',
                payload: [
                    'entitlement_id' => $entitlementId,
                    'order_item_id' => $item->id,
                    'reveal_surface' => 'vault_dashboard_scratch_card',
                    'revealed_at' => $revealedAt,
                    'scratch_proof_hash' => hash('sha256', $scratchProof),
                ],
                request: $request,
                user: $user,
                scope: 'storefront:vault',
                resource: 'vault_entitlement:'.$entitlementId,
            );
        }

        $this->analytics->track('vault.entitlement.revealed', [
            'entitlement_id' => $entitlementId,
            'order_item_id' => $item->id,
            'first_reveal' => $firstReveal,
            'reveal_surface' => 'vault_dashboard_scratch_card',
        ], [
            'event_type' => 'fulfillment',
            'surface' => 'vault_dashboard',
            'order_id' => $order->id,
            'shop_id' => $order->shop_id,
            'currency' => $order->currency,
            'mirror_to_ledger' => false,
        ]);

        return $firstReveal;
    }
}
