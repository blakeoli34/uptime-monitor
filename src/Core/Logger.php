<?php
namespace Core;

class Logger {
    private static $instance = null;
    private $logPath;
    private $logLevel;
    
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];

    private function __construct() {
        $config = Config::get('logging');
        $this->logPath = rtrim($config['path'], '/');
        $this->logLevel = strtolower($config['level']);
        
        // Create log directory if it doesn't exist
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0770, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($level, $message, array $context = []) {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $this->interpolate($message, $context),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        if (!empty($context)) {
            $logEntry['context'] = $context;
        }

        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';
        $jsonEntry = json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($logFile, $jsonEntry, FILE_APPEND | LOCK_EX);
    }

    private function shouldLog($level) {
        return self::LEVELS[$level] >= self::LEVELS[$this->logLevel];
    }

    private function interpolate($message, array $context = []) {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    // Convenience methods
    public function debug($message, array $context = []) {
        $this->log('debug', $message, $context);
    }

    public function info($message, array $context = []) {
        $this->log('info', $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->log('warning', $message, $context);
    }

    public function error($message, array $context = []) {
        $this->log('error', $message, $context);
    }

    public function critical($message, array $context = []) {
        $this->log('critical', $message, $context);
    }
}