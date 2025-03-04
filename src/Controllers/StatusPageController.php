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

    public function formatUptime($input, $allowJustNow = true) {
        // Get timezone
        $timezone = Config::get('timezone') ?: 'America/Detroit';
        
        // First, ensure input is not null to avoid PHP warnings
        if ($input === null) {
            return 'unknown';
        }
        
        // If the input is a timestamp string (but not a numeric string)
        if (is_string($input) && !is_numeric($input)) {
            try {
                // Create DateTime objects with proper timezone
                $now = new \DateTime('now', new \DateTimeZone($timezone));
                $uptime = new \DateTime($input, new \DateTimeZone($timezone));
                
                // Calculate difference in seconds
                $diff = $now->getTimestamp() - $uptime->getTimestamp();
                
                // Format the seconds duration
                return $this->formatUptimeSeconds($diff, $allowJustNow);
            } catch (\Exception $e) {
                error_log("Date parsing error in formatUptime: " . $e->getMessage());
                return "Invalid date";
            }
        }
        // If the input is numeric (either integer or numeric string)
        else if (is_numeric($input)) {
            $seconds = (int)$input;
            return $this->formatUptimeSeconds($seconds, $allowJustNow);
        }
        
        return 'unknown duration';
    }

    private function formatUptimeSeconds($seconds, $allowJustNow = true) {
        if ($seconds < 30 && $allowJustNow) {
            return 'just now';
        }
        
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);
        $months = floor($days / 30);
        $years = floor($months / 12);
        
        if ($years > 0) {
            return $years . ' year' . ($years > 1 ? 's' : '') . ', ' . 
                ($months % 12) . ' month' . (($months % 12) > 1 ? 's' : '');
        }
        if ($months > 0) {
            return $months . ' month' . ($months > 1 ? 's' : '') . ', ' . 
                ($days % 30) . ' day' . (($days % 30) > 1 ? 's' : '');
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


    // Public status page view
    public function show($slug) {
        // Get the configured timezone
        $timezone = Config::get('timezone') ?: 'America/Detroit';
        date_default_timezone_set($timezone);
        
        $sql = "SELECT sp.*, u.username as owner
                FROM status_pages sp
                JOIN users u ON u.id = sp.user_id
                WHERE sp.slug = ? AND sp.is_public = 1";
        
        $page = $this->db->query($sql, [$slug])->fetch();
        
        if (!$page) {
            $this->error('Status page not found', 404);
        }
        
        // Get monitors basic info with their current status
        $sql = "SELECT m.*, 
                ms.current_status as current_status,
                ms.last_check_time as last_checked,
                ms.status_since,
                ms.daily_uptime_percentage as todays_uptime
                FROM monitors m
                JOIN status_page_monitors spm ON spm.monitor_id = m.id
                LEFT JOIN monitor_status ms ON m.id = ms.monitor_id
                WHERE spm.status_page_id = ?
                ORDER BY m.name ASC";

        $monitors = $this->db->query($sql, [$page['id']])->fetchAll();

        foreach ($monitors as &$monitor) {
            // Status since needs to be a valid timestamp
            if ($monitor['status_since']) {
                $dateUtc = new \DateTime($monitor['status_since'], new \DateTimeZone('UTC'));
                $dateUtc->setTimezone(new \DateTimeZone($timezone));
                $monitor['status_since'] = $dateUtc->format('Y-m-d H:i:s');
            } else {
                // If status_since is null, use created_at
                $dateUtc = new \DateTime($monitor['created_at'], new \DateTimeZone('UTC'));
                $dateUtc->setTimezone(new \DateTimeZone($timezone));
                $monitor['status_since'] = $dateUtc->format('Y-m-d H:i:s');
            }
            
            $monitor['up_since'] = $monitor['current_status'] ? $monitor['status_since'] : null;
            $monitor['down_since'] = $monitor['current_status'] ? null : $monitor['status_since'];
        }
        unset($monitor);
        
        // Get daily status for each monitor - much simpler now!
        foreach ($monitors as &$monitor) {
            // Get past 90 days of uptime data
            $sql = "WITH RECURSIVE date_series AS (
                        SELECT CURDATE() - INTERVAL 89 DAY as date
                        UNION ALL
                        SELECT date + INTERVAL 1 DAY FROM date_series WHERE date < CURDATE()
                    )
                    SELECT 
                        ds.date,
                        CASE 
                            WHEN ds.date < ? THEN NULL -- Before monitor creation
                            WHEN ds.date = CURDATE() THEN ms.daily_uptime_percentage
                            ELSE COALESCE(du.uptime_percentage, 100) 
                        END as uptime
                    FROM date_series ds
                    LEFT JOIN daily_uptime du ON ds.date = du.date AND du.monitor_id = ?
                    LEFT JOIN monitor_status ms ON ds.date = CURDATE() AND ms.monitor_id = ?
                    ORDER BY ds.date ASC";
            
            $monitor['daily_status'] = $this->db->query($sql, [
                substr($monitor['created_at'], 0, 10),
                $monitor['id'], 
                $monitor['id']
            ])->fetchAll();
            
            // Calculate overall uptime percentage (average of daily percentages)
            $totalUptime = 0;
            $days = 0;
            
            foreach ($monitor['daily_status'] as $day) {
                // Only include days after monitor creation
                if (isset($day['uptime']) && $day['date'] >= substr($monitor['created_at'], 0, 10)) {
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
                mi.started_at,
                mi.ended_at,
                mi.error_message,
                CASE
                    WHEN mi.ended_at IS NULL THEN TIMESTAMPDIFF(SECOND, mi.started_at, NOW())
                    ELSE mi.duration_seconds
                END as duration_seconds
                FROM monitor_incidents mi
                JOIN monitors m ON m.id = mi.monitor_id
                JOIN status_page_monitors spm ON spm.monitor_id = m.id
                WHERE spm.status_page_id = ?
                AND mi.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY mi.started_at DESC
                LIMIT 30";

        $incidents = $this->db->query($sql, [$page['id']])->fetchAll();

        // Convert incident timestamps to the configured timezone
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

            // Recalculate duration for ongoing incidents
            if (!$incident['ended_at']) {
                $start = new \DateTime($incident['started_at'], new \DateTimeZone($timezone));
                $now = new \DateTime('now', new \DateTimeZone($timezone));
                $incident['duration_seconds'] = $now->getTimestamp() - $start->getTimestamp();
            }
        }
        unset($incident);
        
        // Calculate system status - simplified with the new schema
        $allUp = true;
        $allDown = true;
        
        foreach ($monitors as $monitor) {
            if ($monitor['current_status']) {
                $allDown = false;
            } else {
                $allUp = false;
            }
        }
        
        $partialOutage = !$allUp && !$allDown;
        
        // Extract data for the view
        extract([
            'page' => $page,
            'monitors' => $monitors,
            'incidents' => $incidents,
            'allUp' => $allUp,
            'partialOutage' => $partialOutage,
            'formatUptime' => [$this, 'formatUptime'],
            'pageTitle' => htmlspecialchars($page['name']) . ' - Status Page'
        ]);
        
        // Include the custom view for status pages
        require __DIR__ . '/../Views/status-pages/public.php';
    }
}