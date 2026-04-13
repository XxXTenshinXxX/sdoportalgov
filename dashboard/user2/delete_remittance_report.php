<?php
session_start();
require_once "../../config/db.php";
require_once "../includes/activity_logger.php";
require_once "../includes/notification_helper.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user2") {
    die(json_encode(["success" => false, "message" => "Unauthorized access."]));
}

$userId = null;
if (isset($_SESSION['username'])) {
    $uName = $_SESSION['username'];
    $uRes = $conn->query("SELECT id FROM users WHERE username = '$uName'");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $userId = $uRow['id'];
    }
}

$data = json_decode(file_get_contents("php://input"), true);
$ids = isset($data['ids']) ? $data['ids'] : [];

if (empty($ids)) {
    die(json_encode(["success" => false, "message" => "No reports selected."]));
}

$success = true;
$message = "";

$conn->begin_transaction();

try {
    foreach ($ids as $id) {
        $id = intval($id);
        // Update status to pending for QES module
        $conn->query("UPDATE remittance_reports SET delete_status = 'pending' WHERE id = $id AND module_type = 'qes'");
    }
    $conn->commit();
    logActivity($conn, $userId, 'Delete Request', "Requested deletion of reports: " . implode(', ', $ids), 'qes');

    // Notify Admins and User3
    notifyRoles(
        $conn,
        ['admin', 'user3'],
        $userId,
        "New Delete Request",
        "User (u: " . $_SESSION['username'] . ") has requested to delete " . count($ids) . " QES report(s).",
        "warning",
        "delete_requests.php"
    );

    $message = "Delete request sent for approval.";
} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    $message = "Error: " . $e->getMessage();
}

echo json_encode(["success" => $success, "message" => $message]);
exit;
