<?php

namespace Core;

class Auth {
    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Database::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::initSession();
        }
        return self::$instance;
    }

    private static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $config = Config::get('session');
            session_set_cookie_params(
                $config['lifetime'],
                '/',
                $config['domain'],
                $config['secure'],
                true
            );
            session_start();
        }
    }

    public function login($username, $password) {
        $sql = "SELECT id, username, password, email FROM users WHERE username = ?";
        $stmt = $this->db->query($sql, [$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            return true;
        }
        return false;
    }

    public function register($username, $password, $email) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $sql = "INSERT INTO users (username, password, email) VALUES (?, ?, ?)";
            $this->db->query($sql, [$username, $hashedPassword, $email]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function logout() {
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email']
        ];
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
}