<?php
header('Content-Type: application/json');
require_once '../../includes/session_manager.php';
require_once '../../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if (!canManageContent()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$uploadId = isset($_POST['upload_id']) && is_numeric($_POST['upload_id']) ? intval($_POST['upload_id']) : -1;

if ($uploadId < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid upload ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $checkStmt = $pdo->prepare("SELECT id, file_path, upload_type FROM data_uploads WHERE id = ?");
    $checkStmt->execute([$uploadId]);
    $upload = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$upload) {
        echo json_encode(['success' => false, 'error' => 'Upload record not found']);
        exit();
    }

    // 1. Delete the physical CSV file
    if (!empty($upload['file_path'])) {
        $fullPath = '../../' . $upload['file_path'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    // 2. For outbreaks: delete linked documents (files + DB records) before deleting outbreaks
    if ($upload['upload_type'] === 'outbreaks') {
        $docStmt = $pdo->prepare("
            SELECT id, file_path FROM outbreak_documents
            WHERE outbreak_id IN (SELECT id FROM asf_outbreaks WHERE upload_id = ?)
        ");
        $docStmt->execute([$uploadId]);
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($documents as $doc) {
            if (!empty($doc['file_path'])) {
                $docPath = '../../' . $doc['file_path'];
                if (file_exists($docPath)) {
                    @unlink($docPath);
                }
            }
        }

        $pdo->prepare("
            DELETE FROM outbreak_documents
            WHERE outbreak_id IN (SELECT id FROM asf_outbreaks WHERE upload_id = ?)
        ")->execute([$uploadId]);
    }

    // 3. Delete rows from the primary data table linked to this upload
    $dataTableMap = [
        'environmental' => 'environmental_data',
        'outbreaks'     => 'asf_outbreaks',
        'depopulation'  => 'depopulation_events',
        'meat_movement' => 'meat_movement',
    ];
    if (isset($dataTableMap[$upload['upload_type']])) {
        $table = $dataTableMap[$upload['upload_type']];
        $pdo->prepare("DELETE FROM `{$table}` WHERE upload_id = ?")->execute([$uploadId]);
    }

    // 4. Clear derived tables — risk zones and predictive models become stale when source data is removed
    $pdo->exec("DELETE FROM risk_zones");
    $pdo->exec("DELETE FROM predictive_models");

    // 5. Delete the data_uploads record itself
    $pdo->prepare("DELETE FROM data_uploads WHERE id = ?")->execute([$uploadId]);

    echo json_encode(['success' => true, 'message' => 'Upload and all related data deleted successfully']);

} catch (Exception $e) {
    error_log("Error deleting upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error deleting upload: ' . $e->getMessage()]);
}
?>
