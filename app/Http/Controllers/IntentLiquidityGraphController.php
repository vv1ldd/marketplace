<?php

namespace App\Http\Controllers;

use App\Services\IntentLiquidityGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IntentLiquidityGraphController extends Controller
{
    public function index(Request $request, IntentLiquidityGraphService $graph): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'intent' => ['sometimes', 'nullable', 'string', 'max:64'],
            'actor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return response()->json(
            $graph->graph($filters),
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function show(string $intentKey, IntentLiquidityGraphService $graph): JsonResponse
    {
        $payload = $graph->node($intentKey);
        abort_unless($payload !== null, 404);

        return response()->json(
            $payload,
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
