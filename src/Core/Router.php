<?php
namespace Core;

use Core\Database;

class Router {
    private $routes = [];
    private $params = [];

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function add($method, $path, $handler) {
        // Convert route parameters to regex pattern
        $pattern = preg_replace('/\{([a-zA-Z]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^" . $pattern . "$#";
        
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'path' => $path
        ];
    }

    public function match($method, $uri) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                $this->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $route;
            }
        }
        return false;
    }

    public function handle($method, $uri) {
        $host = $_SERVER['HTTP_HOST'];
    
        // Check if this is a status subdomain
        if (strpos($host, 'status.') === 0) {
            $domain = str_replace('status.', '', $host);
            $this->handleStatusPage($domain);
            return;
        }
        $route = $this->match($method, $uri);
        
        if (!$route) {
            http_response_code(404);
            include __DIR__ . '/../Views/errors/404.php';
            return;
        }

        [$controller, $action] = explode('@', $route['handler']);
        $controller = "Controllers\\" . $controller;
        
        if (!class_exists($controller)) {
            throw new \Exception("Controller {$controller} not found");
        }

        $controllerInstance = new $controller();
        
        if (!method_exists($controllerInstance, $action)) {
            throw new \Exception("Action {$action} not found in controller {$controller}");
        }

        // Pass route parameters to the controller action
        call_user_func_array([$controllerInstance, $action], $this->params);
    }

    private function handleStatusPage($domain) {
        try {
            $sql = "SELECT slug FROM status_pages WHERE custom_domain = ?";
            $result = $this->db->query($sql, [$domain])->fetch();
            
            if ($result) {
                // Forward to status page controller
                $controller = new \Controllers\StatusPageController();
                $controller->show($result['slug']);
            } else {
                http_response_code(404);
                include __DIR__ . '/../Views/errors/404.php';
            }
        } catch (\Exception $e) {
            http_response_code(500);
            include __DIR__ . '/../Views/errors/500.php';
        }
    }

    public function getParams() {
        return $this->params;
    }
}