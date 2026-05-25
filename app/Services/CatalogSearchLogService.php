<?php

namespace App\Services;

use App\Models\CatalogSearchLog;
use Illuminate\Support\Facades\Log;

class CatalogSearchLogService
{
    public function __construct(
        private readonly CatalogQueryUnderstandingService $understandingService
    ) {}

    /**
     * Log a search query with dynamically parsed intent and filters.
     *
     * @param string $query The search query entered by the user
     * @param string $source The source of the search query (storefront, llm_retrieval, llm_understanding)
     * @param int $resultsCount The number of results matched at search time
     * @return CatalogSearchLog|null The persisted log model or null on failure/empty query
     */
    public function log(string $query, string $source, int $resultsCount = 0): ?CatalogSearchLog
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        try {
            // Analyze the search query using the query understanding service
            $understanding = $this->understandingService->understand($query);

            $normalizedQuery = (string) ($understanding['normalized_query'] ?? $query);
            $intent = $understanding['intent'] ?? null;
            $filters = $understanding['filters'] ?? null;
            $confidence = $understanding['confidence'] ?? null;

            // In Laravel/Eloquent, casting will automatically serialize array/object to JSON
            $log = CatalogSearchLog::create([
                'query' => $query,
                'normalized_query' => $normalizedQuery,
                'source' => $source,
                'intent' => $intent,
                'filters' => $filters,
                'confidence' => $confidence,
                'results_count' => $resultsCount,
            ]);

            // Lightweight self-learning suggestion trigger
            try {
                app(\App\Services\QueryNormalizationService::class)->generateSuggestion($query);
            } catch (\Throwable $e) {
                // Ignore suggestion engine exceptions to preserve core logging speed
            }

            if ($source === 'storefront') {
                try {
                    session()->put('last_search_log_id', $log->id);

                    // Track search journey for Multi-Touch attribution
                    $journey = session()->get('search_journey_log_ids', []);
                    if (! in_array($log->id, $journey)) {
                        $journey[] = $log->id;
                    }
                    // Cap journey size to 20 to prevent session bloat
                    if (count($journey) > 20) {
                        array_shift($journey);
                    }
                    session()->put('search_journey_log_ids', $journey);
                } catch (\Throwable $sessionError) {
                    // Ignore session issues in non-web context
                }
            }


            return $log;
        } catch (\Throwable $e) {
            // Report to system logs, but do NOT block execution flow
            Log::error('Search intent logging failed: ' . $e->getMessage(), [
                'query' => $query,
                'source' => $source,
                'results_count' => $resultsCount,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Increment the storefront views count of a search log safely.
     */
    public function incrementViews(?int $logId): void
    {
        if (! $logId) {
            return;
        }

        try {
            CatalogSearchLog::whereKey($logId)->increment('views_count');
        } catch (\Throwable $e) {
            Log::warning('Failed to increment views count on search log: ' . $e->getMessage(), ['id' => $logId]);
        }
    }

    /**
     * Increment the storefront carts count of a search log safely.
     */
    public function incrementCarts(?int $logId): void
    {
        if (! $logId) {
            return;
        }

        try {
            CatalogSearchLog::whereKey($logId)->increment('carts_count');
        } catch (\Throwable $e) {
            Log::warning('Failed to increment carts count on search log: ' . $e->getMessage(), ['id' => $logId]);
        }
    }
}
