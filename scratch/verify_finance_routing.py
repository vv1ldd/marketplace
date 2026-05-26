import subprocess
import json

# Command to execute php code under artisan to simulate an authenticated merchant request
php_code = """
$user = \\App\\Models\\User::whereHas('legalEntities')->first();
if (!$user) {
    echo json_encode(['error' => 'No merchant user found']);
    exit;
}
\\Illuminate\\Support\\Facades\\Auth::login($user);

$domain = config('app.domain') ?: 'meanly.test';
$request = \\Illuminate\\Http\\Request::create("https://{$domain}/partner/dashboard/finance/data", "GET");
$response = app()->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'content' => json_decode($response->getContent(), true)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"""

result = subprocess.run(
    ["php", "artisan", "tinker"],
    input=php_code,
    text=True,
    capture_output=True
)

print("--- Laravel Response Verification ---")
print(result.stdout)
