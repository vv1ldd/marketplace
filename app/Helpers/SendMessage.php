<?php

namespace App\Helpers;

use App\Models\Order;

class SendMessage
{
    /**
     * @param Order $order
     * @return string
     */
    public static function tg(Order $order): string
    {
        $items = $order->items()->get();

        $client_info = (object)$order->client_info;

        $message = "Новый заказ #{$order->id}\n";
        $message .= "Статус: {$order->status}\n";
        $message .= "-------\n";

        $message .= "Клиент:" . data_get($client_info, 'lastName', 'нет данных') . " " . data_get($client_info, 'firstName', 'нет данных') . " " . data_get($client_info, 'middleName', 'нет данных') . "\n";
        $message .= "Телефон: " . data_get($client_info, 'phone', 'нет данных') . "\n";
        $message .= "Почта: " . data_get($client_info, 'email', 'нет данных') . "\n";
        $message .= "-------\n";
        $message .= "Товары:\n";

        foreach ($items as $item) {
            $message .= "{$item['sku']} - {$item['count']} шт. \n";
        }

        return $message;
    }
}
