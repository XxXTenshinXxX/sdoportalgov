<?php
if (!function_exists('createNotification')) {
    /**
     * Create a notification for a specific user
     */
    function createNotification($conn, $userId, $fromUserId, $title, $message, $type = 'info', $link = '#')
    {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $userId, $fromUserId, $title, $message, $type, $link);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('notifyRoles')) {
    /**
     * Create notifications for all users with specific roles
     * @param array $roles Array of role names, e.g., ['admin', 'user3']
     */
    function notifyRoles($conn, $roles, $fromUserId, $title, $message, $type = 'info', $link = '#')
    {
        if (empty($roles))
            return;

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $types = str_repeat('s', count($roles));

        $sql = "SELECT id FROM users WHERE role IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$roles);
        $stmt->execute();
        $result = $stmt->get_result();

        $targetIds = [];
        while ($row = $result->fetch_assoc()) {
            $targetIds[] = $row['id'];
        }
        $stmt->close();

        foreach ($targetIds as $userId) {
            // Avoid notifying the person who triggered the event if they have one of the roles
            if ($userId == $fromUserId)
                continue;
            createNotification($conn, $userId, $fromUserId, $title, $message, $type, $link);
        }
    }
}
?>