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
        $message .= "Клиент: {$client_info->lastName} {$client_info->firstName} {$client_info->middleName}\n";
        $message .= "Телефон: {$client_info->phone}\n";
        $message .= "Почта: {$client_info->email}\n";
        $message .= "-------\n";
        $message .= "Товары:\n";

        foreach ($items as $item) {
            $message .= "{$item['sku']} - {$item['count']} шт. \n";
        }

        return $message;
    }
}
