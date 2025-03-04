<?php
namespace Controllers;

use Core\Database;

class MonitorController extends BaseController {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->auth->requireLogin();
        $this->db = Database::getInstance();
    }

    public function index() {
        $userId = $this->auth->getCurrentUser()['id'];
        
        // Much simpler query now
        $sql = "SELECT m.*,
                ms.current_status,
                ms.last_response_time,
                ms.last_check_time as last_checked,
                ms.last_error_message as latest_error
            FROM monitors m 
            LEFT JOIN monitor_status ms ON m.id = ms.monitor_id
            WHERE m.user_id = ?
            ORDER BY m.name ASC";
        
        $monitors = $this->db->query($sql, [$userId])->fetchAll();
        
        $this->view('monitors/list', ['monitors' => $monitors]);
    }

    public function create() {
        $this->view('monitors/edit', [
            'monitor' => null,
            'isNew' => true
        ]);
    }

    public function store() {
        if (!\Core\CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token'];
            $this->redirect('/monitors/add');
            return;
        }
    
        $validator = new \Core\Validator();
        $rules = [
            'name' => ['required', 'min:1', 'max:255'],
            'type' => ['required'],
            'interval_seconds' => ['required', 'numeric'],
            'webhook_url' => ['url', 'max:255']
        ];

        if ($_POST['type'] === 'http') {
            $rules['url'] = ['required', 'url', 'max:255'];
        } else {
            $rules['url'] = ['required', 'max:255'];
            $rules['port'] = ['required', 'port'];
        }
    
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Validation failed',
                'errors' => $validator->getErrors()
            ];
            $this->redirect('/monitors/add');
            return;
        }
    
        $data = $validator->sanitize($_POST, [
            'name' => 'string',
            'url' => 'url',
            'type' => 'string',
            'webhook_url' => 'url',
            'interval_seconds' => 'int',
            'port' => 'int'
        ]);
    
        try {
            $sql = "INSERT INTO monitors (user_id, name, url, type, interval_seconds, port, webhook_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [
                $this->auth->getCurrentUser()['id'],
                $data['name'],
                $data['url'],
                $data['type'],
                $data['interval_seconds'],
                $data['port'] ?? null,
                $data['webhook_url'] ?? null
            ]);
    
            $monitorId = $this->db->getConnection()->lastInsertId();
            $this->startMonitor($monitorId);
    
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Monitor created successfully'];
            $this->redirect('/monitors');
            
        } catch (\Exception $e) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Failed to create monitor: ' . $e->getMessage()
            ];
            $this->redirect('/monitors/add');
        }
    }
    
    public function update($id) {
        if (!\Core\CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token'];
            $this->redirect("/monitors/$id/edit");
            return;
        }
    
        $validator = new \Core\Validator();
        $rules = [
            'name' => ['required', 'min:1', 'max:255'],
            'type' => ['required'],
            'interval_seconds' => ['required', 'numeric'],
            'webhook_url' => ['url', 'max:255']
        ];

        if ($_POST['type'] === 'http') {
            $rules['url'] = ['required', 'url', 'max:255'];
        } else {
            $rules['url'] = ['required', 'max:255'];
            $rules['port'] = ['required', 'port'];
        }
    
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Validation failed',
                'errors' => $validator->getErrors()
            ];
            $this->redirect("/monitors/$id/edit");
            return;
        }
    
        $data = $validator->sanitize($_POST, [
            'name' => 'string',
            'url' => 'url',
            'type' => 'string',
            'webhook_url' => 'url',
            'interval_seconds' => 'int',
            'port' => 'int'
        ]);
    
        try {
            $sql = "UPDATE monitors 
                    SET name = ?, url = ?, type = ?, interval_seconds = ?, 
                        port = ?, webhook_url = ?
                    WHERE id = ? AND user_id = ?";
            
            $this->db->query($sql, [
                $data['name'],
                $data['url'],
                $data['type'],
                $data['interval_seconds'],
                $data['port'] ?? null,
                $data['webhook_url'] ?? null,
                $id,
                $this->auth->getCurrentUser()['id']
            ]);
    
            $this->restartMonitor($id);
    
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Monitor updated successfully'];
            $this->redirect('/monitors');
            
        } catch (\Exception $e) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Failed to update monitor: ' . $e->getMessage()
            ];
            $this->redirect("/monitors/$id/edit");
        }
    }

    public function edit($id) {
        $userId = $this->auth->getCurrentUser()['id'];
        
        $sql = "SELECT * FROM monitors WHERE id = ? AND user_id = ?";
        $monitor = $this->db->query($sql, [$id, $userId])->fetch();
        
        if (!$monitor) {
            $this->error('Monitor not found', 404);
        }
        
        $this->view('monitors/edit', [
            'monitor' => $monitor,
            'isNew' => false
        ]);
    }

    public function delete($id) {
        $userId = $this->auth->getCurrentUser()['id'];
        
        try {
            // Stop the monitor first
            $this->stopMonitor($id);
            
            // Delete the monitor
            $sql = "DELETE FROM monitors WHERE id = ? AND user_id = ?";
            $result = $this->db->query($sql, [$id, $userId]);
            
            if ($result->rowCount() > 0) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Monitor deleted successfully.'
                ];
            } else {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Monitor not found.'
                ];
            }
        } catch (\Exception $e) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Failed to delete monitor: ' . $e->getMessage()
            ];
        }
        
        $this->redirect('/monitors');
    }

    private function startMonitor($id) {
        $logger = \Core\Logger::getInstance();
        $logger->info("Attempting to start monitor: " . $id);
        
        // Make API call to Node.js service
        $ch = curl_init('http://localhost:3000/api/monitors/start');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['monitorId' => (int)$id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Add a timeout
        $result = curl_exec($ch);
        
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            $logger->error("Failed to start monitor {$id}: " . $error);
            return false;
        }
        
        $logger->info("Start monitor API response: " . $result);
        return $result;
    }

    private function stopMonitor($id) {
        $logger = \Core\Logger::getInstance();
        $logger->info("Attempting to stop monitor: " . $id);
        
        $ch = curl_init('http://localhost:3000/api/monitors/stop');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['monitorId' => (int)$id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            $logger->error("Failed to stop monitor {$id}: " . $error);
            return false;
        }
        
        $logger->info("Stop monitor API response: " . $result);
        return $result;
    }

    private function restartMonitor($id) {
        $logger = \Core\Logger::getInstance();
        $logger->info("Restarting monitor: " . $id);
        
        $stopResult = $this->stopMonitor($id);
        
        // Sleep briefly to ensure the monitor is fully stopped
        sleep(1);
        
        $startResult = $this->startMonitor($id);
        
        return $startResult;
    }
}