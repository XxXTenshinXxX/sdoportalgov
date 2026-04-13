<?php
session_start();

require_once "config/db.php";
require_once "dashboard/includes/activity_logger.php";

if (isset($_SESSION['user_id'])) {
    $uId = $_SESSION['user_id'];
    $uName = $_SESSION['username'] ?? 'User';
    logActivity($conn, $uId, 'Logout', "User $uName has logged out.");
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;