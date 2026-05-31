<?php

namespace SimpleLayer\Sl1e\Contracts;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function postJson(string $url, array $payload, bool $verifyTls = true): HttpResponse;
}
