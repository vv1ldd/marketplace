<?php

namespace App\Http\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoogleTranslateService
{
    /**
     * @param string $text
     * @param string $from
     * @param string $to
     * @return array|mixed
     * @throws ConnectionException
     */
    public static function translate(string $text, string $from = 'en', string $to = 'ru')
    {
        $apiKey = config('services.google_translate.api_key');

        $response = Http::withOptions([
            'timeout' => 30,
            'verify' => false,
        ])->post('https://translation.googleapis.com/language/translate/v2?key=' . $apiKey, [
            'q' => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text',
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body());
        }

        return $response->json('data.translations.0.translatedText');
    }


}
