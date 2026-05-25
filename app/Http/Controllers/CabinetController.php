<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\ProductInventory;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\BuyerWalletService;
use App\Services\OrderSupportTicketService;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;

class CabinetController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $vaultUnlocked = $this->vaultUnlocked($request);

        $orders = collect();
        $safeOrders = collect();

        if ($vaultUnlocked) {
            $orders = $this->buyerOrders($user);
            $itemIds = $orders
                ->flatMap(fn (Order $order) => $order->items->pluck('id'))
                ->filter()
                ->values();

            $inventoryCountsByItem = $itemIds->isEmpty()
                ? collect()
                : ProductInventory::query()
                    ->selectRaw('order_item_id, count(*) as codes_count')
                    ->whereIn('order_item_id', $itemIds)
                    ->where('is_used', true)
                    ->where('status', 'sold')
                    ->groupBy('order_item_id')
                    ->pluck('codes_count', 'order_item_id');

            $safeOrders = $orders
                ->map(fn (Order $order) => $this->safeCard($order, $inventoryCountsByItem))
                ->filter(fn (array $safe) => $safe['paid'] || $safe['codes_count'] > 0)
                ->values();
        }

        $totalOrders = $vaultUnlocked ? $orders->count() : null;
        $activeKeysCount = $vaultUnlocked
            ? $safeOrders->where('ready', true)->sum('codes_count')
            : null;

        $walletBalances = [];
        $transactions = collect();

        if ($vaultUnlocked) {
            $wallets = app(BuyerWalletService::class);
            $walletAccounts = WalletAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('asset', [BuyerWalletService::ASSET_RUBT, BuyerWalletService::ASSET_SL1])
                ->get()
                ->keyBy('asset');

            $walletBalances = [
                BuyerWalletService::ASSET_RUBT => $this->walletBalance($wallets, $user, BuyerWalletService::ASSET_RUBT, $walletAccounts),
                BuyerWalletService::ASSET_SL1 => $this->walletBalance($wallets, $user, BuyerWalletService::ASSET_SL1, $walletAccounts),
            ];

            $transactions = WalletLedgerEntry::query()
                ->where('user_id', $user->id)
                ->latest()
                ->take(12)
                ->get();
        }

        return view('cabinet', compact(
            'user',
            'totalOrders',
            'activeKeysCount',
            'safeOrders',
            'transactions',
            'walletBalances',
            'vaultUnlocked',
        ));
    }

    public function vaultPasskeyOptions(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $allowCredentials = $user->passkeys()
            ->oldest('id')
            ->get()
            ->map(function (Passkey $passkey) {
                $credentialId = base64_decode((string) $passkey->credential_id, true);

                if ($credentialId === false || $credentialId === '') {
                    return null;
                }

                return new PublicKeyCredentialDescriptor(
                    type: 'public-key',
                    id: $credentialId,
                    transports: [],
                );
            })
            ->filter()
            ->values()
            ->all();

        if (count($allowCredentials) === 0) {
            throw ValidationException::withMessages([
                'passkey' => 'Для открытия сейфа сначала добавьте Passkey в профиль.',
            ]);
        }

        $options = new PublicKeyCredentialRequestOptions(
            challenge: random_bytes(32),
            rpId: $request->getHost(),
            allowCredentials: $allowCredentials,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $json = Serializer::make()->toJson($options);
        $unlockId = (string) Str::uuid();

        session([
            'cabinet_vault_unlock.'.$unlockId => [
                'json' => $json,
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ],
        ]);

        return response()->json(json_decode($json, true) + [
            'unlock_id' => $unlockId,
        ]);
    }

    public function vaultPasskeyConfirm(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'unlock_id' => 'required|string',
            'assertion' => 'required|array',
        ]);

        $sessionKey = 'cabinet_vault_unlock.'.$data['unlock_id'];
        $pending = session($sessionKey);

        if (! is_array($pending)
            || (int) ($pending['user_id'] ?? 0) !== (int) $user->id
            || (int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'unlock_id' => 'Контекст открытия сейфа устарел. Повторите Passkey-проверку.',
            ]);
        }

        try {
            $passkey = app(FindPasskeyToAuthenticateAction::class)->execute(
                json_encode($data['assertion']),
                (string) $pending['json'],
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'assertion' => 'Passkey-проверка не удалась: '.$e->getMessage(),
            ]);
        }

        if (! $passkey || (int) $passkey->authenticatable_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'assertion' => 'Этот Passkey не принадлежит текущему клиенту.',
            ]);
        }

        session()->forget($sessionKey);
        session(['cabinet_vault_unlocked_until' => now()->addMinutes(15)->timestamp]);

        return response()->json(['success' => true]);
    }

    /**
     * @return Collection<int, Order>
     */
    private function buyerOrders(User $user): Collection
    {
        $walletOrderIds = WalletLedgerEntry::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (WalletLedgerEntry $entry) => data_get($entry->payload, 'checkout_result.order_id'))
            ->filter()
            ->unique()
            ->values();

        $linkedOrders = Order::query()
            ->with(['items.game', 'shop'])
            ->where(function ($query) use ($user, $walletOrderIds) {
                $query->where('user_id', $user->id);

                if ($walletOrderIds->isNotEmpty()) {
                    $query->orWhereIn('id', $walletOrderIds);
                }
            })
            ->latest()
            ->get();

        // client_info is encrypted in newer installs, so this small fallback only scans
        // recent storefront orders and then lets the model cast decrypt for owner checks.
        $clientInfoFallback = Order::query()
            ->with(['items.game', 'shop'])
            ->where('sales_channel', 'meanly_storefront')
            ->latest()
            ->take(100)
            ->get()
            ->filter(fn (Order $order) => (int) data_get($order->client_info, 'buyer_user_id') === (int) $user->id);

        return $linkedOrders
            ->merge($clientInfoFallback)
            ->unique('id')
            ->sortByDesc('created_at')
            ->values();
    }

    private function vaultUnlocked(Request $request): bool
    {
        return (int) $request->session()->get('cabinet_vault_unlocked_until', 0) >= now()->timestamp;
    }

    /**
     * @param Collection<int|string, int|string> $inventoryCountsByItem
     * @return array<string, mixed>
     */
    private function safeCard(Order $order, Collection $inventoryCountsByItem): array
    {
        $paymentStatus = (string) data_get($order->info, 'payment_status', '');
        $paid = $paymentStatus === 'captured'
            || in_array((string) $order->status, ['COMPLETED', 'PROCESSING'], true)
            || (bool) data_get($order->info, 'wallet_ledger_entry_id');

        $hasFailure = in_array((string) $order->status, ['FAILED', 'CANCELLED'], true)
            || $order->items->contains(fn ($item) => (string) $item->purchase_status === 'failed')
            || (string) data_get($order->info, 'order_safe.status') === 'provider_redeem_failed';

        $inventoryCodesCount = $order->items->sum(
            fn ($item) => (int) $inventoryCountsByItem->get($item->id, 0)
        );
        $itemCodesCount = $order->items->filter(fn ($item) => filled($item->original_code))->count();
        $codesCount = max($inventoryCodesCount, $itemCodesCount);

        $ready = $paid && $codesCount > 0 && ! $hasFailure;
        $safeSource = (string) data_get($order->info, 'order_safe.source', 'local');
        $status = match (true) {
            $hasFailure && $safeSource === 'provider' => 'provider_redeem_failed',
            $hasFailure => 'failed',
            $ready && $safeSource === 'provider' => 'provider_code_ready',
            $ready => 'local_code_ready',
            $paid && $safeSource === 'provider' => 'provider_redeem_pending',
            $paid => 'preparing',
            default => 'pending',
        };
        $label = match ($status) {
            'local_code_ready', 'provider_code_ready' => 'Сейф готов',
            'preparing', 'provider_redeem_pending' => 'Готовим код',
            'failed', 'provider_redeem_failed' => 'Нужна проверка',
            default => 'Ожидаем оплату',
        };
        $message = match ($status) {
            'local_code_ready', 'provider_code_ready' => 'Код закреплен за заказом и появится после открытия сейфа.',
            'provider_redeem_pending' => 'Платеж подтвержден. Запрашиваем код у поставщика.',
            'preparing' => 'Платеж подтвержден. Сейф ожидает завершения выдачи кода.',
            'failed', 'provider_redeem_failed' => 'Выдача кода требует проверки. Тикет поддержки открыт: площадка проверит заказ или оформит возврат.',
            default => 'Проверяем подтверждение оплаты и готовим сейф заказа.',
        };

        $firstItem = $order->items->first();
        $productName = $firstItem?->game?->name ?: ($firstItem?->sku ?: 'Цифровой ваучер');
        $quantity = max(1, (int) $order->items->sum(fn ($item) => (int) ($item->count ?? 1)));

        $scratchProof = data_get($order->info, 'order_safe.scratch_proof');
        $scratched = !empty($scratchProof);
        $supportTicket = null;
        $supportTicketUrl = null;
        $supportTicketMessagesUrl = null;
        $supportTicketReplyUrl = null;

        if ($hasFailure && $paid) {
            $supportTicket = app(OrderSupportTicketService::class)->ticketForProblemSafe($order);
            if ($supportTicket) {
                $supportTicketUrl = URL::signedRoute('meanly.storefront.orders.safe.support-ticket', ['order' => $order->uuid]);
                $supportTicketMessagesUrl = URL::signedRoute('meanly.storefront.orders.safe.support-ticket.messages', ['order' => $order->uuid]);
                $supportTicketReplyUrl = URL::signedRoute('meanly.storefront.orders.safe.support-ticket.reply', ['order' => $order->uuid]);
            }
        }

        return [
            'order_id' => $order->order_id,
            'uuid' => $order->uuid,
            'anchor' => 'safe-'.$order->uuid,
            'product_name' => $productName,
            'quantity' => $quantity,
            'total_amount' => (float) $order->total_amount,
            'currency' => $order->currency ?: 'RUB',
            'created_at' => $order->created_at,
            'status' => $status,
            'label' => $label,
            'message' => $message,
            'paid' => $paid,
            'ready' => $ready,
            'codes_count' => max(1, $codesCount),
            'cabinet_safe_url' => $this->cabinetSafeUrl($order),
            'safe_url' => route('meanly.storefront.orders.safe.show', ['order' => $order->uuid]),
            'safe_status_url' => route('meanly.storefront.orders.safe.status', ['order' => $order->uuid]),
            'safe_open_url' => route('meanly.storefront.orders.safe.open', ['order' => $order->uuid]),
            'support_ticket_id' => $supportTicket?->id,
            'support_ticket_url' => $supportTicketUrl,
            'support_ticket_messages_url' => $supportTicketMessagesUrl,
            'support_ticket_reply_url' => $supportTicketReplyUrl,
            'scratched' => $scratched,
            'scratch_proof' => $scratchProof,
        ];
    }

    private function cabinetSafeUrl(Order $order): string
    {
        $anchor = 'safe-'.$order->uuid;

        return route('filament.client.pages.dashboard', ['safe' => $order->uuid]).'#'.$anchor;
    }

    /**
     * @param Collection<string, WalletAccount> $walletAccounts
     * @return array<string, mixed>
     */
    private function walletBalance(BuyerWalletService $wallets, User $user, string $asset, Collection $walletAccounts): array
    {
        $balance = $wallets->balance($user, $asset);
        $scale = $asset === BuyerWalletService::ASSET_SL1 ? 4 : 2;

        return [
            'available_minor' => $balance['available_minor'],
            'reserved_minor' => $balance['reserved_minor'],
            'total_minor' => $balance['total_minor'],
            'available' => $wallets->minorToDecimalString($balance['available_minor'], $scale),
            'reserved' => $wallets->minorToDecimalString($balance['reserved_minor'], $scale),
            'total' => $wallets->minorToDecimalString($balance['total_minor'], $scale),
            'l1_address' => $walletAccounts->get($asset)?->l1_address,
        ];
    }
}
