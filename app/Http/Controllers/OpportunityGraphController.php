<?php

namespace App\Http\Controllers;

use App\Services\OpportunityGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OpportunityGraphController extends Controller
{
    public function opportunities(Request $request, OpportunityGraphService $graph): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'direction' => ['sometimes', 'nullable', 'in:asc,desc'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'min_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'has_active_case' => ['sometimes', 'nullable'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return response()->json(
            $graph->opportunities($filters),
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function entity(string $type, string $slug, OpportunityGraphService $graph): JsonResponse
    {
        $payload = $graph->entity($type, $slug);
        abort_unless($payload !== null, 404);

        return response()->json(
            $payload,
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function actionEffectiveness(OpportunityGraphService $graph): JsonResponse
    {
        return response()->json(
            $graph->actionEffectiveness(),
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
