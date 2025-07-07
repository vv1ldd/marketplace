<?php

namespace App\Helpers;

use App\Models\Order;
use App\Models\OrderItems;

class SendMessage
{
    /**
     * @param Order $order
     * @param string $status
     * @param OrderItems|null $order_item
     * @return string
     */
    public static function tg(Order $order, string $status = 'new', ?OrderItems $order_item = null): string
    {

        $message = '';

        if ($status === 'new') {
            $items = $order->items()->get();

            $client_info = $order->client_info;

            $message = "Новый заказ #{$order->id}\n";
            $message .= "Статус: {$order->status}\n";
            $message .= "-------\n";

            $message .= "Данные покупателя от Яндекса:\n";
            $message .= "ФИО: " . data_get($client_info, 'lastName', '-') . " " . data_get($client_info, 'firstName', '-') . " " . data_get($client_info, 'middleName', '-') . "\n";
            $message .= "Телефон: " . data_get($client_info, 'phone', '-') . "\n";
            $message .= "Почта: " . data_get($client_info, 'email', '-') . "\n";
            $message .= "-------\n";

            $message .= "Товары:\n";
            foreach ($items as $item) {
                $message .= "{$item['sku']} - {$item['count']} шт. \n";
            }
        } else if ($status === 'send_form') {

            $message .= "Активация по заказу #{$order->id}\n";
            $message .= "Активировано sku: {$order_item->sku}\n";
            $message .= "-------\n";
            $message .= "Данные заполненной формы:\n";

            $client_info = $order_item->client_info;

            $message .= "ФИО: " . data_get($client_info, 'last_name', '-') . " " . data_get($client_info, 'first_name', '-') . "\n";
            $message .= "Телефон: " . data_get($client_info, 'phone', '-') . "\n";
            $message .= "Почта: " . data_get($client_info, 'email', '-') . "\n";
            $message .= "-------\n";

            $option = data_get($client_info, 'option');

            if (!empty($option)) {
                $message .= "Опция:\n";
                $option = $option[0];

                foreach ($option as $key => $value) {
                    $message .= "{$key}: {$value}\n";
                }
            } else {
                $message .= "Опция не выбрана\n";
            }


        }


        return $message;
    }
}
