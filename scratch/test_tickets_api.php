<?php

use Illuminate\Support\Facades\Auth;
use App\Models\Seller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Http\Controllers\PartnerDashboardController;
use Illuminate\Http\Request;

// 1. Boot up Laravel environment
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$seller = Seller::where('email', 'admin@admin.com')->first();
if (!$seller) {
    $seller = Seller::first();
}

if (!$seller) {
    echo "❌ [ERROR] No seller found in database!\n";
    exit(1);
}

Auth::login($seller);
echo "\n👤 [TEST] Operating as partner seller: {$seller->email} (ID: {$seller->id})\n";

$legalEntity = $seller->legalEntities()->first();
if (!$legalEntity) {
    echo "❌ [ERROR] Legal entity not found for seller!\n";
    exit(1);
}

$shop = \App\Models\Shop::where('legal_entity_id', $legalEntity->id)->first();
if (!$shop) {
    echo "❌ [ERROR] No registered shop found for this legal entity!\n";
    exit(1);
}
$shopId = $shop->id;
echo "🏪 [TEST] Resolved Shop ID #{$shopId} (Name: {$shop->name} | Domain: {$shop->domain})\n";

$controller = new PartnerDashboardController();

// 2. Fetch Tickets list via dynamic AJAX handler
echo "\n🎫 [TEST] Invoking getTicketsData (status: all, search: '')...\n";
$request = Request::create('/merchant/dashboard/tickets/data', 'GET', [
    'status' => 'all',
    'search' => ''
]);
$response = $controller->getTicketsData($request);
$data = json_decode($response->getContent(), true);

if (isset($data['success']) && $data['success']) {
    echo "✅ [SUCCESS] getTicketsData returned successfully! Total count: " . $data['total'] . "\n";
    foreach ($data['tickets'] as $t) {
        echo "   👉 Ticket ID #{$t['id']} | Subject: {$t['subject']} | Status: {$t['status']} | Updated: {$t['updated_at']}\n";
    }
} else {
    echo "❌ [ERROR] getTicketsData failed!\n";
    var_dump($response->getContent());
    exit(1);
}

// 3. Create a brand new ticket via dynamic creation handler
echo "\n✨ [TEST] Spawning a brand new support ticket via createTicket endpoint...\n";
$createReq = Request::create('/merchant/dashboard/tickets/create', 'POST', [
    'subject' => 'Тестовое обращение B2B API',
    'priority' => 'medium',
    'message' => 'Добрый день! Это автоматический тест безопасности зашифрованных каналов связи.',
    'shop_id' => $shopId
]);

$createRes = $controller->createTicket($createReq);
$createData = json_decode($createRes->getContent(), true);

if (isset($createData['success']) && $createData['success']) {
    $newTicketId = $createData['ticket_id'];
    echo "✅ [SUCCESS] Support ticket created successfully! ID: #{$newTicketId}\n";
} else {
    echo "❌ [ERROR] Ticket creation failed!\n";
    var_dump($createRes->getContent());
    exit(1);
}

// 4. Load ticket details and verify decryption
echo "\n🔒 [TEST] Loading conversation details for Ticket ID #{$newTicketId} (verifying vaulted decrypt)...\n";
$detailsRes = $controller->getTicketDetails($newTicketId);
$detailsData = json_decode($detailsRes->getContent(), true);

if (isset($detailsData['success']) && $detailsData['success']) {
    echo "✅ [SUCCESS] Details loaded! Subject: {$detailsData['ticket']['subject']}\n";
    foreach ($detailsData['messages'] as $m) {
        echo "   💬 [{$m['sender']}] ({$m['created_at']}): {$m['message']}\n";
    }
} else {
    echo "❌ [ERROR] Loading ticket details failed!\n";
    var_dump($detailsRes->getContent());
    exit(1);
}

// 5. Reply to the ticket
echo "\n✍️ [TEST] Appending a follow-up answer reply to the ticket...\n";
$replyReq = Request::create("/merchant/dashboard/tickets/{$newTicketId}/reply", 'POST', [
    'message' => 'Второй тестовый ответ на зашифрованный тикет.'
]);
$replyRes = $controller->replyToTicket($replyReq, $newTicketId);
$replyData = json_decode($replyRes->getContent(), true);

if (isset($replyData['success']) && $replyData['success']) {
    echo "✅ [SUCCESS] Reply sent! Message content: " . $replyData['message']['message'] . "\n";
} else {
    echo "❌ [ERROR] Reply request failed!\n";
    var_dump($replyRes->getContent());
    exit(1);
}

// 6. Final verification load
echo "\n🏁 [TEST] Invoking final detail check to verify all messages exist...\n";
$finalRes = $controller->getTicketDetails($newTicketId);
$finalData = json_decode($finalRes->getContent(), true);

if (isset($finalData['success']) && $finalData['success']) {
    echo "✅ [SUCCESS] Final message list (Total messages: " . count($finalData['messages']) . "):\n";
    foreach ($finalData['messages'] as $m) {
        echo "   💬 [{$m['sender']}] ({$m['created_at']}): {$m['message']}\n";
    }
}

// Cleanup the test ticket to keep DB fresh
TicketMessage::where('ticket_id', $newTicketId)->delete();
Ticket::where('id', $newTicketId)->delete();
echo "\n🧹 [CLEANUP] Deleted test Ticket ID #{$newTicketId}.\n";
echo "🏆 [FINISH] B2B Support Tickets integration tests completed perfectly!\n";
