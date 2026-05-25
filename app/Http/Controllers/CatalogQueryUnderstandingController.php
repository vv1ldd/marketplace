<?php

namespace App\Http\Controllers;

use App\Services\CatalogQueryUnderstandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CatalogQueryUnderstandingController extends Controller
{
    public function __invoke(Request $request, CatalogQueryUnderstandingService $understanding, \App\Services\CatalogSearchLogService $logService): JsonResponse
    {
        $payload = $request->isMethod('get') ? [
            'query' => $request->query('query', $request->query('q')),
            'q' => $request->query('q'),
            'locale' => $request->query('locale'),
        ] : $request->all();

        $validated = Validator::make($payload, [
            'query' => ['sometimes', 'nullable', 'string', 'max:240'],
            'q' => ['sometimes', 'nullable', 'string', 'max:240'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
        ])->validate();

        $queryStr = (string) ($validated['query'] ?? $validated['q'] ?? '');

        if ($queryStr !== '') {
            $logService->log($queryStr, 'llm_understanding', 0);
        }

        return response()->json(
            $understanding->understand(
                $queryStr,
                $validated['locale'] ?? null,
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
