<?php

namespace App\Helpers;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\PlayStation\PlayStationAlt;

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

            $message = "Новая SL1 транзакция {$order->transactionReference()}\n";
            $message .= "Статус: {$order->status}\n";
            $message .= "-------\n";

            $message .= "Данные покупателя:\n";
            $message .= "ФИО: " . data_get($client_info, 'lastName', '-') . " " . data_get($client_info, 'firstName', '-') . " " . data_get($client_info, 'middleName', '-') . "\n";
            $message .= "Телефон: " . data_get($client_info, 'phone', '-') . "\n";
            $message .= "Почта: " . data_get($client_info, 'email', '-') . "\n";
            $message .= "-------\n";

            $message .= "Товары:\n";
            foreach ($items as $item) {

                $product = PlayStationAlt::where('sku', $item['sku'])->where('region_id', '063101db-9ac0-4e48-a948-29fe7e3f8dec')->first();

                $woo_price_try = (($product?->woo_price_try ?? 0) / 100) * $item['count'];

                $message .= "{$item['sku']} - {$item['count']} шт. - {$woo_price_try} лир. итого \n";

                $message .= "Тип формы: {$product?->typeForm?->name} \n";
            }
        } else if ($status === 'send_form') {

            $message .= "Активация по SL1 транзакции {$order_item->transactionReference()}\n";
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
                $message .= "Выбранная опция:\n";

                foreach ($option as $key => $value) {
                    $message .= "{$key}: {$value}\n";
                }
            } else {
                $message .= "Опция не выбрана\n";
            }

            $message .= "Тип формы: {$order_item?->typeForm?->name} \n";
        }


        return $message;
    }
}
