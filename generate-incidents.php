<?php
/**
 * This script generates test downtime incidents for monitors
 * 
 * Usage: php generate-incidents.php [monitor_id] [number_of_incidents]
 * If no monitor_id is provided, it will generate incidents for all monitors
 * If no number_of_incidents is provided, it will default to 5
 */

require_once __DIR__ . '/src/Core/Config.php';
require_once __DIR__ . '/src/Core/Database.php';

use Core\Config;
use Core\Database;

// Initialize configuration and database
Config::load();
$db = Database::getInstance();

// Parse command line arguments
$monitorId = isset($argv[1]) ? (int)$argv[1] : null;
$numIncidents = isset($argv[2]) ? (int)$argv[2] : 5;

// Get monitors
if ($monitorId) {
    $sql = "SELECT id, name FROM monitors WHERE id = ?";
    $monitors = $db->query($sql, [$monitorId])->fetchAll();
    if (empty($monitors)) {
        die("Monitor with ID $monitorId not found\n");
    }
} else {
    $sql = "SELECT id, name FROM monitors";
    $monitors = $db->query($sql)->fetchAll();
    if (empty($monitors)) {
        die("No monitors found\n");
    }
}

echo "Found " . count($monitors) . " monitor(s)\n";

// Generate incidents
foreach ($monitors as $monitor) {
    echo "Generating $numIncidents incidents for monitor {$monitor['name']} (ID: {$monitor['id']})\n";
    
    for ($i = 1; $i <= $numIncidents; $i++) {
        // Random start time within the last 30 days
        $daysAgo = rand(1, 30);
        $startTime = new DateTime("-$daysAgo days");
        
        // Randomize the time
        $startTime->modify("-" . rand(0, 23) . " hours");
        $startTime->modify("-" . rand(0, 59) . " minutes");
        $startTime->modify("-" . rand(0, 59) . " seconds");
        
        // Random duration between 1 minute and 6 hours
        $durationMinutes = rand(1, 360);
        $endTime = clone $startTime;
        $endTime->modify("+$durationMinutes minutes");
        
        // Random error message
        $errors = [
            "Connection refused",
            "Timeout waiting for response",
            "SSL certificate verification failed",
            "DNS lookup failed",
            "HTTP 500 Internal Server Error",
            "HTTP 503 Service Unavailable",
            "TCP connection failed",
            "Response time exceeded threshold",
            "Invalid response received",
            "No route to host"
        ];
        $errorMessage = $errors[array_rand($errors)];
        
        // Insert downtime incident (status = 0)
        $sql = "INSERT INTO monitor_logs (monitor_id, status, error_message, checked_at) 
                VALUES (?, 0, ?, ?)";
        $db->query($sql, [
            $monitor['id'],
            $errorMessage,
            $startTime->format('Y-m-d H:i:s')
        ]);
        
        // Insert recovery (status = 1)
        $sql = "INSERT INTO monitor_logs (monitor_id, status, response_time, checked_at) 
                VALUES (?, 1, ?, ?)";
        $db->query($sql, [
            $monitor['id'],
            rand(50, 2000), // Random response time between 50ms and 2000ms
            $endTime->format('Y-m-d H:i:s')
        ]);
        
        echo "  Created incident #{$i}: Down at {$startTime->format('Y-m-d H:i:s')}, recovered at {$endTime->format('Y-m-d H:i:s')} (Duration: $durationMinutes minutes)\n";
    }
    
    // Also create one ongoing incident (no recovery)
    $startTime = new DateTime("-" . rand(1, 12) . " hours");
    $sql = "INSERT INTO monitor_logs (monitor_id, status, error_message, checked_at) 
            VALUES (?, 0, ?, ?)";
    $db->query($sql, [
        $monitor['id'],
        "Ongoing incident - " . $errors[array_rand($errors)],
        $startTime->format('Y-m-d H:i:s')
    ]);
    
    echo "  Created ongoing incident: Down since {$startTime->format('Y-m-d H:i:s')}\n";
}

echo "Done! Generated " . ($numIncidents + 1) . " incidents for each monitor\n";