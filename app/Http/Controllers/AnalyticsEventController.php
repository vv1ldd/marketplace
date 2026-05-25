<?php

namespace App\Http\Controllers;

use App\Services\MeanlyAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsEventController extends Controller
{
    public function store(Request $request, MeanlyAnalyticsService $analytics): JsonResponse
    {
        $data = $request->validate([
            'event_name' => 'required|string|max:160',
            'event_type' => 'nullable|string|max:64',
            'surface' => 'nullable|string|max:64',
            'severity' => 'nullable|string|max:24',
            'duration_ms' => 'nullable|integer|min:0|max:600000',
            'visitor_id' => 'nullable|string|max:128',
            'product_id' => 'nullable|integer',
            'order_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'legal_entity_id' => 'nullable|integer',
            'provider_type' => 'nullable|string|max:64',
            'category' => 'nullable|string|max:128',
            'currency' => 'nullable|string|max:12',
            'metadata' => 'nullable|array',
        ]);

        $metadata = (array) ($data['metadata'] ?? []);
        unset($data['metadata']);

        $analytics->track((string) $data['event_name'], $metadata + [
            'visitor_id' => $data['visitor_id'] ?? null,
        ], [
            'event_type' => $data['event_type'] ?? 'client',
            'surface' => $data['surface'] ?? 'storefront',
            'severity' => $data['severity'] ?? 'info',
            'duration_ms' => $data['duration_ms'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'shop_id' => $data['shop_id'] ?? null,
            'legal_entity_id' => $data['legal_entity_id'] ?? null,
            'provider_type' => $data['provider_type'] ?? null,
            'category' => $data['category'] ?? null,
            'currency' => $data['currency'] ?? null,
            'visitor_id' => $data['visitor_id'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}
