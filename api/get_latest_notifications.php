<?php
session_start();
require_once "../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$uId = $_SESSION['user_id'];
$limit = 5;

// Fetch latest unread notifications
$notifications = [];
$res = $conn->query("SELECT id, title, message, link, created_at FROM notifications WHERE user_id = $uId AND is_read = 0 ORDER BY created_at DESC LIMIT $limit");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Fetch total unread count
$countRes = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $uId AND is_read = 0");
$unreadCount = $countRes ? $countRes->fetch_assoc()['total'] : 0;

// Fetch unread activity logs count (since last view). Admins see all logs, regular users exclude admin activity.
$role = $_SESSION['role'] ?? 'user';
$sql = ($role === 'admin') 
    ? "SELECT COUNT(*) as total FROM activity_logs l WHERE l.created_at > (SELECT IFNULL(last_viewed_logs, '1970-01-01 00:00:00') FROM users WHERE id = $uId)"
    : "SELECT COUNT(*) as total FROM activity_logs l JOIN users u ON l.user_id = u.id WHERE u.role != 'admin' AND l.created_at > (SELECT IFNULL(last_viewed_logs, '1970-01-01 00:00:00') FROM users WHERE id = $uId)";

$logRes = $conn->query($sql);
$logCount = $logRes ? $logRes->fetch_assoc()['total'] : 0;

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount,
    'log_count' => $logCount,
    'notifications' => $notifications
]);
?>
