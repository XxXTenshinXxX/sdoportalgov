<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$uId = $_SESSION['user_id'];

// We can either delete them entirely or mark them all as read. Usually 'clear' means delete.
$stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $uId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear notifications']);
}
?>
