<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$notif_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch the notification to get the link and ensure it belongs to the user
$stmt = $conn->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $notif_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $redirect_link = $row['link'] ? htmlspecialchars_decode($row['link']) : '#';
    
    // Mark as read
    $updateStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $updateStmt->bind_param("ii", $notif_id, $user_id);
    $updateStmt->execute();
    
    // The link is usually relative to the dashboard, e.g., 'users.php' or 'es-shs.php'.
    // Since this script is in /api/, redirecting to 'users.php' goes to '/api/users.php'. 
    // We must prepend the path to their role's dashboard.
    if ($redirect_link !== '#' && !str_starts_with($redirect_link, 'http')) {
        $role = $_SESSION['role'] ?? 'admin';
        $redirect_link = "../dashboard/" . $role . "/" . $redirect_link;
    }
    
    header("Location: " . $redirect_link);
    exit;
} else {
    // Notification not found or doesn't belong to user
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
    exit;
}
?>
