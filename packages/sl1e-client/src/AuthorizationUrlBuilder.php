<?php

namespace SimpleLayer\Sl1e;

final readonly class AuthorizationUrlBuilder
{
    public function __construct(private Sl1eConfig $config)
    {
    }

    public function build(AuthorizeRequest $request): string
    {
        $params = AuthorizeQueryParameters::build($this->config, $request);

        return $this->config->providerUrl('/authorize').'?'.http_build_query($params);
    }
}
