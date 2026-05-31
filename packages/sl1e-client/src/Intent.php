<?php

namespace SimpleLayer\Sl1e;

final readonly class Intent
{
    public function __construct(
        public ?string $type = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $cta = null,
        public ?string $nonce = null,
        public ?string $resource = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        return array_filter([
            'intent_type' => self::clean($this->type, 80),
            'intent_title' => self::clean($this->title, 96),
            'intent_description' => self::clean($this->description, 220),
            'intent_cta' => self::clean($this->cta, 64),
            'intent_nonce' => self::clean($this->nonce, 80),
            'intent_resource' => self::clean($this->resource, 160),
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    private static function clean(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(strip_tags($value));
        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }
}
