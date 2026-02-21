<?php
header('Content-Type: text/plain');

echo "--- Laravel Diagnostics (Standalone) ---\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current directory: " . __DIR__ . "\n";

$basePath = realpath(__DIR__ . '/..');
echo "Base path: $basePath\n\n";

echo "--- Environment Variables (from PHP) ---\n";
$vars = ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_DOMAIN', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'TRUSTED_HOSTS', 'SESSION_DRIVER', 'CACHE_STORE'];
foreach ($vars as $var) {
    $val = getenv($var);
    echo "$var: " . ($val !== false ? "'$val'" : "NOT SET") . "\n";
}

echo "\n--- Domain Check ---\n";
$host = $_SERVER['HTTP_HOST'] ?? 'unknown';
$trusted = getenv('TRUSTED_HOSTS');
$trusted_array = explode(',', $trusted);
echo "Current HTTP_HOST: '$host'\n";
if (in_array($host, $trusted_array)) {
    echo "SUCCESS: Host is in trusted list.\n";
} else {
    echo "WARNING: Host '$host' is NOT in TRUSTED_HOSTS.\n";
}

echo "\n--- Key Check ---\n";
$key = getenv('APP_KEY');
echo "APP_KEY: " . ($key ? "Set (length: " . strlen($key) . ")" : "NOT SET") . "\n";

echo "\n--- Permissions ---\n";
$folders = ['/storage', '/storage/logs', '/storage/framework/views', '/bootstrap/cache'];
foreach ($folders as $folder) {
    $path = $basePath . $folder;
    echo "$folder: " . (is_writable($path) ? "Writable" : "NOT WRITABLE") . "\n";
}

echo "\n--- Database Ping ---\n";
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_DATABASE');
$db_user = getenv('DB_USERNAME');
$db_pass = getenv('DB_PASSWORD');

if ($db_host && $db_name) {
    echo "Attempting to connect to $db_host ($db_name)...\n";
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        echo "SUCCESS: Database connection established.\n";
    } catch (Exception $e) {
        echo "FAILURE: Database error: " . $e->getMessage() . "\n";
    }
}

echo "\n--- End of Diagnostics ---\n";
