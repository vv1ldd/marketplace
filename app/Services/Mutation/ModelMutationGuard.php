<?php

namespace App\Services\Mutation;

use RuntimeException;
use Illuminate\Support\Facades\Log;

class ModelMutationGuard
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function guard(string $mutationPath, array $metadata = []): void
    {
        if (MutationContext::isActive()) {
            return;
        }

        $mode = strtolower((string) config('mutation.model_hook_mode', 'shadow'));
        $payload = ['mutation_path' => $mutationPath, 'mode' => $mode] + $metadata;

        if (in_array($mode, ['enforce', 'hard', 'hard_enforce'], true)) {
            Log::error('Model mutation hook blocked without mutation context', $payload);

            throw new RuntimeException("Model mutation hook requires mutation context: {$mutationPath}");
        }

        if ($mode !== 'disabled') {
            Log::warning('Model mutation hook executed without mutation context', $payload);
        }
    }
}
