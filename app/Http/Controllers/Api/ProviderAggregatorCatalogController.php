<?php

namespace App\Http\Controllers\Api;

use App\Models\Provider;
use App\Services\Provider\ProviderCatalogAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProviderAggregatorCatalogController extends Controller
{
    public function __construct(private readonly ProviderCatalogAggregator $catalog)
    {
    }

    public function unifiedCatalog(Request $request, string $provider): JsonResponse
    {
        $record = $this->resolveProvider($provider);

        if (! $this->authorized($request, $record)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (! $record) {
            return response()->json([
                'success' => true,
                'provider' => ['type' => $provider],
                'disabled' => true,
                'count' => 0,
                'items' => [],
            ]);
        }

        $payload = $this->catalog->unifiedCatalog($record, $request->boolean('include_inactive'));
        if (! $request->boolean('include_raw')) {
            $payload['items'] = collect($payload['items'])
                ->map(function (array $item): array {
                    unset($item['raw_data']);

                    return $item;
                })
                ->all();
        }

        if ($provider !== $record->type) {
            $payload['provider']['requested_type'] = $provider;
        }

        return response()->json(
            $payload
        );
    }

    private function resolveProvider(string $provider): ?Provider
    {
        $type = $provider === 'ezpin' ? 'wildflow' : $provider;

        return Provider::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();
    }

    private function authorized(Request $request, ?Provider $provider): bool
    {
        $providedToken = trim((string) ($request->header('X-Auth-Token') ?: $request->bearerToken()));
        if ($providedToken === '') {
            return false;
        }

        $validTokens = collect([
            data_get($provider?->credentials, 'api_key'),
            config('app.wildflow_token'),
        ])
            ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
            ->map(fn (string $token): string => trim($token));

        return $validTokens->contains(
            fn (string $token): bool => hash_equals($token, $providedToken)
        );
    }
}
