<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyTelemetry;
use App\Http\Services\ShadowConsensusService;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function report(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|exists:currencies,code',
            'rate' => 'required|numeric|min:0',
            'type' => 'nullable|string', // telegram, manual, p2p
            'source' => 'nullable|string|max:255',
            'reporter_id' => 'nullable|string|max:255',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'city' => 'nullable|string',
            'observed_at' => 'nullable|date',
            'key' => 'required|string'
        ]);

        if ($validated['key'] !== config('app.telemetry_key', 'sovereign_secret')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $currency = Currency::where('code', $validated['code'])->first();
        if (!$currency->is_shadow) {
            return response()->json(['error' => 'Not a Shadow FX currency'], 422);
        }

        // Layer 1: Save Raw Observation
        CurrencyTelemetry::create([
            'currency_code' => $validated['code'],
            'rate' => $validated['rate'],
            'source_type' => $validated['type'] ?? 'manual',
            'source_name' => $validated['source'],
            'reporter_id' => $validated['reporter_id'],
            'city' => $validated['city'],
            'confidence' => $validated['confidence'] ?? 0.5,
            'observed_at' => $validated['observed_at'] ?? now(),
        ]);

        // Layer 2: Run Consensus Engine
        $consensus = new ShadowConsensusService();
        $consensus->updateConsensus($validated['code']);

        return response()->json([
            'success' => true,
            'new_consensus_rate' => $currency->fresh()->manual_rate,
            'observations_count' => CurrencyTelemetry::where('currency_code', $validated['code'])->count()
        ]);
    }
}
