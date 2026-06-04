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
        public string $nativeDeepLinkScheme = 'simplel1',
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
            nativeDeepLinkScheme: (string) ($config['native_deep_link_scheme'] ?? 'simplel1'),
        );
    }

    public function providerUrl(string $path = ''): string
    {
        $base = rtrim($this->identityProviderUrl, '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    public function nativeDeepLinkScheme(): string
    {
        $scheme = trim($this->nativeDeepLinkScheme);
        $scheme = preg_replace('#://.*$#', '', $scheme) ?: '';
        $scheme = rtrim($scheme, ':/.');

        return preg_match('/^[a-z][a-z0-9+.-]*$/i', $scheme) === 1
            ? strtolower($scheme)
            : '';
    }
}
