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
            'uptimeOverview' => $uptimeOverview
        ]);
    }

    private function getMonitorStats() {
        $userId = $this->auth->getCurrentUser()['id'];
        
        // First, let's debug what's happening with the statuses
        $debugSql = "SELECT m.id, m.name, ml.status, ml.checked_at
                    FROM monitors m
                    LEFT JOIN (
                        SELECT ml.*
                        FROM monitor_logs ml
                        INNER JOIN (
                            SELECT monitor_id, MAX(checked_at) as max_checked_at
                            FROM monitor_logs
                            GROUP BY monitor_id
                        ) latest ON ml.monitor_id = latest.monitor_id AND ml.checked_at = latest.max_checked_at
                    ) ml ON m.id = ml.monitor_id
                    WHERE m.user_id = ?";
        
        $debugResults = $this->db->query($debugSql, [$userId])->fetchAll();
        
        // Log the debug info
        $logger = \Core\Logger::getInstance();
        $logger->info('Debug monitor statuses: ' . json_encode($debugResults));
        
        // Improved query that more reliably determines monitor statuses
        $sql = "SELECT 
            COUNT(m.id) as total_monitors,
            SUM(CASE WHEN latest_logs.status = 1 THEN 1 ELSE 0 END) as monitors_up,
            SUM(CASE WHEN latest_logs.status = 0 OR latest_logs.status IS NULL THEN 1 ELSE 0 END) as monitors_down
        FROM monitors m
        LEFT JOIN (
            SELECT ml.*
            FROM monitor_logs ml
            INNER JOIN (
                SELECT monitor_id, MAX(checked_at) as max_checked_at
                FROM monitor_logs
                GROUP BY monitor_id
            ) latest ON ml.monitor_id = latest.monitor_id AND ml.checked_at = latest.max_checked_at
        ) latest_logs ON m.id = latest_logs.monitor_id
        WHERE m.user_id = ?";
        
        $result = $this->db->query($sql, [$userId])->fetch();
        
        // Log the result
        $logger->info('Monitor stats: ' . json_encode($result));
        
        return [
            'total' => $result['total_monitors'] ?? 0,
            'up' => $result['monitors_up'] ?? 0,
            'down' => $result['monitors_down'] ?? 0
        ];
    }

    private function getRecentIncidents() {
        $userId = $this->auth->getCurrentUser()['id'];
        
        $sql = "SELECT 
                m.name, 
                ml_start.status, 
                ml_start.error_message, 
                ml_start.checked_at as started_at,
                ml_end.checked_at as ended_at,
                TIMESTAMPDIFF(SECOND, ml_start.checked_at, COALESCE(ml_end.checked_at, NOW())) as duration_seconds
            FROM monitor_logs ml_start
            JOIN monitors m ON m.id = ml_start.monitor_id
            LEFT JOIN (
                SELECT monitor_id, MIN(checked_at) as checked_at
                FROM monitor_logs
                WHERE status = 1
                GROUP BY monitor_id, (
                    SELECT COUNT(*) 
                    FROM monitor_logs ml2 
                    WHERE ml2.monitor_id = monitor_logs.monitor_id 
                    AND ml2.status = 0 
                    AND ml2.checked_at < monitor_logs.checked_at
                )
            ) ml_end ON ml_end.monitor_id = ml_start.monitor_id 
                AND ml_end.checked_at > ml_start.checked_at
            WHERE m.user_id = ? 
            AND ml_start.status = 0
            AND ml_start.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY ml_start.checked_at DESC
            LIMIT 20";
        
        return $this->db->query($sql, [$userId])->fetchAll();
    }

    private function getUptimeOverview() {
        $userId = $this->auth->getCurrentUser()['id'];
        
        // Get uptime percentage for last 24 hours for each monitor
        $sql = "SELECT 
                    m.name,
                    COUNT(*) as total_checks,
                    SUM(CASE WHEN ml.status = 1 THEN 1 ELSE 0 END) as successful_checks,
                    (SUM(CASE WHEN ml.status = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as uptime_percentage
                FROM monitors m
                LEFT JOIN monitor_logs ml ON m.id = ml.monitor_id
                WHERE m.user_id = ? 
                AND ml.checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY m.id, m.name";
        
        return $this->db->query($sql, [$userId])->fetchAll();
    }
    
    private function formatUptime($seconds) {
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