<?php

namespace SimpleLayer\Sl1e\Contracts;

final readonly class HttpResponse
{
    /**
     * @param array<string, mixed> $json
     */
    public function __construct(
        public int $status,
        public array $json = [],
        public string $body = '',
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
