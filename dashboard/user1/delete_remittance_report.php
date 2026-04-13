<?php
session_start();
require_once "../../config/db.php";
require_once "../includes/activity_logger.php";
require_once "../includes/notification_helper.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user1") {
    header("Location: ../../index.php");
    exit;
}

$userId = null;
if (isset($_SESSION['username'])) {
    $uName = $_SESSION['username'];
    $uRes = $conn->query("SELECT id FROM users WHERE username = '$uName'");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $userId = $uRow['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = JSON_decode(file_get_contents('php://input'), true);
    if (!isset($data['ids']) || empty($data['ids'])) {
        echo JSON_encode(['success' => false, 'message' => 'No IDs provided.']);
        exit;
    }

    $ids = $data['ids'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Start transaction
    $conn->begin_transaction();

    try {
        // Instead of deleting, set status to pending
        $stmt = $conn->prepare("UPDATE remittance_reports SET delete_status = 'pending' WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        logActivity($conn, $userId, 'Delete Request', "Requested deletion of reports: " . implode(', ', $ids), 'es_shs');

        // Notify Admins and User3
        notifyRoles(
            $conn,
            ['admin', 'user3'],
            $userId,
            "New Delete Request",
            "User (u: " . $_SESSION['username'] . ") has requested to delete " . count($ids) . " ES/SHS report(s).",
            "warning",
            "delete_requests.php"
        );

        echo JSON_encode(['success' => true, 'message' => 'Delete request sent for approval.']);

    } catch (Exception $e) {
        $conn->rollback();
        echo JSON_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo JSON_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>