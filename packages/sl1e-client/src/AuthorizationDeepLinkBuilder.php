<?php

namespace SimpleLayer\Sl1e;

use SimpleLayer\Sl1e\Exception\Sl1eConfigurationException;

final readonly class AuthorizationDeepLinkBuilder
{
    public function __construct(private Sl1eConfig $config)
    {
    }

    public function build(AuthorizeRequest $request): string
    {
        $scheme = $this->config->nativeDeepLinkScheme();
        if ($scheme === '') {
            throw new Sl1eConfigurationException('SL1E native deep link scheme is required.');
        }

        $params = AuthorizeQueryParameters::build($this->config, $request);

        return $scheme.'://authorize?'.http_build_query($params);
    }
}
