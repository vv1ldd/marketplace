<?php

namespace App\Services\Provider;

use App\Models\Provider;
use App\Services\Dgs\DgsFulfillmentPayloadBuilder;
use App\Services\Dgs\DgsFulfillmentService;
use App\Services\Dgs\DgsNodeFulfillmentAdapter;
use App\Services\DgsShadowIngestService;
use App\Services\WildflowService;
use Illuminate\Support\Facades\Log;

class WildflowDriver implements ProviderDriverInterface
{
    protected ?Provider $provider = null;
    protected ?WildflowService $service = null;
    protected ?array $lastOrderResponse = null;
    protected ?array $lastSourceLedgerReceipt = null;

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $normalizedCardsByReference = [];

    public function setProvider(Provider $provider): self
    {
        $this->provider = $provider;
        $this->service = new WildflowService(providerModel: $provider);
        return $this;
    }

    protected function getService(): WildflowService
    {
        if (!$this->service) {
            $this->service = new WildflowService(providerModel: $this->provider);
        }
        return $this->service;
    }

    public function createOrder(string $sku, string $reference, float $price, int $quantity, array $meta = []): string
    {
        $totalAmount = $price * $quantity;
        $upstreamProvider = $this->upstreamProvider();
        $terminalId = (string)($meta['terminal_id'] ?? '');

        // 💳 JIT CREDIT GRANTING (With Multi-Tenant Auto-Registration)
        try {
            $sellerName = (string)($meta['seller_name'] ?? '');

            Log::info("Meanly supply authority: attempting JIT credit grant for {$reference}", [
                'amount' => $totalAmount,
                'upstream_provider' => $upstreamProvider,
                'terminal_id' => $terminalId,
                'seller_name' => $sellerName
            ]);

            $this->getService()->grantCredit($totalAmount, $reference, $terminalId);
            $this->lastSourceLedgerReceipt = $this->getService()->lastSourceLedgerReceipt();
        } catch (\Throwable $e) {
            Log::error("Meanly supply authority JIT credit failed: " . $e->getMessage());
            throw $e;
        }

        if ($this->shouldRouteFulfillmentToNode()) {
            $payloadBuilder = app(DgsFulfillmentPayloadBuilder::class);
            $adapter = app(DgsNodeFulfillmentAdapter::class);
            $fulfillmentMeta = array_merge($meta, [
                'ezpin_sku' => $meta['ezpin_sku'] ?? $sku,
            ]);

            $nodeResponse = app(DgsFulfillmentService::class)->issue(
                $payloadBuilder->build($sku, $reference, $price, $quantity, $fulfillmentMeta)
            );

            $this->lastOrderResponse = $adapter->normalizeOrderResponse($nodeResponse, $reference);
            $this->normalizedCardsByReference[$reference] = $adapter->normalizedCards($nodeResponse);

            Log::info('Meanly supply authority: Node DGS fulfillment completed', [
                'reference' => $reference,
                'node_status' => $nodeResponse['status'] ?? null,
                'fulfillment_mode' => config('services.dgs.fulfillment_mode'),
            ]);

            return $reference;
        }

        // Meanly provider authority returns the normalized order object. Keep the
        // marketplace reference as the idempotent handle for later card polling.
        $order = $this->getService()->createOrder(
            service_sku: $sku,
            order_item_id: $reference,
            price: $price,
            quantity: $quantity,
            pre_order: $meta['pre_order'] ?? false,
            destination: $meta['email'] ?? '',
            provider: $upstreamProvider,
            terminalId: $terminalId,
            sellerId: $meta['seller_id'] ?? null,
            sellerName: $meta['seller_name'] ?? null
        );
        $this->lastOrderResponse = is_array($order) ? $order : null;
        $this->lastSourceLedgerReceipt = data_get($this->lastOrderResponse, 'source_ledger_receipt')
            ?: $this->getService()->lastSourceLedgerReceipt();

        return $reference;
    }

    public function lastOrderResponse(): ?array
    {
        return $this->lastOrderResponse;
    }

    public function lastSourceLedgerReceipt(): ?array
    {
        return $this->lastSourceLedgerReceipt;
    }

    public function getCodes(string $externalOrderId): array
    {
        return collect($this->getNormalizedCards($externalOrderId))
            ->map(fn (array $card) => $card['pinCode'] ?? $card['pin_code'] ?? $card['code'] ?? null)
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNormalizedCards(string $externalOrderId): array
    {
        if (isset($this->normalizedCardsByReference[$externalOrderId])) {
            return $this->normalizedCardsByReference[$externalOrderId];
        }

        $cards = $this->getService()->getCards($externalOrderId, $this->upstreamProvider());

        return is_array($cards) ? $cards : [];
    }

    /**
     * @param  array<string, mixed>  $phpOrder
     * @param  array<string, mixed>  $mpOrderData
     * @param  array<string, mixed>  $mpProductData
     * @param  array<int, array<string, mixed>>  $legacyCards
     */
    public function fireShadowIngest(array $phpOrder, array $mpOrderData, array $mpProductData, array $legacyCards): void
    {
        app(DgsShadowIngestService::class)->fireShadowIngest(
            $phpOrder,
            $mpOrderData,
            $mpProductData,
            $legacyCards
        );
    }

    public function getBalance(): float
    {
        return 0.0; 
    }

    public function getRates(): array
    {
        return $this->getService()->getExchangeRates($this->upstreamProvider());
    }

    private function shouldRouteFulfillmentToNode(): bool
    {
        $mode = (string) config('services.dgs.fulfillment_mode', 'http');

        if ($mode === 'http') {
            return false;
        }

        if ($mode === 'node') {
            return true;
        }

        if ($mode !== 'split') {
            return false;
        }

        // Phase 4.1: split routes upstream providers listed in WILDFLOW_SPLIT_FULFILLMENT_PROVIDERS.
        $allowed = (array) config('services.dgs.split_fulfillment_providers', ['ezpin-sandbox']);
        $upstream = $this->upstreamProvider();
        $providerType = (string) ($this->provider?->type ?? '');

        $candidates = array_values(array_unique(array_filter([
            $upstream,
            match ($providerType) {
                'wildflow-sandbox', 'ezpin-sandbox' => 'ezpin-sandbox',
                'wildflow', 'ezpin' => 'ezpin',
                default => $providerType,
            },
        ])));

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    private function upstreamProvider(): string
    {
        return (string) (
            data_get($this->provider?->settings, 'upstream_provider')
            ?? data_get($this->provider?->settings, 'provider')
            ?? data_get($this->provider?->credentials, 'upstream_provider')
            ?? data_get($this->provider?->credentials, 'provider')
            ?? match ($this->provider?->type) {
                'ezpin-sandbox', 'wildflow-sandbox' => 'ezpin-sandbox',
                'fazer' => 'fazer',
                default => 'ezpin',
            }
        );
    }
}
