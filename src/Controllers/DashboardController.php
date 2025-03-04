<?php
namespace Controllers;

use Core\Database;

class DashboardController extends BaseController {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->auth->requireLogin();
        $this->db = Database::getInstance();
    }

    public function index() {
        // Get monitor statistics
        $stats = $this->getMonitorStats();
        
        // Get recent incidents
        $recentIncidents = $this->getRecentIncidents();
        
        // Get uptime overview
        $uptimeOverview = $this->getUptimeOverview();

        $this->view('dashboard/index', [
            'stats' => $stats,
            'recentIncidents' => $recentIncidents,
            'uptimeOverview' => $uptimeOverview,
            'formatUptime' => [$this, 'formatUptime']
        ]);
    }

    private function getMonitorStats() {
        $userId = $this->auth->getCurrentUser()['id'];
        
        $sql = "SELECT 
            COUNT(m.id) as total_monitors,
            SUM(CASE WHEN ms.current_status = 1 THEN 1 ELSE 0 END) as monitors_up,
            SUM(CASE WHEN ms.current_status = 0 OR ms.current_status IS NULL THEN 1 ELSE 0 END) as monitors_down
        FROM monitors m
        LEFT JOIN monitor_status ms ON m.id = ms.monitor_id
        WHERE m.user_id = ?";
        
        $result = $this->db->query($sql, [$userId])->fetch();
        
        return [
            'total' => $result['total_monitors'] ?? 0,
            'up' => $result['monitors_up'] ?? 0,
            'down' => $result['monitors_down'] ?? 0
        ];
    }
    
    private function getRecentIncidents() {
        $userId = $this->auth->getCurrentUser()['id'];
        $timezone = \Core\Config::get('timezone') ?: 'America/Detroit';
        
        $sql = "SELECT 
            m.name,
            0 as status,
            mi.error_message,
            mi.started_at,
            mi.ended_at,
            mi.duration_seconds  -- Make sure this is cast to integer if needed
        FROM monitor_incidents mi
        JOIN monitors m ON m.id = mi.monitor_id
        WHERE m.user_id = ?
        AND mi.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY mi.started_at DESC
        LIMIT 20";
        
        $incidents = $this->db->query($sql, [$userId])->fetchAll();
        
        // Add debug logging
        error_log("Raw incidents from database: " . json_encode($incidents));
        
        // Convert timestamps to the configured timezone
        foreach ($incidents as &$incident) {
            if ($incident['started_at']) {
                $dateUtc = new \DateTime($incident['started_at'], new \DateTimeZone('UTC'));
                $dateUtc->setTimezone(new \DateTimeZone($timezone));
                $incident['started_at'] = $dateUtc->format('Y-m-d H:i:s');
            }
            
            if ($incident['ended_at']) {
                $dateUtc = new \DateTime($incident['ended_at'], new \DateTimeZone('UTC'));
                $dateUtc->setTimezone(new \DateTimeZone($timezone));
                $incident['ended_at'] = $dateUtc->format('Y-m-d H:i:s');
            }
            
            // Ensure duration_seconds is numeric
            if (isset($incident['duration_seconds'])) {
                // Cast to integer to ensure it's treated as seconds
                $incident['duration_seconds'] = (int)$incident['duration_seconds'];
            }
        }
        
        // Add debug logging after processing
        error_log("Processed incidents: " . json_encode($incidents));
        
        return $incidents;
    }
    
    private function getUptimeOverview() {
        $userId = $this->auth->getCurrentUser()['id'];
        
        // Modified query to ensure it returns fields with expected names
        $sql = "SELECT 
            m.name,
            ms.todays_checks as total_checks,
            ms.todays_successful_checks as successful_checks,
            (
                (ms.todays_successful_checks + COALESCE(du.total_checks * du.uptime_percentage / 100, 0)) /
                NULLIF(ms.todays_checks + COALESCE(du.total_checks, 0), 0) * 100
            ) as uptime_percentage
        FROM monitors m
        JOIN monitor_status ms ON m.id = ms.monitor_id
        LEFT JOIN (
            SELECT monitor_id, uptime_percentage, 100 as total_checks
            FROM daily_uptime
            WHERE date = CURRENT_DATE - INTERVAL 1 DAY
        ) du ON du.monitor_id = m.id
        WHERE m.user_id = ?";
        
        $results = $this->db->query($sql, [$userId])->fetchAll();
        
        // Ensure we have valid values for all results
        foreach ($results as &$row) {
            // Set uptime_percentage to 100 if NULL (no checks performed)
            if ($row['uptime_percentage'] === null) {
                $row['uptime_percentage'] = 100.0;
            }
        }
        
        return $results;
    }
    
    public function formatUptime($seconds) {
        if ($seconds === null) return 'just now';
        
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);
        
        if ($seconds < 30) {
            return 'just now';
        }
        
        if ($days > 0) {
            return $days . ' day' . ($days > 1 ? 's' : '') . ', ' . 
                ($hours % 24) . ' hour' . (($hours % 24) > 1 ? 's' : '');
        }
        
        if ($hours > 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ', ' . 
                ($minutes % 60) . ' minute' . (($minutes % 60) > 1 ? 's' : '');
        }
        
        if ($minutes > 0) {
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        
        return $seconds . ' second' . ($seconds > 1 ? 's' : '');
    }
}