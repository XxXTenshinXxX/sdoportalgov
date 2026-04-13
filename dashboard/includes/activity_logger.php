<?php
if (!function_exists('logActivity')) {
    function logActivity($conn, $userId, $action, $details, $moduleType = null)
    {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, module_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $action, $details, $moduleType);
        $stmt->execute();
        $stmt->close();
    }
}
?>