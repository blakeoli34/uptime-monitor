<?php
namespace Controllers;

class AuthController extends BaseController {
    public function __construct() {
        parent::__construct();
    }

    public function showLogin() {
        if ($this->auth->isLoggedIn()) {
            $this->redirect('/');
        }
        $this->view('auth/login');
    }

    public function showRegister() {
        if ($this->auth->isLoggedIn()) {
            $this->redirect('/');
        }
        $this->view('auth/register');
    }

    public function login() {
        if (!\Core\CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token'];
            $this->redirect('/login');
            return;
        }
    
        $validator = new \Core\Validator();
        $rules = [
            'username' => ['required', 'min:3', 'max:255'],
            'password' => ['required', 'min:6', 'max:255']
        ];
    
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Validation failed',
                'errors' => $validator->getErrors()
            ];
            $this->redirect('/login');
            return;
        }
    
        $username = $_POST['username'];
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
    
        if ($this->auth->login($username, $password)) {
            if ($remember) {
                setcookie('remember_token', $this->auth->createRememberToken(), time() + (86400 * 30), '/', '', true, true);
            }
            $this->redirect('/');
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid credentials'];
            $this->redirect('/login');
        }
    }
    
    public function register() {
        if (!\Core\CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token'];
            $this->redirect('/register');
            return;
        }
    
        $validator = new \Core\Validator();
        $rules = [
            'username' => ['required', 'min:3', 'max:255', 'alphanumeric'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'min:6', 'max:255'],
            'password_confirmation' => ['required']
        ];
    
        if (!$validator->validate($_POST, $rules)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Validation failed',
                'errors' => $validator->getErrors()
            ];
            $this->redirect('/register');
            return;
        }
    
        if ($_POST['password'] !== $_POST['password_confirmation']) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Passwords do not match'];
            $this->redirect('/register');
            return;
        }
    
        $data = $validator->sanitize($_POST, [
            'username' => 'string',
            'email' => 'email'
        ]);
    
        if ($this->auth->register($data['username'], $_POST['password'], $data['email'])) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Registration successful. Please login.'
            ];
            $this->redirect('/login');
        } else {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Registration failed. Username or email might already be taken.'
            ];
            $this->redirect('/register');
        }
    }

    public function logout() {
        $this->auth->logout();
        $this->redirect('/login');
    }
}