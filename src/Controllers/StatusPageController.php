<?php
namespace Controllers;

use Core\Database;
use Core\Config;

class StatusPageController extends BaseController {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    public function index() {
        $this->auth->requireLogin();
        $userId = $this->auth->getCurrentUser()['id'];
        
        $sql = "SELECT sp.*, 
                (SELECT COUNT(*) FROM status_page_monitors WHERE status_page_id = sp.id) as monitor_count
                FROM status_pages sp 
                WHERE sp.user_id = ? 
                ORDER BY sp.name ASC";
        
        $pages = $this->db->query($sql, [$userId])->fetchAll();
        
        $this->view('status-pages/list', ['pages' => $pages]);
    }

    public function create() {
        $this->auth->requireLogin();
        
        // Get user's monitors for selection
        $userId = $this->auth->getCurrentUser()['id'];
        $sql = "SELECT id, name FROM monitors WHERE user_id = ? ORDER BY name ASC";
        $monitors = $this->db->query($sql, [$userId])->fetchAll();
        
        $this->view('status-pages/edit', [
            'page' => null,
            'monitors' => $monitors,
            'isNew' => true
        ]);
    }

    public function store() {
        if (!\Core\CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token'];
            $this->redirect('/status-pages/add');
            return;
        }
    
        $validator = new \Core\Validator();
        $rules = [
            'name' => ['required', 'min:1', 'max:255'],
            'slug' => ['required', 'slug', 'min:1', 'max:255'],
            'description' => ['max:1000'],
            'is_public' => ['boolean'],
            'custom_domain' => ['max:255'],
            'monitor_ids' => []  // Made optional since it was causing validation failure
        ];
    
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Validation failed',
                'errors' => $validator->getErrors()
            ];
            var_dump($validator->getErrors());
            die();
            $this->redirect('/status-pages/add');
            return;
        }
    
        $data = $validator->sanitize($_POST, [
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'custom_domain' => 'string'
        ]);

        $userId = $this->auth->getCurrentUser()['id'];
    
        try {
            $this->db->getConnection()->beginTransaction();
            
            $sql = "INSERT INTO status_pages (user_id, name, slug, description, is_public, custom_domain) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            $this->db->query($sql, [
                $userId,
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                isset($_POST['is_public']) ? 1 : 0,
                $data['custom_domain'] ?? null
            ]);
            
            $pageId = $this->db->getConnection()->lastInsertId();
            
            // Handle monitor associations
            if (!empty($_POST['monitor_ids'])) {
                $values = array_fill(0, count($_POST['monitor_ids']), "(?, ?)");
                $sql = "INSERT INTO status_page_monitors (status_page_id, monitor_id) VALUES " . 
                       implode(', ', $values);
                
                $params = [];
                foreach ($_POST['monitor_ids'] as $monitorId) {
                    $params[] = $pageId;
                    $params[] = (int)$monitorId;
                }
                
                $this->db->query($sql, $params);
            }
            
            $this->db->getConnection()->commit();
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status page created successfully'];
            $this->redirect('/status-pages');
            
        } catch (\Exception $e) {
            $this->db->getConnection()->rollBack();
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Failed to create status page: ' . $e->getMessage()
            ];
            $this->redirect('/status-pages/add');
        }
    }
    
    public function update($id) {
        if (!\Core\CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token'];
            $this->redirect("/status-pages/$id/edit");
            return;
        }
    
        $validator = new \Core\Validator();
        $rules = [
            'name' => ['required', 'min:1', 'max:255'],
            'slug' => ['required', 'slug', 'min:1', 'max:255'],
            'description' => ['max:1000'],
            'is_public' => ['boolean'],
            'custom_domain' => ['max:255'],
            'monitor_ids' => []  // Made optional since it was causing validation failure
        ];
    
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Validation failed',
                'errors' => $validator->getErrors()
            ];
            $this->redirect("/status-pages/$id/edit");
            return;
        }
    
        $data = $validator->sanitize($_POST, [
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'custom_domain' => 'string'
        ]);

        $userId = $this->auth->getCurrentUser()['id'];
    
        try {
            $this->db->getConnection()->beginTransaction();
            
            $sql = "UPDATE status_pages 
                    SET name = ?, slug = ?, description = ?, is_public = ?, custom_domain = ?
                    WHERE id = ? AND user_id = ?";

            $this->db->query($sql, [
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                isset($_POST['is_public']) ? 1 : 0,
                $data['custom_domain'] ?? null,
                $id,
                $userId
            ]);
            
            // Update monitor associations
            $sql = "DELETE FROM status_page_monitors WHERE status_page_id = ?";
            $this->db->query($sql, [$id]);
            
            if (!empty($_POST['monitor_ids'])) {
                $values = array_fill(0, count($_POST['monitor_ids']), "(?, ?)");
                $sql = "INSERT INTO status_page_monitors (status_page_id, monitor_id) VALUES " . 
                       implode(', ', $values);
                
                $params = [];
                foreach ($_POST['monitor_ids'] as $monitorId) {
                    $params[] = $id;
                    $params[] = (int)$monitorId;
                }
                
                $this->db->query($sql, $params);
            }
            
            $this->db->getConnection()->commit();
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status page updated successfully'];
            $this->redirect('/status-pages');
            
        } catch (\Exception $e) {
            $this->db->getConnection()->rollBack();
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Failed to update status page: ' . $e->getMessage()
            ];
            $this->redirect("/status-pages/$id/edit");
        }
    }

    public function edit($id) {
        $this->auth->requireLogin();
        $userId = $this->auth->getCurrentUser()['id'];
        
        // Get status page
        $sql = "SELECT * FROM status_pages WHERE id = ? AND user_id = ?";
        $page = $this->db->query($sql, [$id, $userId])->fetch();
        
        if (!$page) {
            $this->error('Status page not found', 404);
        }
        
        // Get all user's monitors
        $sql = "SELECT id, name FROM monitors WHERE user_id = ? ORDER BY name ASC";
        $monitors = $this->db->query($sql, [$userId])->fetchAll();
        
        // Get selected monitors
        $sql = "SELECT monitor_id FROM status_page_monitors WHERE status_page_id = ?";
        $selectedMonitors = $this->db->query($sql, [$id])->fetchAll(\PDO::FETCH_COLUMN);
        
        $this->view('status-pages/edit', [
            'page' => $page,
            'monitors' => $monitors,
            'selectedMonitors' => $selectedMonitors,
            'isNew' => false,
            'statusPageUrl' => "status." . $page['custom_domain']
        ]);
    }


    public function delete($id) {
        $this->auth->requireLogin();
        $userId = $this->auth->getCurrentUser()['id'];
        
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Delete monitor associations
            $sql = "DELETE spm FROM status_page_monitors spm
                    JOIN status_pages sp ON sp.id = spm.status_page_id
                    WHERE sp.id = ? AND sp.user_id = ?";
            $this->db->query($sql, [$id, $userId]);
            
            // Delete status page
            $sql = "DELETE FROM status_pages WHERE id = ? AND user_id = ?";
            $result = $this->db->query($sql, [$id, $userId]);
            
            $this->db->getConnection()->commit();
            
            if ($result->rowCount() > 0) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Status page deleted successfully.'
                ];
            } else {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Status page not found.'
                ];
            }
        } catch (\Exception $e) {
            $this->db->getConnection()->rollBack();
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Failed to delete status page: ' . $e->getMessage()
            ];
        }
        
        $this->redirect('/status-pages');
    }

    public function formatUptime($timestamp) {
        if (!$timestamp) return 'just now';
        
        // Create DateTime objects
        $now = new \DateTime();
        $uptime = new \DateTime($timestamp);
        
        // Calculate difference
        $diff = $now->diff($uptime);
        
        // Only return "just now" if truly just now (less than 30 seconds)
        if ($diff->days == 0 && $diff->h == 0 && $diff->i == 0 && $diff->s < 30) {
            return 'just now';
        }
    
        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ', ' . 
                $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ', ' . 
                $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ', ' . 
                $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ', ' . 
                $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        
        return $diff->s . ' second' . ($diff->s > 1 ? 's' : '');
    }


    // Public status page view
    public function show($slug) {
        date_default_timezone_set(Config::get('timezone') ?: 'America/Detroit');
        
        $sql = "SELECT sp.*, u.username as owner
                FROM status_pages sp
                JOIN users u ON u.id = sp.user_id
                WHERE sp.slug = ? AND sp.is_public = 1";
        
        $page = $this->db->query($sql, [$slug])->fetch();
        
        if (!$page) {
            $this->error('Status page not found', 404);
        }
     
        // Get monitors basic info
        $sql = "SELECT m.*, 
                COALESCE(ml.status, 0) as current_status,
                ml.checked_at as last_checked,
                (
                    SELECT checked_at 
                    FROM monitor_logs t1
                    WHERE t1.monitor_id = m.id
                    AND t1.status = COALESCE(ml.status, 0)
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM monitor_logs t2
                        WHERE t2.monitor_id = t1.monitor_id
                        AND t2.checked_at > t1.checked_at
                        AND t2.status != t1.status
                    )
                    ORDER BY t1.checked_at ASC
                    LIMIT 1
                ) as status_since
                FROM monitors m
                JOIN status_page_monitors spm ON spm.monitor_id = m.id
                LEFT JOIN (
                    SELECT ml1.monitor_id, ml1.status, ml1.checked_at
                    FROM monitor_logs ml1
                    INNER JOIN (
                        SELECT monitor_id, MAX(checked_at) as max_checked_at
                        FROM monitor_logs
                        GROUP BY monitor_id
                    ) ml2 ON ml1.monitor_id = ml2.monitor_id AND ml1.checked_at = ml2.max_checked_at
                ) ml ON m.id = ml.monitor_id
                WHERE spm.status_page_id = ?
                ORDER BY m.name ASC";
     
        $monitors = $this->db->query($sql, [$page['id']])->fetchAll();
    
        foreach ($monitors as &$monitor) {
            $monitor['up_since'] = $monitor['current_status'] ? $monitor['status_since'] : null;
            $monitor['down_since'] = $monitor['current_status'] ? null : $monitor['status_since'];
        }
        unset($monitor);
     
        // Get daily status for each monitor
        foreach ($monitors as &$monitor) {
            $sql = "WITH RECURSIVE dates AS (
                SELECT CURDATE() - INTERVAL n DAY as date
                FROM (
                    SELECT a.N + b.N * 10 + c.N * 100 AS n
                    FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                         (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b,
                         (SELECT 0 AS N) c
                    WHERE a.N + b.N * 10 + c.N * 100 < 90
                ) numbers
            )
            SELECT 
                d.date,
                CASE 
                    WHEN d.date < DATE(?) THEN NULL
                    WHEN ml.total_checks IS NULL THEN 100
                    ELSE (ml.successful_checks * 100.0 / ml.total_checks)
                END as uptime
            FROM dates d
            LEFT JOIN (
                SELECT 
                    DATE(checked_at) as check_date,
                    COUNT(*) as total_checks,
                    SUM(status) as successful_checks
                FROM monitor_logs
                WHERE monitor_id = ?
                GROUP BY DATE(checked_at)
            ) ml ON ml.check_date = d.date
            ORDER BY d.date ASC";
     
            $monitor['daily_status'] = $this->db->query($sql, [$monitor['created_at'], $monitor['id']])->fetchAll();
     
            // Calculate uptime percentage
            $activeChecks = array_filter($monitor['daily_status'], function($day) use ($monitor) {
                return $day['date'] >= substr($monitor['created_at'], 0, 10);
            });
     
            $totalUptime = 0;
            $days = 0;
     
            foreach ($activeChecks as $day) {
                if (isset($day['uptime'])) {
                    $totalUptime += $day['uptime'];
                    $days++;
                }
            }
     
            $monitor['total_uptime'] = $days > 0 ? $totalUptime / $days : 100;
        }
    
        unset($monitor);
     
        // Get incident history
        $sql = "SELECT 
            m.name as monitor_name,
            ml_start.checked_at as started_at,
            TIMEDIFF(ml_end.checked_at, ml_start.checked_at) as duration
        FROM monitor_logs ml_start
        JOIN monitors m ON m.id = ml_start.monitor_id
        JOIN monitor_logs ml_end ON ml_end.monitor_id = ml_start.monitor_id
        JOIN status_page_monitors spm ON spm.monitor_id = m.id
        WHERE ml_start.status = 0 
        AND ml_start.checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND spm.status_page_id = ?
        AND ml_end.checked_at = (
            SELECT MIN(checked_at) 
            FROM monitor_logs 
            WHERE monitor_id = ml_start.monitor_id 
            AND checked_at > ml_start.checked_at 
            AND status = 1
        )
        ORDER BY ml_start.checked_at DESC";
    
        $incidents = $this->db->query($sql, [$page['id']])->fetchAll();
        
        // Calculate system status
        $allUp = true;
        $allDown = true;
        
        foreach ($monitors as $monitor) {
            if ($monitor['current_status']) {
                $allDown = false;
            } else {
                $allUp = false;
            }
        }
        unset($monitor);
        
        $partialOutage = !$allUp && !$allDown;
    
        
        $this->view('status-pages/public', [
            'page' => $page,
            'monitors' => $monitors,
            'incidents' => $incidents,
            'allUp' => $allUp,
            'partialOutage' => $partialOutage,
            'formatUptime' => [$this, 'formatUptime']
        ]);
    }
     
     private function getLastStatusChange($monitorId, $status) {
        $sql = "SELECT checked_at 
                FROM monitor_logs 
                WHERE monitor_id = ? AND status = ? 
                ORDER BY checked_at DESC LIMIT 1";
        
        $result = $this->db->query($sql, [$monitorId, $status])->fetch();
        return $result ? $result['checked_at'] : null;
     }
}