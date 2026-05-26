import subprocess
import json

# Command to execute php code under artisan to simulate an authenticated merchant deposit
php_code = """
$user = \\App\\Models\\User::whereHas('legalEntities')->first();
if (!$user) {
    echo json_encode(['error' => 'No merchant user found']);
    exit;
}
\\Illuminate\\Support\\Facades\\Auth::login($user);

$domain = config('app.domain') ?: 'meanly.test';

// 1. Check balance before
$requestBefore = \\Illuminate\\Http\\Request::create("https://{$domain}/partner/dashboard/finance/data", "GET");
$responseBefore = json_decode(app()->handle($requestBefore)->getContent(), true);
$balanceBefore = $responseBefore['balances']['available'] ?? 0;

// 2. Perform deposit simulation of 15000 RUB
$depositRequest = \\Illuminate\\Http\\Request::create("https://{$domain}/partner/dashboard/finance/deposit", "POST", [
    'amount' => 15000
]);

$controller = app(\\App\\Http\Controllers\\PartnerDashboardController::class);
$response = $controller->simulateDeposit($depositRequest);

// 3. Check balance after
$requestAfter = \\Illuminate\\Http\\Request::create("https://{$domain}/partner/dashboard/finance/data", "GET");
$responseAfter = json_decode(app()->handle($requestAfter)->getContent(), true);
$balanceAfter = $responseAfter['balances']['available'] ?? 0;

echo json_encode([
    'deposit_status' => $response->getStatusCode(),
    'deposit_response' => json_decode($response->getContent(), true),
    'balance_before' => $balanceBefore,
    'balance_after' => $balanceAfter,
    'difference' => $balanceAfter - $balanceBefore
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"""

result = subprocess.run(
    ["php", "artisan", "tinker"],
    input=php_code,
    text=True,
    capture_output=True
)

print("--- Laravel Deposit Simulation Verification ---")
print(result.stdout)
