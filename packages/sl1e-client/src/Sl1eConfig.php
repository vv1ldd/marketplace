<?php

namespace SimpleLayer\Sl1e;

final readonly class Sl1eConfig
{
    public function __construct(
        public string $identityProviderUrl = 'https://pass.simplelayer.one',
        public string $clientId = '',
        public string $clientName = '',
        public string $uiTheme = 'default',
        public bool $verifyTls = true,
        public string $codeExchangePath = '/api/sl1e/authorization-code/exchange',
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            identityProviderUrl: rtrim((string) ($config['identity_provider_url'] ?? $config['provider_url'] ?? 'https://pass.simplelayer.one'), '/'),
            clientId: (string) ($config['client_id'] ?? ''),
            clientName: (string) ($config['client_name'] ?? ''),
            uiTheme: (string) ($config['ui_theme'] ?? 'default'),
            verifyTls: filter_var($config['verify_tls'] ?? true, FILTER_VALIDATE_BOOL),
            codeExchangePath: (string) ($config['code_exchange_path'] ?? '/api/sl1e/authorization-code/exchange'),
        );
    }

    public function providerUrl(string $path = ''): string
    {
        $base = rtrim($this->identityProviderUrl, '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }
}
