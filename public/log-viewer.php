<?php
$logFilePath = __DIR__ . '/../storage/logs/laravel.log';

echo "<h1>Laravel Error Logs</h1>";

if (file_exists($logFilePath)) {
    $lines = file($logFilePath);
    if ($lines === false) {
        echo "<p>Could not read the log file.</p>";
    } else {
        echo "<pre style='background: #111; color: #0f0; padding: 10px; overflow-x: auto;'>";
        // Show the last 200 lines
        $recentLines = array_slice($lines, -200);
        foreach ($recentLines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>";
    }
} else {
    echo "<p>Log file does not exist: " . htmlspecialchars($logFilePath) . "</p>";
}
