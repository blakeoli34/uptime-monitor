<?php
namespace Controllers;

use Core\Auth;
use Core\Config;

class BaseController {
    protected $auth;
    protected $config;

    public function __construct() {
        $this->auth = Auth::getInstance();
        $this->config = Config::get();
    }

    protected function view($name, $data = []) {
        // Add some default data
        $data['auth'] = $this->auth;
        $data['config'] = $this->config;
        $data['currentUser'] = $this->auth->getCurrentUser();
        
        // Extract data to make variables available in view
        extract($data);
        
        // Include header
        require __DIR__ . '/../Views/layout/header.php';
        
        // Include the view file
        require __DIR__ . '/../Views/' . $name . '.php';
        
        // Include footer
        require __DIR__ . '/../Views/layout/footer.php';
    }

    protected function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function redirect($path) {
        header('Location: ' . $path);
        exit;
    }

    protected function error($message, $code = 400) {
        http_response_code($code);
        $this->view('errors/error', ['message' => $message]);
        exit;
    }
}