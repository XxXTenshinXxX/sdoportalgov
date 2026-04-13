<?php
require '../../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

if (empty($userId) || empty($username)) {
    header("Location: users.php?error=Missing required fields.");
    exit;
}

// Check if username already exists for other users
$checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$checkStmt->bind_param("si", $username, $userId);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    header("Location: users.php?error=Username already taken.");
    exit;
}

// Update username
$updateStmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
$updateStmt->bind_param("si", $username, $userId);

if ($updateStmt->execute()) {
    // If password is provided, update it
    if (!empty($newPassword)) {
        if ($newPassword !== $confirmPassword) {
            header("Location: users.php?error=Passwords do not match.");
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $passStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $passStmt->bind_param("si", $hashedPassword, $userId);
        $passStmt->execute();
    }

    header("Location: users.php?success=User updated successfully.");
} else {
    header("Location: users.php?error=Failed to update user.");
}
exit;
?>