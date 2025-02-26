<?php
require_once __DIR__ . '/src/Core/Config.php';
require_once __DIR__ . '/src/Core/Database.php';

// Initialize database connection
$db = \Core\Database::getInstance();

try {
    // Begin transaction for safety
    $db->getConnection()->beginTransaction();

    // Delete all down incidents (status = 0)
    $sql = "DELETE FROM monitor_logs WHERE status = 0";
    $stmt = $db->query($sql);

    $rowsAffected = $stmt->rowCount();

    // Commit the transaction
    $db->getConnection()->commit();

    echo "Successfully removed $rowsAffected down incidents from the database.\n";

} catch (\Exception $e) {
    // Rollback in case of error
    $db->getConnection()->rollBack();
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}