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
        
        $sql = "SELECT m.*, 
                    (SELECT status FROM monitor_logs 
                     WHERE monitor_id = m.id 
                     ORDER BY checked_at DESC LIMIT 1) as current_status,
                    (SELECT response_time FROM monitor_logs 
                     WHERE monitor_id = m.id 
                     ORDER BY checked_at DESC LIMIT 1) as last_response_time
                FROM monitors m 
                WHERE user_id = ?
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
        // Make API call to Node.js service
        $ch = curl_init('http://localhost:3000/api/monitors/start');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['monitorId' => $id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function stopMonitor($id) {
        $ch = curl_init('http://localhost:3000/api/monitors/stop');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['monitorId' => $id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function restartMonitor($id) {
        $this->stopMonitor($id);
        return $this->startMonitor($id);
    }
}