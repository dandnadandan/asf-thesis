<?php
/**
 * One-time migration: adds upload_id column to data tables so deletions cascade properly.
 * Run once, then this file can be deleted.
 */
require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

if (!isLoggedIn() || !canManageContent()) {
    die('Unauthorized');
}

$database = new Database();
$pdo = $database->getConnection();

$alterStatements = [
    "ALTER TABLE environmental_data ADD COLUMN IF NOT EXISTS upload_id INT NULL DEFAULT NULL",
    "ALTER TABLE asf_outbreaks ADD COLUMN IF NOT EXISTS upload_id INT NULL DEFAULT NULL",
    "ALTER TABLE depopulation_events ADD COLUMN IF NOT EXISTS upload_id INT NULL DEFAULT NULL",
    "ALTER TABLE meat_movement ADD COLUMN IF NOT EXISTS upload_id INT NULL DEFAULT NULL",
];

$results = [];
foreach ($alterStatements as $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['sql' => $sql, 'status' => 'OK'];
    } catch (Exception $e) {
        $results[] = ['sql' => $sql, 'status' => 'ERROR: ' . $e->getMessage()];
    }
}

header('Content-Type: text/plain');
foreach ($results as $r) {
    echo $r['status'] . ' — ' . $r['sql'] . "\n";
}
echo "\nMigration complete. You can now delete this file.";
