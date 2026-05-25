<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NpdStatusService
{
    private const ENDPOINT = 'https://statusnpd.nalog.ru/api/v1/tracker/taxpayer_status';

    public function check(string $inn, ?string $date = null): array
    {
        $inn = preg_replace('/\D+/', '', $inn);

        if (strlen($inn) !== 12) {
            return [
                'status' => false,
                'message' => 'Статус НПД проверяется только для 12-значного ИНН физлица.',
            ];
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'inn' => $inn,
                    'requestDate' => $date ?: now()->toDateString(),
                ]);

            if ($response->failed()) {
                return [
                    'status' => false,
                    'message' => 'Не удалось проверить статус самозанятого в ФНС.',
                ];
            }

            return [
                'status' => (bool) $response->json('status'),
                'message' => (string) ($response->json('message') ?? ''),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'status' => false,
                'message' => 'Сервис проверки самозанятых временно недоступен.',
            ];
        }
    }
}
