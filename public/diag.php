<?php
header('Content-Type: text/plain');

echo "--- Laravel Diagnostics (Standalone) ---\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current directory: " . __DIR__ . "\n";

$basePath = realpath(__DIR__ . '/..');
echo "Base path: $basePath\n\n";

echo "--- Environment Variables (from PHP) ---\n";
$vars = ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_DOMAIN', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'TRUSTED_HOSTS'];
foreach ($vars as $var) {
    $val = getenv($var);
    echo "$var: " . ($val !== false ? "'$val'" : "NOT SET") . "\n";
}

echo "\n--- Domain Check ---\n";
$host = $_SERVER['HTTP_HOST'] ?? 'unknown';
$trusted = getenv('TRUSTED_HOSTS');
$trusted_array = explode(',', $trusted);
echo "Current HTTP_HOST: '$host'\n";
echo "TRUSTED_HOSTS: '$trusted'\n";
if (in_array($host, $trusted_array)) {
    echo "SUCCESS: Host is in trusted list.\n";
} else {
    echo "WARNING: Host '$host' is NOT in TRUSTED_HOSTS. This will cause 403 or 500 errors.\n";
}

echo "\n--- Key Check ---\n";
$key = getenv('APP_KEY');
if (!$key) {
    echo "CRITICAL: APP_KEY is NOT SET. Laravel will return 500.\n";
} else {
    echo "APP_KEY is set (length: " . strlen($key) . ")\n";
}

echo "\n--- Permissions ---\n";
$folders = [
    '/storage',
    '/storage/logs',
    '/storage/framework/views',
    '/bootstrap/cache',
];

foreach ($folders as $folder) {
    $path = $basePath . $folder;
    if (file_exists($path)) {
        echo "$folder: " . (is_writable($path) ? "Writable" : "NOT WRITABLE") . " (" . substr(sprintf('%o', fileperms($path)), -4) . ")\n";
    } else {
        echo "$folder: DOES NOT EXIST\n";
    }
}

echo "\n--- Database Ping ---\n";
$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_DATABASE');
$db_user = getenv('DB_USERNAME');
$db_pass = getenv('DB_PASSWORD');

if ($db_host && $db_name) {
    echo "Attempting to connect to $db_host:$db_port ($db_name)...\n";
    try {
        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        echo "SUCCESS: Database connection established.\n";
    } catch (Exception $e) {
        echo "FAILURE: Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "SKIPPED: Database env variables missing.\n";
}

echo "\n--- End of Diagnostics ---\n";
