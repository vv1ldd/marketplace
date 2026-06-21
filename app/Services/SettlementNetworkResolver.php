<?php

namespace App\Services;

use App\Support\SettlementAdapterConfig;
use App\Support\SettlementNetwork;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class SettlementNetworkResolver
{
    /** @var list<string> */
    private const CRYPTO_RAIL_PROTOCOLS = ['evm', 'utxo', 'solana'];

    public function defaultKey(): string
    {
        return (string) config('blockchain_networks.default', 'simple-layer-1');
    }

    public function resolve(string $key): SettlementNetwork
    {
        $config = $this->configFor($key);

        return $this->networkFromConfig($key, $config);
    }

    public function default(): SettlementNetwork
    {
        return $this->resolve($this->defaultKey());
    }

    /**
     * @return array<int, SettlementNetwork>
     */
    public function all(): array
    {
        return array_values(array_map(
            fn (string $key): SettlementNetwork => $this->resolve($key),
            array_keys($this->networkConfigs()),
        ));
    }

    /**
     * @return array<int, SettlementNetwork>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (SettlementNetwork $network): bool => $network->enabled,
        ));
    }

    /**
     * @return array<int, SettlementNetwork>
     */
    public function storefrontVisible(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (SettlementNetwork $network): bool => $network->storefrontVisible,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function storefrontCatalog(): array
    {
        return [
            'default' => $this->defaultKey(),
            'items' => array_map(
                static fn (SettlementNetwork $network): array => $network->toStorefrontCatalogEntry(),
                $this->storefrontVisible(),
            ),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function networkConfigs(): array
    {
        return (array) config('blockchain_networks.networks', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function configFor(string $key): array
    {
        $config = $this->networkConfigs()[$key] ?? null;
        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown settlement network [{$key}].");
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function networkFromConfig(string $key, array $config): SettlementNetwork
    {
        $protocol = (string) ($config['protocol'] ?? 'unknown');
        $enabled = (bool) ($config['enabled'] ?? false);
        $storefrontVisible = (bool) ($config['storefront_visible'] ?? false);
        $rpcEnabled = (bool) ($config['rpc_enabled'] ?? false);
        $status = (string) ($config['status'] ?? 'disabled');

        if (in_array($protocol, self::CRYPTO_RAIL_PROTOCOLS, true) && ! $this->cryptoRailsEnabled()) {
            $enabled = false;
            $storefrontVisible = false;
            $rpcEnabled = false;
        } elseif (in_array($protocol, self::CRYPTO_RAIL_PROTOCOLS, true) && SettlementAdapterConfig::isConfigured($key)) {
            if (SettlementAdapterConfig::isEnabled($key)) {
                $enabled = true;
                $storefrontVisible = true;
                $status = SettlementAdapterConfig::mode($key) === SettlementAdapterConfig::MODE_FULL
                    ? 'live'
                    : 'read_only';
            } else {
                $enabled = false;
                $storefrontVisible = $this->cryptoRailsEnabled();
                $status = (string) ($config['status'] ?? 'coming_soon');
            }
        }

        return new SettlementNetwork(
            key: $key,
            label: (string) ($config['label'] ?? $key),
            shortLabel: (string) ($config['short_label'] ?? strtoupper($key)),
            protocol: $protocol,
            authority: (string) ($config['authority'] ?? 'unknown'),
            status: $status,
            enabled: $enabled,
            storefrontVisible: $storefrontVisible,
            traceLabel: (string) ($config['trace_label'] ?? ($config['label'] ?? $key)),
            contractKey: (string) ($config['contract_key'] ?? $key),
            chainId: isset($config['chain_id']) ? (int) $config['chain_id'] : null,
            nativeSymbol: isset($config['native_symbol']) ? (string) $config['native_symbol'] : null,
            assets: array_values(array_filter(Arr::wrap($config['assets'] ?? []))),
            rpcUrl: isset($config['rpc_url']) && is_string($config['rpc_url']) && $config['rpc_url'] !== ''
                ? $config['rpc_url']
                : null,
            rpcEnabled: $rpcEnabled,
            requiredConfirmations: max(1, (int) ($config['required_confirmations'] ?? 1)),
        );
    }

    private function cryptoRailsEnabled(): bool
    {
        return (bool) config('blockchain_networks.crypto_rails_enabled', false);
    }
}
