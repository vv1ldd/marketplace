<?php
header('Content-Type: text/plain');

echo "--- Laravel Diagnostics ---\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current directory: " . __DIR__ . "\n";

$basePath = realpath(__DIR__ . '/..');
echo "Base path: $basePath\n\n";

echo "--- Environment ---\n";
echo "APP_URL: " . getenv('APP_URL') . "\n";
echo "APP_KEY: " . (getenv('APP_KEY') ? 'Set (Length: ' . strlen(getenv('APP_KEY')) . ')' : 'NOT SET') . "\n";

echo "\n--- Permissions ---\n";
$folders = [
    '/storage',
    '/storage/logs',
    '/storage/framework',
    '/storage/framework/views',
    '/bootstrap/cache',
];

foreach ($folders as $folder) {
    $path = $basePath . $folder;
    echo "$folder: " . (is_writable($path) ? "Writable" : "NOT WRITABLE") . " (" . substr(sprintf('%o', fileperms($path)), -4) . ")\n";
}

echo "\n--- Database Connection ---\n";
$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT');
$db_name = getenv('DB_DATABASE');
$db_user = getenv('DB_USERNAME');
$db_pass = getenv('DB_PASSWORD');

echo "Attempting to connect to $db_host:$db_port ($db_name)...\n";

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    echo "SUCCESS: Connected to database.\n";
} catch (Exception $e) {
    echo "FAILURE: Could not connect to database. Error: " . $e->getMessage() . "\n";
}

echo "\n--- Log Files ---\n";
$logFile = $basePath . '/storage/logs/laravel.log';
if (file_exists($logFile)) {
    echo "laravel.log exists. Size: " . filesize($logFile) . " bytes.\n";
    echo "Last 10 lines:\n";
    $lines = array_slice(file($logFile), -10);
    echo implode("", $lines);
} else {
    echo "laravel.log DOES NOT EXIST.\n";
    echo "Contents of storage/logs:\n";
    print_r(scandir($basePath . '/storage/logs'));
}

echo "\n--- End of Diagnostics ---\n";
