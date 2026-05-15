<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class B2BController extends Controller
{
    public function search(Request $request)
    {
        $inn = $request->get('inn');
        $isIP = strlen($inn) === 12;
        
        // 🧪 DEV OVERRIDE
        if ($inn === '526216895584') {
            return response()->json([
                'suggestions' => [[
                    'value' => 'ООО "СУВЕРЕННЫЕ ТЕХНОЛОГИИ"',
                    'data' => [
                        'inn' => '526216895584',
                        'ogrn' => '1234567890123',
                        'kpp' => '526201001',
                        'address' => ['value' => 'г. Нижний Новгород, ул. Суверенная, д. 1'],
                        'management' => ['name' => 'Иванов Иван Иванович'],
                        'type' => 'LEGAL'
                    ]
                ]]
            ]);
        }

        try {
            $dadata = new \Dadata\DadataClient(config('services.dadata.token'), null);
            $result = $dadata->findById("party", $inn, 1);

            if (empty($result)) {
                return response()->json(['suggestions' => [], 'fallback' => true]);
            }

            // Enrich the result with extra flags
            foreach ($result as &$item) {
                $item['is_ip'] = $isIP;
                // Determine tax system hint (simplified logic)
                $item['tax_system_hint'] = $isIP ? 'USN' : 'OSN'; 
            }

            return response()->json(['suggestions' => $result]);
        } catch (\Exception $e) {
            \Log::error("DaData Error: " . $e->getMessage());
            return response()->json(['suggestions' => [], 'fallback' => true]);
        }
    }
}
