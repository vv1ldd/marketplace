<?php

namespace App\Services\Llm;

class LlmResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $text,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly ?string $error = null,
        public readonly bool $fallbackUsed = false,
        public readonly array $metadata = [],
    ) {
    }

    public static function success(string $text, string $provider, ?string $model = null, array $metadata = []): self
    {
        return new self(true, $text, $provider, $model, null, false, $metadata);
    }

    public static function failure(string $provider, string $error, ?string $model = null, array $metadata = []): self
    {
        return new self(false, '', $provider, $model, $error, false, $metadata);
    }

    public function withFallbackUsed(): self
    {
        return new self(
            ok: $this->ok,
            text: $this->text,
            provider: $this->provider,
            model: $this->model,
            error: $this->error,
            fallbackUsed: true,
            metadata: $this->metadata,
        );
    }
}
