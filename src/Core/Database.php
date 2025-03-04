<?php

namespace Core;

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $config = Config::get('db');
        
        try {
            $this->connection = new \PDO(
                "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4",
                $config['user'],
                $config['pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (\PDOException $e) {
            throw new \Exception("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private $slowQueryThreshold = 1.0; // seconds

    public function query($sql, $params = []) {
        $startTime = microtime(true);
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $duration = microtime(true) - $startTime;
        
        if ($duration > $this->slowQueryThreshold) {
            $logger = \Core\Logger::getInstance();
            $logger->warning("Slow query detected: {$duration}s", [
                'query' => $this->truncateSql($sql),
                'params' => json_encode(array_slice($params, 0, 10))
            ]);
        }
        
        return $stmt;
    }

    private function truncateSql($sql) {
        // Truncate very long queries for logging
        $sql = preg_replace('/\s+/', ' ', $sql);
        return strlen($sql) > 1000 ? substr($sql, 0, 997) . '...' : $sql;
    }
}