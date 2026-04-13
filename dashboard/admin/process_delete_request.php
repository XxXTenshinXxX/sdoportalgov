<?php
session_start();
require_once "../../config/db.php";
require_once "../includes/activity_logger.php";
require_once "../includes/notification_helper.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "user3")) {
    die(json_encode(["success" => false, "message" => "Unauthorized access."]));
}

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? intval($data['id']) : 0;
$action = isset($data['action']) ? $data['action'] : '';

if ($id <= 0 || !in_array($action, ['approve', 'reject'])) {
    die(json_encode(["success" => false, "message" => "Invalid request parameters."]));
}

$conn->begin_transaction();

try {
    // 1. Fetch report details first (needed for logging and notifications)
    $stmt = $conn->prepare("SELECT * FROM remittance_reports WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $report = $res->fetch_assoc();

    if (!$report) {
        throw new Exception("Report not found.");
    }

    $uploaderId = $report['uploaded_by'];
    $spaNo = $report['spa_no'];
    $moduleType = $report['module_type'];
    $adminId = $_SESSION['user_id'] ?? 0;

    if ($action === 'approve') {
        // Permanent Delete
        $filePath = "../../" . $report['file_path'];

        // Delete members
        $stmt = $conn->prepare("DELETE FROM remittance_members WHERE report_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Delete report
        $stmt = $conn->prepare("DELETE FROM remittance_reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Delete physical file
        if (!empty($report['file_path']) && file_exists($filePath)) {
            @unlink($filePath);
        }

        logActivity($conn, $adminId, 'Approve Deletion', "Approved deletion for report ID: $id (SPA: $spaNo)", $moduleType);

        // Notify Uploader
        createNotification(
            $conn,
            $uploaderId,
            $adminId,
            "Delete Request Approved",
            "Your request to delete report (SPA: $spaNo) has been approved and the report is permanently deleted.",
            "success",
            ($moduleType === 'philhealth' ? 'philhealth.php' : ($moduleType === 'es_shs' ? 'es-shs.php' : 'qes.php'))
        );

        $message = "Report permanently deleted.";
    } else {
        // Restore
        $stmt = $conn->prepare("UPDATE remittance_reports SET delete_status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        logActivity($conn, $adminId, 'Reject Deletion', "Rejected deletion for report ID: $id (SPA: $spaNo)", $moduleType);

        // Notify Uploader
        createNotification(
            $conn,
            $uploaderId,
            $adminId,
            "Delete Request Rejected",
            "Your request to delete report (SPA: $spaNo) has been rejected. The report has been restored to active status.",
            "danger",
            ($moduleType === 'philhealth' ? 'philhealth.php' : ($moduleType === 'es_shs' ? 'es-shs.php' : 'qes.php'))
        );

        $message = "Report restored to active status.";
    }

    $conn->commit();
    echo json_encode(["success" => true, "message" => $message]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>