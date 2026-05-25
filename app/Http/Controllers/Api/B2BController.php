<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class B2BController extends Controller
{
    public function search(Request $request)
    {
        $inn = preg_replace('/\D+/', '', (string) $request->get('inn', ''));
        if (!in_array(strlen($inn), [10, 12], true)) {
            return response()->json(['suggestions' => [], 'fallback' => true, 'npd' => null]);
        }

        $token = config('services.dadata.token');
        
        if (!$token) {
            \Log::error("DaData Token is missing in config.");
            $npd = strlen($inn) === 12
                ? app(\App\Services\NpdStatusService::class)->check($inn)
                : null;

            return response()->json(['suggestions' => [], 'fallback' => true, 'npd' => $npd]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Token ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party', [
                'query' => $inn,
                'count' => 1
            ]);

            if ($response->failed()) {
                throw new \Exception("DaData API failed: " . $response->body());
            }

            $result = $response->json()['suggestions'] ?? [];

            if (empty($result)) {
                $npd = strlen($inn) === 12
                    ? app(\App\Services\NpdStatusService::class)->check($inn)
                    : null;

                return response()->json(['suggestions' => [], 'fallback' => true, 'npd' => $npd]);
            }

            // Normalize results using our service
            $normalized = array_map(function($item) {
                return \App\Services\DaDataNormalizer::normalize($item);
            }, $result);

            return response()->json(['suggestions' => $normalized]);
        } catch (\Exception $e) {
            \Log::error("DaData Error: " . $e->getMessage());
            $npd = strlen($inn) === 12
                ? app(\App\Services\NpdStatusService::class)->check($inn)
                : null;

            return response()->json(['suggestions' => [], 'fallback' => true, 'npd' => $npd]);
        }
    }
}
