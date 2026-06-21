<?php

namespace App\Support;

class SettlementNetwork
{
    /**
     * @param array<int, string> $assets
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $shortLabel,
        public readonly string $protocol,
        public readonly string $authority,
        public readonly string $status,
        public readonly bool $enabled,
        public readonly bool $storefrontVisible,
        public readonly string $traceLabel,
        public readonly string $contractKey,
        public readonly ?int $chainId = null,
        public readonly ?string $nativeSymbol = null,
        public readonly array $assets = [],
        public readonly ?string $rpcUrl = null,
        public readonly bool $rpcEnabled = false,
        public readonly int $requiredConfirmations = 1,
    ) {}

    public function isLive(): bool
    {
        return $this->enabled && $this->status === 'live';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'short_label' => $this->shortLabel,
            'protocol' => $this->protocol,
            'authority' => $this->authority,
            'status' => $this->status,
            'enabled' => $this->enabled,
            'storefront_visible' => $this->storefrontVisible,
            'trace_label' => $this->traceLabel,
            'contract_key' => $this->contractKey,
            'chain_id' => $this->chainId,
            'native_symbol' => $this->nativeSymbol,
            'assets' => $this->assets !== [] ? $this->assets : null,
            'rpc_enabled' => $this->rpcEnabled ?: null,
            'required_confirmations' => $this->requiredConfirmations > 1 ? $this->requiredConfirmations : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorefrontCatalogEntry(): array
    {
        $entry = [
            'key' => $this->key,
            'label' => $this->label,
            'protocol' => $this->protocol,
            'status' => $this->status,
            'enabled' => $this->enabled,
            'chain_id' => $this->chainId,
        ];

        if (SettlementAdapterConfig::isConfigured($this->key)) {
            $entry['adapter_enabled'] = SettlementAdapterConfig::isEnabled($this->key);
            $entry['adapter_mode'] = SettlementAdapterConfig::mode($this->key);
        }

        return $entry;
    }
}
