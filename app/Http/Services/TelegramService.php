<?php

namespace App\Http\Services;

use App\Models\YmSend;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    /** @var string */
    private string $base_url = 'https://api.telegram.org/bot';
    /** @var PendingRequest */
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::baseUrl($this->base_url . config('services.tg.token'));
    }

    /**
     * @param string $message
     * @return int
     * @throws ConnectionException
     */
    public function sendMessage(string $message): int
    {
        $response = $this->client->post('sendMessage', [
            'chat_id' => config('services.tg.chat_id'),
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('result.message_id');
    }
}
