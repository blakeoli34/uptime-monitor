<?php
require_once __DIR__ . '/src/Core/Config.php';
require_once __DIR__ . '/src/Core/Database.php';

// Check if monitor ID is provided
if (!isset($argv[1]) || !is_numeric($argv[1])) {
    echo "Usage: php get_monitor_status_checks.php <monitor_id>\n";
    exit(1);
}

$monitorId = (int)$argv[1];

// Initialize database connection
$db = \Core\Database::getInstance();

try {
    // Fetch monitor details first
    $monitorSql = "SELECT name, created_at FROM monitors WHERE id = ?";
    $monitorStmt = $db->query($monitorSql, [$monitorId]);
    $monitor = $monitorStmt->fetch();

    if (!$monitor) {
        echo "Monitor with ID $monitorId not found.\n";
        exit(1);
    }

    // Fetch all status checks for the monitor
    $sql = "SELECT 
                id, 
                status, 
                response_time, 
                error_message, 
                checked_at,
                TIMEDIFF(
                    checked_at, 
                    LAG(checked_at) OVER (ORDER BY checked_at)
                ) as duration_since_last_check
            FROM monitor_logs 
            WHERE monitor_id = ? 
            ORDER BY checked_at ASC";

    $stmt = $db->query($sql, [$monitorId]);
    $logs = $stmt->fetchAll();

    // Print monitor details
    echo "Monitor: {$monitor['name']} (ID: $monitorId)\n";
    echo "Created At: {$monitor['created_at']}\n\n";

    // Print log details
    echo "Status Checks:\n";
    echo str_pad("", 80, "-") . "\n";
    echo str_pad("Timestamp", 25) . 
         str_pad("Status", 10) . 
         str_pad("Response Time", 15) . 
         str_pad("Duration Since Last", 20) . 
         "Error Message\n";
    echo str_pad("", 80, "-") . "\n";

    foreach ($logs as $log) {
        echo str_pad($log['checked_at'], 25) . 
             str_pad($log['status'] ? 'UP' : 'DOWN', 10) . 
             str_pad($log['response_time'] ? $log['response_time'] . 'ms' : 'N/A', 15) . 
             str_pad($log['duration_since_last_check'] ?? 'First Check', 20) . 
             ($log['error_message'] ?? '') . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}