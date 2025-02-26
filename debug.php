<?php
// Save this as debug.php in your project root

require_once __DIR__ . '/src/Core/Config.php';
require_once __DIR__ . '/src/Core/Database.php';

// Initialize database
\Core\Config::load();
$db = \Core\Database::getInstance();

// Get all monitors
$monitors = $db->query("SELECT id, name, url, type FROM monitors")->fetchAll();

echo "=== MONITORS ===\n";
foreach ($monitors as $monitor) {
    echo "ID: {$monitor['id']}, Name: {$monitor['name']}, URL: {$monitor['url']}, Type: {$monitor['type']}\n";
    
    // Get latest log entry
    $log = $db->query(
        "SELECT status, error_message, checked_at, response_time 
         FROM monitor_logs 
         WHERE monitor_id = ? 
         ORDER BY checked_at DESC LIMIT 1", 
        [$monitor['id']]
    )->fetch();
    
    if ($log) {
        echo "  Latest status: " . ($log['status'] ? "UP" : "DOWN") . "\n";
        echo "  Last checked: {$log['checked_at']}\n";
        echo "  Response time: {$log['response_time']}ms\n";
        echo "  Error message: " . ($log['error_message'] ?: "None") . "\n";
    } else {
        echo "  No logs found for this monitor.\n";
    }
    
    // Get all status entries for the last hour
    $logs = $db->query(
        "SELECT status, error_message, checked_at 
         FROM monitor_logs 
         WHERE monitor_id = ? 
         AND checked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         ORDER BY checked_at DESC", 
        [$monitor['id']]
    )->fetchAll();
    
    echo "  Recent log entries: " . count($logs) . "\n";
    foreach ($logs as $i => $entry) {
        if ($i < 5) { // Show just the 5 most recent entries
            echo "    {$entry['checked_at']}: " . ($entry['status'] ? "UP" : "DOWN") . 
                 ($entry['error_message'] ? " - {$entry['error_message']}" : "") . "\n";
        }
    }
    echo "\n";
}

// Check if monitor service is running
$isServiceRunning = false;
try {
    $ch = curl_init('http://localhost:3000/api/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $isServiceRunning = true;
        echo "Monitor service is RUNNING\n";
        echo "Health check response: $result\n";
    } else {
        echo "Monitor service returned status $httpCode\n";
    }
} catch (Exception $e) {
    echo "Failed to check monitor service: " . $e->getMessage() . "\n";
}

if (!$isServiceRunning) {
    echo "WARNING: Monitor service does not appear to be running!\n";
}