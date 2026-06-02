<?php

namespace FazerSdk\Endpoints;

class Webhooks extends AbstractEndpoint
{
    public function get(): array
    {
        return $this->request('GET', 'me/webhook')->json();
    }

    public function set(string $url): array
    {
        return $this->request('POST', 'me/webhook', ['webhook_url' => $url])->json();
    }

    public function delete(): array
    {
        return $this->request('DELETE', 'me/webhook')->json();
    }

    public function update(string $url): array
    {
        return $this->request('PUT', 'me/webhook/settings', ['webhook_url' => $url])->json();
    }

    public function regenerateSecret(): array
    {
        return $this->request('POST', 'me/webhook/regenerate')->json();
    }

    public function test(): array
    {
        return $this->request('POST', 'me/webhook/test')->json();
    }
}
