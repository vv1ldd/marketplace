<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class B2BController extends Controller
{
    public function search(Request $request)
    {
        $inn = $request->get('inn');
        
        // 🧪 DEV OVERRIDE
        if ($inn === '526216895584') {
            $mockRaw = [
                'value' => 'ООО "СУВЕРЕННЫЕ ТЕХНОЛОГИИ"',
                'data' => [
                    'inn' => '526216895584',
                    'ogrn' => '1234567890123',
                    'kpp' => '526201001',
                    'address' => ['value' => 'г. Нижний Новгород, ул. Суверенная, д. 1'],
                    'management' => ['name' => 'Иванов Иван Иванович'],
                    'type' => 'LEGAL',
                    'state' => ['status' => 'ACTIVE'],
                    'tax_system' => 'ОСН'
                ]
            ];
            return response()->json([
                'suggestions' => [\App\Services\DaDataNormalizer::normalize($mockRaw)]
            ]);
        }

        try {
            $dadata = new \Dadata\DadataClient(config('services.dadata.token'), null);
            $result = $dadata->findById("party", $inn, 1);

            if (empty($result)) {
                return response()->json(['suggestions' => [], 'fallback' => true]);
            }

            // Normalize results
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
