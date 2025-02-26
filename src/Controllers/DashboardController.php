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
        $timezone = \Core\Config::get('timezone') ?: 'America/Detroit';
        
        $sql = "WITH incident_groups AS (
            SELECT 
                m.id as monitor_id,
                m.name,
                ml.status,
                ml.checked_at,
                ml.error_message,
                -- Group consecutive incidents by comparing status changes
                SUM(CASE 
                    WHEN ml.status = 0 AND (
                        SELECT COALESCE(prev_ml.status, -1) 
                        FROM monitor_logs prev_ml
                        WHERE prev_ml.monitor_id = m.id
                        AND prev_ml.checked_at < ml.checked_at
                        ORDER BY prev_ml.checked_at DESC
                        LIMIT 1
                    ) = 1 THEN 1
                    ELSE 0
                END) OVER (PARTITION BY m.id ORDER BY ml.checked_at) as incident_group
            FROM monitor_logs ml
            JOIN monitors m ON m.id = ml.monitor_id
            WHERE m.user_id = ?
            AND ml.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY m.id, ml.checked_at
        )
        SELECT 
            name,
            0 as status,
            MIN(CASE WHEN status = 0 THEN error_message END) as error_message,
            MIN(CASE WHEN status = 0 THEN checked_at END) as started_at,
            MIN(CASE WHEN status = 1 AND EXISTS (
                SELECT 1 FROM incident_groups ig2 
                WHERE ig2.monitor_id = incident_groups.monitor_id 
                AND ig2.incident_group = incident_groups.incident_group
                AND ig2.status = 0
                AND ig2.checked_at < incident_groups.checked_at
            ) THEN checked_at END) as ended_at,
            TIMESTAMPDIFF(SECOND, 
                MIN(CASE WHEN status = 0 THEN checked_at END), 
                COALESCE(MIN(CASE WHEN status = 1 AND EXISTS (
                    SELECT 1 FROM incident_groups ig2 
                    WHERE ig2.monitor_id = incident_groups.monitor_id 
                    AND ig2.incident_group = incident_groups.incident_group
                    AND ig2.status = 0
                    AND ig2.checked_at < incident_groups.checked_at
                ) THEN checked_at END), NOW())
            ) as duration_seconds
        FROM incident_groups
        WHERE status IN (0, 1)
        GROUP BY name, monitor_id, incident_group
        HAVING MIN(CASE WHEN status = 0 THEN checked_at END) IS NOT NULL
        ORDER BY started_at DESC
        LIMIT 20";
        
        $incidents = $this->db->query($sql, [$userId])->fetchAll();
        
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
        }
        unset($incident);
        
        return $incidents;
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