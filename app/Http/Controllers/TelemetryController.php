<?php

namespace App\Http\Controllers;

use App\Models\TelegramPost;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function telegramClick(Request $request, $id)
    {
        $post = TelegramPost::with(['channel', 'product'])->findOrFail($id);
        
        // 1. Увеличиваем счетчик кликов
        $post->increment('clicks');

        // 2. Логика перенаправления
        // Вариант А: Перекидываем на Telegram-менеджера
        $channel = $post->channel;
        $settings = $channel->settings ?? [];
        $manager = $settings['telegram_manager_username'] ?? '';
        
        $productName = $post->product ? $post->product->name : 'Товар';
        $price = $post->posted_price;

        // Формируем текст для сообщения менеджеру
        $text = urlencode("Привет! Хочу купить: {$productName} за {$price} руб. (ID поста: {$post->id})");

        if ($manager) {
            return redirect()->away("https://t.me/{$manager}?text={$text}");
        }

        // Если менеджера нет, просто возвращаем 404
        abort(404, 'Менеджер не настроен');
        
        // В будущем (Вариант Б) здесь будет:
        // return view('checkout', ['post' => $post]);
    }

    public function telegramWebhook(Request $request, $token)
    {
        $channel = \App\Models\DirectChannel::where('settings->telegram_bot_token', $token)->first();
        
        if (!$channel) {
            return response()->json(['ok' => false, 'error' => 'Channel not found'], 404);
        }

        $botService = new \App\Services\TelegramBotService($channel);
        $botService->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
