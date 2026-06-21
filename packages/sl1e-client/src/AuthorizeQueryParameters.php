<?php

namespace SimpleLayer\Sl1e;

use SimpleLayer\Sl1e\Exception\Sl1eConfigurationException;

final readonly class AuthorizeQueryParameters
{
    /**
     * @return array<string, string>
     */
    public static function build(Sl1eConfig $config, AuthorizeRequest $request): array
    {
        if ($config->clientId === '') {
            throw new Sl1eConfigurationException('SL1E client_id is required.');
        }

        if ($request->redirectUri === '' || $request->state === '' || $request->nonce === '') {
            throw new Sl1eConfigurationException('SL1E redirect_uri, state and nonce are required.');
        }

        return array_filter([
            'client_id' => $config->clientId,
            'client_name' => $config->clientName ?: $config->clientId,
            'ui_theme' => $config->uiTheme,
            'redirect_uri' => $request->redirectUri,
            'scope' => $request->scope,
            'state' => $request->state,
            'nonce' => $request->nonce,
            'mode' => $request->mode === 'register' ? 'register' : 'login',
            'response_mode' => match ($request->responseMode) {
                'form_post' => 'form_post',
                'query' => 'query',
                default => 'code',
            },
            ...($request->intent?->toQueryParams() ?? []),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }
}
