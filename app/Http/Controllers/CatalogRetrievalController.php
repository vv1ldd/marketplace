<?php

namespace App\Http\Controllers;

use App\Services\CatalogQueryUnderstandingService;
use App\Services\CatalogRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class CatalogRetrievalController extends Controller
{
    public function __invoke(Request $request, CatalogRetrievalService $retrieval, CatalogQueryUnderstandingService $understandingService, \App\Services\CatalogSearchLogService $logService): JsonResponse
    {
        $payload = $request->isMethod('get')
            ? $this->payloadFromQuery($request)
            : $request->all();

        $validated = Validator::make($payload, [
            'query' => ['sometimes', 'nullable', 'string', 'max:200'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'intent' => ['sometimes', 'nullable', 'string', 'max:64'],
            'auto_understand' => ['sometimes', 'nullable'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'filters' => ['sometimes', 'array'],
            'filters.category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'filters.brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filters.region' => ['sometimes', 'nullable', 'string', 'max:32'],
            'filters.currency' => ['sometimes', 'nullable', 'string', 'max:16'],
            'filters.face_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.has_offer' => ['sometimes', 'boolean'],
            'filters.provider_network_only' => ['sometimes', 'boolean'],
        ])->validate();

        $understanding = null;
        if ($this->boolValue($validated['auto_understand'] ?? false)) {
            $understanding = $understandingService->understand(
                (string) ($validated['query'] ?? $validated['q'] ?? ''),
                $validated['locale'] ?? null,
            );

            $validated = $this->applyUnderstanding($validated, $understanding);
        }

        $response = $retrieval->retrieve($validated);
        if ($understanding !== null) {
            $response['understanding'] = $understanding;
        }

        $queryStr = (string) ($validated['query'] ?? $validated['q'] ?? '');
        if ($queryStr !== '') {
            $logService->log(
                $queryStr,
                'llm_retrieval',
                (int) ($response['match_count'] ?? 0)
            );
        }

        return response()->json(
            $response,
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromQuery(Request $request): array
    {
        $filters = (array) $request->query('filters', []);

        foreach (['category', 'brand', 'region', 'currency', 'face_value', 'has_offer', 'provider_network_only'] as $key) {
            if ($request->query->has($key)) {
                $filters[$key] = $request->query($key);
            }
        }

        return [
            'query' => $request->query('query', $request->query('q')),
            'q' => $request->query('q'),
            'intent' => $request->query('intent'),
            'auto_understand' => $request->query('auto_understand'),
            'locale' => $request->query('locale'),
            'limit' => $request->query('limit'),
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $understanding
     * @return array<string, mixed>
     */
    private function applyUnderstanding(array $validated, array $understanding): array
    {
        $rewrittenQuery = (string) ($understanding['rewritten_query'] ?? '');
        if ($rewrittenQuery !== '') {
            $validated['query'] = $rewrittenQuery;
        }

        if (! filled($validated['intent'] ?? null) && filled($understanding['intent'] ?? null)) {
            $validated['intent'] = $understanding['intent'];
        }

        $inferredFilters = $this->filtersArray($understanding['filters'] ?? []);
        $explicitFilters = $this->filledFilterValues((array) ($validated['filters'] ?? []));
        $validated['filters'] = array_merge($inferredFilters, $explicitFilters);

        return Arr::except($validated, ['auto_understand', 'locale']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersArray(mixed $filters): array
    {
        if ($filters instanceof \stdClass) {
            return get_object_vars($filters);
        }

        return is_array($filters) ? $filters : [];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filledFilterValues(array $filters): array
    {
        return array_filter(
            $filters,
            fn (mixed $value): bool => $value === false || $value === true || filled($value)
        );
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
