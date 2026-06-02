<?php

namespace App\Services\Mutation;

use Closure;

class MutationContext
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $context = null;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function bind(array $context, ?Closure $callback = null): mixed
    {
        $previous = self::$context;
        self::$context = $context;

        if ($callback === null) {
            return null;
        }

        try {
            return $callback();
        } finally {
            self::$context = $previous;
        }
    }

    public static function clear(): void
    {
        self::$context = null;
    }

    public static function isActive(): bool
    {
        return filled(self::$context['mutation_id'] ?? null);
    }

    public static function mutationId(): ?string
    {
        return self::$context['mutation_id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$context ?? [];
    }
}
