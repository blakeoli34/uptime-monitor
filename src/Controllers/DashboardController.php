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
        
        $sql = "SELECT 
            COUNT(*) as total_monitors,
            SUM(CASE WHEN ml.status = 1 THEN 1 ELSE 0 END) as monitors_up,
            SUM(CASE WHEN ml.status = 0 THEN 1 ELSE 0 END) as monitors_down
        FROM monitors m
        LEFT JOIN (
            SELECT monitor_id, status
            FROM monitor_logs ml1
            WHERE checked_at = (
                SELECT MAX(checked_at)
                FROM monitor_logs ml2
                WHERE ml2.monitor_id = ml1.monitor_id
            )
        ) ml ON m.id = ml.monitor_id
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
        
        $sql = "SELECT m.name, ml.status, ml.error_message, ml.checked_at 
                FROM monitor_logs ml
                JOIN monitors m ON m.id = ml.monitor_id
                WHERE m.user_id = ? AND ml.status = 0
                ORDER BY ml.checked_at DESC
                LIMIT 5";
        
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
}