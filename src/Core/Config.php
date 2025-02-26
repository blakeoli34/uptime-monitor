<?php
namespace Core;

class Config {
    private static $config = null;
    private static $env = [];

    public static function load() {
        if (self::$config !== null) {
            return self::$config;
        }

        // Load .env file
        self::loadEnv();

        // Build configuration
        self::$config = [
            'app' => [
                'name' => self::env('APP_NAME', 'Uptime Monitor'),
                'env' => self::env('APP_ENV', 'production'),
                'url' => self::env('APP_URL', 'http://localhost'),
                'debug' => self::env('APP_DEBUG', false),
            ],
            'db' => [
                'host' => self::env('DB_HOST', 'localhost'),
                'name' => self::env('DB_NAME', 'uptimemonitor'),
                'user' => self::env('DB_USER', 'uptime_user'),
                'pass' => self::env('DB_PASS', ''),
            ],
            'monitor' => [
                'port' => (int)self::env('NODE_PORT', 3000),
                'default_interval' => (int)self::env('DEFAULT_CHECK_INTERVAL', 300),
                'timeouts' => [
                    'http' => (int)self::env('HTTP_TIMEOUT', 10000),
                    'ssl' => (int)self::env('SSL_TIMEOUT', 5000),
                    'tcp' => (int)self::env('TCP_TIMEOUT', 5000),
                ],
            ],
            'session' => [
                'lifetime' => (int)self::env('SESSION_LIFETIME', 86400),
                'secure' => self::env('COOKIE_SECURE', true),
                'domain' => self::env('COOKIE_DOMAIN', ''),
            ],
            'logging' => [
                'path' => self::env('LOG_PATH', '/var/www/uptimemonitor/logs'),
                'level' => self::env('LOG_LEVEL', 'error'),
            ],
        ];

        return self::$config;
    }

    private static function loadEnv() {
        $envFile = __DIR__ . '/../../.env';
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            self::$env[trim($name)] = trim($value);
        }
    }

    public static function env($key, $default = null) {
        return self::$env[$key] ?? $default;
    }

    public static function get($key = null, $default = null) {
        if (self::$config === null) {
            self::load();
        }

        if ($key === null) {
            return self::$config;
        }

        $keys = explode('.', $key);
        $config = self::$config;

        foreach ($keys as $segment) {
            if (!isset($config[$segment])) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }
}