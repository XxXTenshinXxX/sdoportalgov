<?php
session_start();
header('Content-Type: application/json');
require '../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user3') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No IDs provided']);
    exit;
}

// Convert IDs to integers for safety
$ids = array_map('intval', $ids);
$ids_placeholder = implode(',', $ids);

try {
    // Start transaction
    $conn->begin_transaction();

    // 1. Get file paths to delete physical files
    $result = $conn->query("SELECT file_path FROM remittance_reports WHERE id IN ($ids_placeholder)");
    $filesToDelete = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['file_path'])) {
                // Adjust path based on your folder structure
                $filesToDelete[] = "../../" . $row['file_path'];
            }
        }
    }

    // 2. Delete from database (Cascading will handle members)
    $stmt = $conn->prepare("DELETE FROM remittance_reports WHERE id IN ($ids_placeholder)");
    if (!$stmt->execute()) {
        throw new Exception("Database deletion failed: " . $stmt->error);
    }

    // 3. Commit database changes first
    $conn->commit();

    // 4. Delete physical files from disk ONLY AFTER successful commit
    foreach ($filesToDelete as $file) {
        if (!empty($file) && file_exists($file)) {
            @unlink($file);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>