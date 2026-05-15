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
            return response()->json([
                'suggestions' => [
                    [
                        'value' => 'ООО "СУВЕРЕННЫЕ ТЕХНОЛОГИИ"',
                        'data' => [
                            'inn' => '526216895584',
                            'ogrn' => '1234567890123',
                            'address' => ['value' => 'г. Нижний Новгород, ул. Суверенная, д. 1'],
                            'name' => ['full_with_opf' => 'ООО "СУВЕРЕННЫЕ ТЕХНОЛОГИИ"']
                        ]
                    ]
                ]
            ]);
        }

        try {
            $dadata = new \Dadata\DadataClient(config('services.dadata.token'), null);
            $result = $dadata->findById("party", $inn, 1);

            if (empty($result)) {
                return response()->json([
                    'suggestions' => [],
                    'message' => 'Организация не найдена в реестре. Вы можете ввести данные вручную.',
                    'fallback' => true
                ]);
            }

            return response()->json(['suggestions' => $result]);
        } catch (\Exception $e) {
            \Log::error("DaData Error: " . $e->getMessage());
            return response()->json([
                'suggestions' => [],
                'message' => 'Сервис верификации временно недоступен. Введите данные вручную.',
                'fallback' => true
            ]);
        }
    }
}
