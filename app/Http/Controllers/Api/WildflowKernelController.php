<?php

namespace App\Http\Controllers\Api;

use App\Models\Currency;
use App\Models\LegalEntity;
use App\Models\MeanlyApiOrder;
use App\Models\MeanlyApiReservation;
use App\Models\Order\OrderItems;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\SellerTerminal;
use App\Services\FinanceService;
use App\Services\Provider\EzPinCatalogPuller;
use App\Services\Provider\ProviderCatalogAggregator;
use App\Services\VaultTransitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WildflowKernelController extends Controller
{
    public function __construct(
        private readonly ProviderCatalogAggregator $catalog,
        private readonly VaultTransitService $vault,
        private readonly FinanceService $finance
    ) {
    }

    public function unifiedCatalog(Request $request, string $provider): JsonResponse
    {
        $record = $this->resolveProvider($provider);

        if (! $record) {
            return response()->json([
                'success' => true,
                'provider' => $provider,
                'disabled' => true,
                'count' => 0,
                'catalog' => ['results' => []],
                'items' => [],
            ]);
        }

        $payload = $this->catalog->unifiedCatalog($record, $request->boolean('include_inactive'));
        if (! $request->boolean('include_raw')) {
            $payload['items'] = collect($payload['items'])
                ->map(function (array $item): array {
                    unset($item['raw_data']);

                    return $item;
                })
                ->all();
        }

        if ($provider !== $record->type) {
            $payload['provider']['requested_type'] = $provider;
        }

        return response()->json($payload);
    }

    public function exchangeRates(string $provider): JsonResponse
    {
        $rates = Currency::query()
            ->get()
            ->map(fn (Currency $currency): array => [
                'code' => $currency->code,
                'rate_to_rub' => (float) ($currency->manual_rate ?: $currency->rate_to_rub ?: 1),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'data' => $rates,
        ]);
    }

    public function checkAvailability(Request $request, string $provider, string $sku): JsonResponse
    {
        $quantity = max(1, (int) ($request->query('quantity') ?: $request->query('item_count') ?: 1));
        $product = $this->resolveProviderProduct($provider, $sku);
        $available = (bool) ($product?->is_active ?? false);
        $partner = $this->targetPartner($request, $request->query('terminal_id'));
        $balanceCheck = $partner && $product
            ? $this->partnerBalanceCheck(
                $partner,
                $this->orderCostUsd($product, (float) ($request->query('price') ?? 0), $quantity)
            )
            : null;

        if ($request->boolean('live') && ($provider === 'ezpin' || $provider === 'ezpin-sandbox')) {
            $vendor = $this->resolveVendorProvider($provider);
            if (! $vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'EZPin provider credentials are not configured.',
                ], 422);
            }

            try {
                $availability = app(EzPinCatalogPuller::class)->checkAvailability(
                    $vendor,
                    $sku,
                    $quantity,
                    $request->query('price') !== null ? (float) $request->query('price') : null,
                );

                return response()->json([
                    'success' => true,
                    'provider' => $provider,
                    'service_sku' => $sku,
                    'available' => (bool) (
                        data_get($availability, 'available')
                        ?? data_get($availability, 'availability')
                        ?? true
                    ),
                    'availability' => $availability,
                    'source' => 'ezpin-live',
                ]);
            } catch (\Throwable $error) {
                return response()->json([
                    'success' => false,
                    'message' => 'EZPin availability failed: '.$error->getMessage(),
                ], 502);
            }
        }

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'service_sku' => $sku,
            'available' => $available,
            'availability' => [
                'availability' => $available,
                'available' => $available,
                'requested' => $quantity,
                'affordable' => $balanceCheck['affordable'] ?? null,
                'required_usd' => $balanceCheck['required_usd'] ?? null,
                'available_usd' => $balanceCheck['available_usd'] ?? null,
                'detail' => $available ? 'Available from Meanly local kernel.' : 'Product is inactive or missing.',
            ],
            'delivery_type' => 0,
            'delivery_type_text' => 'Instant',
            'detail' => $available ? 'Available from Meanly local kernel.' : 'Product is inactive or missing.',
        ], $product || $available ? 200 : 404);
    }

    public function checkAvailabilityFromPayload(Request $request, string $provider = 'ezpin'): JsonResponse
    {
        $data = $request->validate([
            'sku' => 'required|string',
            'service_sku' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'item_count' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'live' => 'nullable|boolean',
            'terminal_id' => 'nullable|string|max:160',
        ]);

        $request->query->set('quantity', (string) ($data['quantity'] ?? $data['item_count'] ?? 1));
        if (array_key_exists('price', $data)) {
            $request->query->set('price', (string) $data['price']);
        }
        if (array_key_exists('live', $data)) {
            $request->query->set('live', $data['live'] ? '1' : '0');
        }
        if (array_key_exists('terminal_id', $data)) {
            $request->query->set('terminal_id', (string) $data['terminal_id']);
        }

        return $this->checkAvailability($request, $provider, (string) ($data['service_sku'] ?? $data['sku']));
    }

    public function topLevelOrder(Request $request): JsonResponse
    {
        return $this->placeOrder($request, 'ezpin');
    }

    public function placeOrder(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate([
            'service_sku' => 'nullable|string',
            'sku' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1|max:100',
            'price' => 'nullable|numeric|min:0',
            'referenceCode' => 'nullable|string|max:160',
            'reference_code' => 'nullable|string|max:160',
            'pre_order' => 'nullable|boolean',
            'destination' => 'nullable|string',
            'terminal_id' => 'nullable|string',
            'provider_terminal_id' => 'nullable|string',
            'provider_terminal_pin' => 'nullable|string',
            'seller_id' => 'nullable|string|max:128',
            'seller_name' => 'nullable|string|max:255',
        ]);

        $sku = (string) ($data['service_sku'] ?? $data['sku'] ?? '');
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'service_sku is required.'], 422);
        }

        $reference = (string) ($data['referenceCode'] ?? $data['reference_code'] ?? Str::uuid());
        $product = $this->resolveProviderProduct($provider, $sku);
        if ($product && ! $product->is_active) {
            return response()->json(['success' => false, 'message' => 'Product is inactive.'], 400);
        }

        $legalEntity = $this->targetPartner($request, $data['terminal_id'] ?? null);
        if (! $legalEntity || ! $legalEntity->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Active partner balance account is required for API orders.',
            ], 403);
        }

        $existing = MeanlyApiOrder::query()
            ->where('provider', $provider)
            ->where('marketplace_reference', $reference)
            ->when($legalEntity, fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => $existing->status !== 'failed',
                'idempotent' => true,
                'type' => 'meanly-local-kernel',
                'order' => $this->kernelOrderPayload($existing),
            ], $existing->status === 'failed' ? 500 : 200);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $cost = $this->orderCostUsd($product, (float) ($data['price'] ?? 0), $quantity);
        if ($cost['required_usd'] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Order price is required for partner balance settlement.',
            ], 422);
        }

        $balanceCheck = $this->partnerBalanceCheck($legalEntity, $cost);
        if (! $balanceCheck['affordable']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient partner balance for API order.',
                'required_usd' => $balanceCheck['required_usd'],
                'available_usd' => $balanceCheck['available_usd'],
                'balance_currency' => $legalEntity->currency ?? 'RUB',
            ], 402);
        }

        $kernelOrder = MeanlyApiOrder::create([
            'legal_entity_id' => $legalEntity?->id,
            'provider' => $provider,
            'marketplace_reference' => $reference,
            'proxy_reference' => (string) Str::uuid(),
            'service_sku' => $sku,
            'price' => $cost['source_amount'],
            'currency' => $cost['source_currency'],
            'status' => 'processing',
            'request_payload' => array_merge($data, ['settlement' => $balanceCheck]),
        ]);

        $reservation = null;
        try {
            $reservation = $this->reservePartnerApiBalance($kernelOrder, $legalEntity, $balanceCheck);
            $vendorResponse = $this->placeDirectVendorOrder($provider, $kernelOrder, $data);
            if ($vendorResponse === null) {
                throw new \RuntimeException('Provider order driver is not configured.');
            }

            $kernelOrder->update([
                'vendor_reference' => $this->vendorReferenceFromResponse($vendorResponse, $kernelOrder->proxy_reference),
                'response_payload' => $vendorResponse,
                'status' => 'accepted',
            ]);
            $this->settlePartnerApiBalance($kernelOrder, $legalEntity, $reservation);
        } catch (\Throwable $error) {
            if ($reservation) {
                $this->refundPartnerApiBalance($kernelOrder, $legalEntity, $reservation);
            }
            $kernelOrder->update([
                'status' => 'failed',
                'error_message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Provider order failed: '.$error->getMessage(),
                'order' => $this->kernelOrderPayload($kernelOrder->fresh()),
            ], str_contains($error->getMessage(), 'Insufficient partner balance') ? 402 : 500);
        }

        return response()->json([
            'success' => true,
            'type' => 'meanly-local-kernel',
            'order' => $this->kernelOrderPayload($kernelOrder->fresh()),
        ]);
    }

    public function normalizedCards(Request $request, string $provider, string $reference): JsonResponse
    {
        $kernelOrder = MeanlyApiOrder::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($reference): void {
                $query->where('marketplace_reference', $reference)
                    ->orWhere('proxy_reference', $reference)
                    ->orWhere('vendor_reference', $reference);
            })
            ->latest('id')
            ->first();

        if ($kernelOrder) {
            $cards = $this->directVendorCards($provider, $kernelOrder);
            if ($cards !== []) {
                return response()->json([
                    'success' => true,
                    'provider' => $provider,
                    'reference_code' => $reference,
                    'cards' => $cards,
                ]);
            }
        }

        $item = OrderItems::query()
            ->where('provider_order_id', $reference)
            ->orWhere('uuid', $reference)
            ->latest('id')
            ->first();

        if (! $item || ! filled($item->original_code)) {
            return response()->json([
                'success' => true,
                'provider' => $provider,
                'reference_code' => $reference,
                'cards' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'provider' => $provider,
            'reference_code' => $reference,
            'cards' => [[
                'pinCode' => $item->original_code,
                'pin_code' => $item->original_code,
                'code' => $item->original_code,
                'serial' => null,
                'expiry' => optional($item->activate_till)->toDateString(),
            ]],
        ]);
    }

    public function syncPartner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'terminal_id' => 'nullable|string|max:160',
            'name' => 'nullable|string|max:255',
            'balance' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'l1_address' => 'nullable|string|max:128',
            'provider_credentials' => 'nullable|array',
        ]);

        $requestEntity = $request->attributes->get('meanly_api_legal_entity')
            ?: $request->attributes->get('wildflow_legal_entity');
        $externalId = (string) ($data['terminal_id'] ?? $requestEntity?->id ?? '');
        $entity = $externalId !== '' ? $this->findLegalEntity($externalId) : $requestEntity;

        $apiToken = $request->header('X-Auth-Token') ?: $this->newSecret();
        $attributes = [
            'name' => $data['name'] ?? "Kernel Partner {$externalId}",
            'short_name' => $data['name'] ?? "Kernel Partner {$externalId}",
            'inn' => $this->syntheticInn($externalId ?: Str::uuid()->toString()),
            'is_active' => true,
            'available_balance' => (float) ($data['balance'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? 'RUB')),
            'vendor_credentials' => $data['provider_credentials'] ?? [],
            'wildflow_api_token' => $apiToken,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('legal_entities', 'meanly_api_token')) {
            $attributes['meanly_api_token'] = $apiToken;
        }

        $entity = LegalEntity::withoutEvents(function () use ($entity, $attributes, $externalId, $data): LegalEntity {
            if (! $entity) {
                $entity = LegalEntity::create($attributes);
            } else {
                $entity->fill($attributes)->save();
            }

            $meta = $entity->agreement_metadata ?? [];
            if ($externalId !== '') {
                $meta['kernel_external_id'] = $externalId;
            }
            if (filled($data['l1_address'] ?? null)) {
                $meta['l1_address'] = $data['l1_address'];
            }
            $entity->forceFill(['agreement_metadata' => $meta])->save();

            if (! filled($entity->meanlyFinancialSecret())) {
                $secret = $this->newSecret();
                $secretAttributes = [
                    'wildflow_financial_secret' => $entity->wildflow_financial_secret ?: $secret,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('legal_entities', 'meanly_financial_secret')) {
                    $secretAttributes['meanly_financial_secret'] = $secret;
                }

                $entity->forceFill($secretAttributes)->save();
            }

            return $entity;
        });

        return response()->json([
            'success' => true,
            'partner_id' => $entity->id,
            'external_id' => (string) ($externalId ?: $entity->id),
            'data' => $this->partnerPayload($entity),
        ]);
    }

    public function grantCredit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'required|string|max:160',
            'terminal_id' => 'nullable|string|max:160',
        ]);

        $entity = $this->targetPartner($request, $data['terminal_id'] ?? null);
        $reservation = MeanlyApiReservation::query()->updateOrCreate(
            [
                'legal_entity_id' => $entity?->id,
                'reference' => $data['reference'],
            ],
            [
                'amount' => (float) $data['amount'],
                'status' => 'active',
                'expires_at' => now()->addHours(2),
            ]
        );

        return response()->json([
            'success' => true,
            'reservation_id' => 'MEANLY-HOLD-'.$reservation->id,
            'idempotent' => ! $reservation->wasRecentlyCreated,
        ]);
    }

    public function topUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'terminal_id' => 'nullable|string|max:160',
            'reference' => 'nullable|string|max:160',
        ]);

        $entity = $this->targetPartner($request, $data['terminal_id'] ?? null);
        if (! $entity) {
            return response()->json(['success' => false, 'message' => 'Partner not found.'], 404);
        }

        $entity->increment('available_balance', (float) $data['amount']);

        return response()->json([
            'success' => true,
            'partner_id' => $entity->id,
            'balance' => (float) $entity->fresh()->available_balance,
            'reference' => $data['reference'] ?? null,
        ]);
    }

    public function showPartner(Request $request, string $externalId): JsonResponse
    {
        $entity = $this->findLegalEntity($externalId);
        if (! $entity) {
            return response()->json(['success' => false, 'message' => 'Partner not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->partnerPayload($entity),
        ]);
    }

    public function listPartners(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LegalEntity::query()
                ->where('is_active', true)
                ->get()
                ->map(fn (LegalEntity $entity): array => $this->partnerPayload($entity))
                ->values()
                ->all(),
        ]);
    }

    public function destroyPartner(Request $request, string $externalId): JsonResponse
    {
        $entity = $this->findLegalEntity($externalId);
        if (! $entity) {
            return response()->json(['success' => false, 'message' => 'Partner not found.'], 404);
        }

        $entity->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Partner disabled successfully',
        ]);
    }

    private function resolveProvider(string $provider): ?Provider
    {
        return Provider::query()
            ->where('type', $this->meanlyProviderType($provider))
            ->where('is_active', true)
            ->first();
    }

    private function resolveProviderProduct(string $provider, string $sku): ?ProviderProduct
    {
        $skuBidx = $this->vault->computeBlindIndex($sku);
        $providerType = $this->meanlyProviderType($provider);

        return ProviderProduct::query()
            ->with('provider')
            ->whereHas('provider', fn ($query) => $query->where('type', $providerType))
            ->where(function ($query) use ($skuBidx): void {
                $query->where('sku_bidx', $skuBidx)
                    ->orWhere('market_sku_bidx', $skuBidx);
            })
            ->first();
    }

    private function meanlyProviderType(string $provider): string
    {
        return match ($provider) {
            'ezpin' => 'wildflow',
            'ezpin-sandbox' => 'wildflow-sandbox',
            default => $provider,
        };
    }

    private function targetPartner(Request $request, ?string $externalId): ?LegalEntity
    {
        if (filled($externalId)) {
            return $this->findLegalEntity((string) $externalId);
        }

        return $request->attributes->get('meanly_api_legal_entity')
            ?: $request->attributes->get('wildflow_legal_entity');
    }

    private function findLegalEntity(string $externalId): ?LegalEntity
    {
        if (ctype_digit($externalId)) {
            $entity = LegalEntity::query()->find((int) $externalId);
            if ($entity) {
                return $entity;
            }
        }

        $terminal = SellerTerminal::query()->with('legalEntity')->where('terminal_id', $externalId)->first();
        if ($terminal?->legalEntity) {
            return $terminal->legalEntity;
        }

        return LegalEntity::query()->get()->first(function (LegalEntity $entity) use ($externalId): bool {
            return in_array($externalId, array_filter([
                data_get($entity->agreement_metadata, 'kernel_external_id'),
                data_get($entity->agreement_metadata, 'l1_address'),
            ]), true);
        });
    }

    private function partnerPayload(LegalEntity $entity): array
    {
        return [
            'id' => $entity->id,
            'external_id' => (string) (data_get($entity->agreement_metadata, 'kernel_external_id') ?? $entity->id),
            'name' => $entity->name,
            'balance' => (float) $entity->available_balance,
            'currency' => $entity->currency ?? 'RUB',
            'active' => (bool) $entity->is_active,
        ];
    }

    private function syntheticInn(string $externalId): string
    {
        return substr(preg_replace('/\D+/', '', hash('crc32b', $externalId)).'000000000000', 0, 12);
    }

    private function newSecret(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function kernelOrderPayload(MeanlyApiOrder $order): array
    {
        return [
            'reference_code' => $order->marketplace_reference,
            'proxy_reference' => $order->proxy_reference,
            'order_id' => $order->vendor_reference ?: $order->proxy_reference,
            'status' => $order->status === 'failed' ? 0 : 1,
            'status_text' => $order->status === 'failed' ? 'failed' : 'accept',
            'is_completed' => $order->status === 'completed',
            'provider' => $order->provider,
            'service_sku' => $order->service_sku,
        ];
    }

    private function orderCostUsd(?ProviderProduct $product, float $requestPrice, int $quantity): array
    {
        $sourceCurrency = strtoupper((string) ($product?->currency ?: 'USD'));
        $unitAmount = $requestPrice > 0
            ? $requestPrice
            : (float) ($product?->purchase_price ?: $product?->retail_price ?: 0);
        $sourceAmount = round($unitAmount * max(1, $quantity), 4);

        return [
            'source_amount' => $sourceAmount,
            'source_currency' => $sourceCurrency,
            'required_usd' => round($this->finance->convert($sourceAmount, $sourceCurrency, 'USD'), 4),
        ];
    }

    private function partnerBalanceCheck(LegalEntity $entity, array $cost): array
    {
        $walletCurrency = strtoupper((string) ($entity->currency ?: 'RUB'));
        $availableWallet = (float) $entity->available_balance;
        $requiredUsd = round((float) ($cost['required_usd'] ?? 0), 4);
        $availableUsd = round($this->finance->convert($availableWallet, $walletCurrency, 'USD'), 4);
        $requiredWallet = round($this->finance->convert($requiredUsd, 'USD', $walletCurrency), 4);

        return [
            'affordable' => $requiredUsd > 0 && $availableUsd + 0.0001 >= $requiredUsd,
            'required_usd' => $requiredUsd,
            'available_usd' => $availableUsd,
            'required_wallet' => $requiredWallet,
            'available_wallet' => round($availableWallet, 4),
            'wallet_currency' => $walletCurrency,
            'source_amount' => round((float) ($cost['source_amount'] ?? 0), 4),
            'source_currency' => (string) ($cost['source_currency'] ?? 'USD'),
        ];
    }

    private function reservePartnerApiBalance(MeanlyApiOrder $order, LegalEntity $entity, array $balanceCheck): MeanlyApiReservation
    {
        return DB::transaction(function () use ($order, $entity, $balanceCheck): MeanlyApiReservation {
            $locked = LegalEntity::query()->lockForUpdate()->findOrFail($entity->id);
            $requiredWallet = (float) $balanceCheck['required_wallet'];

            if ((float) $locked->available_balance + 0.0001 < $requiredWallet) {
                throw new \RuntimeException('Insufficient partner balance for API order.');
            }

            $locked->decrement('available_balance', $requiredWallet);
            $locked->increment('reserved_balance', $requiredWallet);

            $reservation = MeanlyApiReservation::create([
                'legal_entity_id' => $locked->id,
                'amount' => $requiredWallet,
                'reference' => 'api-order:'.$order->id,
                'status' => 'active',
                'expires_at' => now()->addMinutes(30),
            ]);

            app(\App\Services\LedgerService::class)->recordGlobal('WILDFLOW_API_ORDER_BALANCE_RESERVED', $locked->fresh(), [
                'kernel_order_id' => $order->id,
                'reservation_id' => $reservation->id,
                'required_usd' => $balanceCheck['required_usd'],
                'amount' => $requiredWallet,
                'currency' => $balanceCheck['wallet_currency'],
            ]);

            return $reservation;
        });
    }

    private function settlePartnerApiBalance(MeanlyApiOrder $order, LegalEntity $entity, MeanlyApiReservation $reservation): void
    {
        DB::transaction(function () use ($order, $entity, $reservation): void {
            $locked = LegalEntity::query()->lockForUpdate()->findOrFail($entity->id);
            $amount = (float) $reservation->amount;

            $locked->decrement('reserved_balance', $amount);
            $reservation->forceFill(['status' => 'settled'])->save();

            app(\App\Services\LedgerService::class)->recordGlobal('WILDFLOW_API_ORDER_BALANCE_SETTLED', $locked->fresh(), [
                'kernel_order_id' => $order->id,
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'currency' => $locked->currency ?? 'RUB',
            ]);
        });
    }

    private function refundPartnerApiBalance(MeanlyApiOrder $order, LegalEntity $entity, MeanlyApiReservation $reservation): void
    {
        DB::transaction(function () use ($order, $entity, $reservation): void {
            $locked = LegalEntity::query()->lockForUpdate()->findOrFail($entity->id);
            $amount = (float) $reservation->amount;

            $locked->decrement('reserved_balance', $amount);
            $locked->increment('available_balance', $amount);
            $reservation->forceFill(['status' => 'refunded'])->save();

            app(\App\Services\LedgerService::class)->recordGlobal('WILDFLOW_API_ORDER_BALANCE_REFUNDED', $locked->fresh(), [
                'kernel_order_id' => $order->id,
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'currency' => $locked->currency ?? 'RUB',
            ]);
        });
    }

    private function placeDirectVendorOrder(string $provider, MeanlyApiOrder $order, array $payload): ?array
    {
        $vendor = $this->resolveVendorProvider($provider);
        if (! $vendor) {
            return null;
        }

        if ($provider === 'ezpin' || $provider === 'ezpin-sandbox') {
            return $this->placeEzpinOrder($vendor, $order, $payload);
        }

        if ($provider === 'fazer') {
            return $this->placeFazerOrder($vendor, $order, $payload);
        }

        return null;
    }

    private function placeEzpinOrder(Provider $provider, MeanlyApiOrder $order, array $payload): array
    {
        $puller = app(EzPinCatalogPuller::class);
        $credentials = $puller->credentialsFor($provider);

        $request = new \EzPin\DTO\OrderRequest(
            sku: (int) $order->service_sku,
            quantity: (int) ($payload['quantity'] ?? 1),
            price: (float) ($payload['price'] ?? $order->price),
            referenceCode: $order->proxy_reference,
            terminal_pin: (string) ($payload['provider_terminal_pin'] ?? $credentials['terminal_pin'] ?? ''),
            terminal_id: (int) ($payload['provider_terminal_id'] ?? $credentials['terminal_id'] ?? 0),
            destination: (string) ($payload['destination'] ?? ''),
            preOrder: (bool) ($payload['pre_order'] ?? false),
            deliveryType: (int) ($payload['delivery_type'] ?? 0),
        );

        return $puller->clientFor($provider)->createOrder($request);
    }

    private function placeFazerOrder(Provider $provider, MeanlyApiOrder $order, array $payload): array
    {
        if (! class_exists(\FazerSdk\FazerClient::class)) {
            throw new \RuntimeException('Fazer SDK is not available.');
        }

        $apiKey = (string) data_get($provider->credentials, 'api_key', config('services.fazer.api_key'));
        if ($apiKey === '') {
            throw new \RuntimeException('Fazer credentials are not configured.');
        }

        return (new \FazerSdk\FazerClient($apiKey))->orders()->createGiftCardOrder(
            $order->service_sku,
            (int) ($payload['quantity'] ?? 1)
        );
    }

    private function directVendorCards(string $provider, MeanlyApiOrder $order): array
    {
        $vendor = $this->resolveVendorProvider($provider);
        $reference = $order->vendor_reference ?: $order->proxy_reference;
        if (! $vendor || $reference === '') {
            return [];
        }

        try {
            if (($provider === 'ezpin' || $provider === 'ezpin-sandbox') && class_exists(\EzPin\EzPinClient::class)) {
                $rawCards = app(EzPinCatalogPuller::class)->getCards($vendor, $reference);
                $cards = $rawCards['results'] ?? $rawCards;

                return collect($cards)
                    ->map(fn (array $card): array => [
                        'pinCode' => $card['pin_code'] ?? $card['pinCode'] ?? $card['card_number'] ?? $card['code'] ?? null,
                        'pin_code' => $card['pin_code'] ?? $card['pinCode'] ?? $card['card_number'] ?? $card['code'] ?? null,
                        'serial' => $card['serial'] ?? $card['serial_number'] ?? null,
                        'expiry' => $card['expiry_date'] ?? $card['expire_date'] ?? null,
                        'raw_data' => $card,
                    ])
                    ->filter(fn (array $card): bool => filled($card['pinCode']))
                    ->values()
                    ->all();
            }

            if ($provider === 'fazer' && class_exists(\FazerSdk\FazerClient::class)) {
                $raw = (new \FazerSdk\FazerClient((string) data_get($vendor->credentials, 'api_key')))->orders()->getStatus($reference);
                $code = $raw['code'] ?? $raw['pin'] ?? $raw['content'] ?? null;

                return $code ? [[
                    'pinCode' => (string) $code,
                    'pin_code' => (string) $code,
                    'serial' => $raw['serial'] ?? null,
                    'raw_data' => $raw,
                ]] : [];
            }
        } catch (\Throwable) {
            return [];
        }

        return [];
    }

    private function resolveVendorProvider(string $provider): ?Provider
    {
        $upstream = Provider::query()
            ->where('type', $provider)
            ->where('is_active', true)
            ->first();

        if ($upstream) {
            return $upstream;
        }

        return Provider::query()
            ->where('type', $this->meanlyProviderType($provider))
            ->where('is_active', true)
            ->first();
    }

    private function vendorReferenceFromResponse(array $response, string $fallback): string
    {
        return (string) (
            data_get($response, 'referenceCode')
            ?? data_get($response, 'reference_code')
            ?? data_get($response, 'order.referenceCode')
            ?? data_get($response, 'order.reference_code')
            ?? data_get($response, 'order_id')
            ?? data_get($response, 'id')
            ?? $fallback
        );
    }
}
