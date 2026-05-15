<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class B2BController extends Controller
{
    public function search(Request $request)
    {
        $inn = $request->get('inn');
        
        try {
            $dadata = new \Dadata\DadataClient(config('services.dadata.token'), null);
            $result = $dadata->findById("party", $inn, 1);

            if (empty($result)) {
                return response()->json(['suggestions' => [], 'fallback' => true]);
            }

            // Normalize results using our new service
            $normalized = array_map(function($item) {
                return \App\Services\DaDataNormalizer::normalize($item);
            }, $result);

            return response()->json(['suggestions' => $normalized]);
        } catch (\Exception $e) {
            \Log::error("DaData Error: " . $e->getMessage());
            return response()->json(['suggestions' => [], 'fallback' => true]);
        }
    }
}
