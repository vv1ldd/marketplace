<?php

include 'vendor/autoload.php';
$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order\Order;

$order = Order::find(1197); // Ищем по системному ID

if (!$order) {
    die("Order WITH ID 1197 not found\n");
}

echo "--- BEFORE UPDATE ---\n";
echo "Current Info: " . json_encode($order->client_info, JSON_UNESCAPED_UNICODE) . "\n\n";

// Новые данные от клиента (Иван Иванов)
$newData = [
    'first_name' => 'Иван',
    'last_name' => 'Иванов',
    'email' => 'ivan@test.ru',
    'phone' => '+79001112233',
    'type_id' => 1
];

// Наша новая логика объединения
$order->update([
    'client_info' => array_merge($order->client_info ?? [], $newData),
    'code_activated' => true
]);

echo "--- AFTER UPDATE ---\n";
$updatedOrder = Order::find(1197);
echo "Updated Info: " . json_encode($updatedOrder->client_info, JSON_UNESCAPED_UNICODE) . "\n";
echo "Active Doc Standard (Snake Case): " . ($updatedOrder->client_info['first_name'] ?? 'FAIL') . " " . ($updatedOrder->client_info['last_name'] ?? 'FAIL') . "\n";
