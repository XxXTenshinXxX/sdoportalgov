<?php
session_start();
require_once "../../config/db.php";
require_once "../../dashboard/includes/activity_logger.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user3") {
    header("Location: ../../index.php");
    exit;
}

// Update last activity
if (isset($_SESSION["username"])) {
    $sessionUsername = $_SESSION["username"];
    $conn->query("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE username = '$sessionUsername'");
}

// Log Sidebar Navigation
$current_page = basename($_SERVER['PHP_SELF']);
$navigation_pages = [
    'dashboard.php' => 'Viewed Dashboard',
    'users.php' => 'Viewed Users Page',
    'es-shs.php' => 'Viewed ES/SHS Module',
    'es-shs-members.php' => 'Viewed ES/SHS Members',
    'qes.php' => 'Viewed QES Module',
    'qes-members.php' => 'Viewed QES Members',
    'delete_requests.php' => 'Viewed Delete Requests',
    'activity_logs.php' => 'Viewed Activity Logs',
    'profile.php' => 'Viewed Profile'
];

if (isset($navigation_pages[$current_page]) && isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'admin') {
    logActivity($conn, $_SESSION['user_id'], "Navigation", $navigation_pages[$current_page]);
}

// Update last viewed logs timestamp when visiting activity_logs.php
if ($current_page === 'activity_logs.php' && isset($_SESSION['user_id'])) {
    $uId = $_SESSION['user_id'];
    $conn->query("UPDATE users SET last_viewed_logs = CURRENT_TIMESTAMP WHERE id = $uId");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User 3 Portal - Schools Division Office</title>
    <link rel="icon" type="image/png" href="../../assets/img/SDO-Logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script>
        // Check local storage for sidebar state on page load
        (function () {
            const sidebarState = localStorage.getItem('sidebarCollapsed');
            if (sidebarState === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
                // We add it to documentElement to avoid flash of content before body is ready
            }
        })();

        document.addEventListener('DOMContentLoaded', function () {
            // Ensure body class matches documentElement for consistency
            if (document.documentElement.classList.contains('sidebar-collapsed')) {
                document.body.classList.add('sidebar-collapsed');
            }

            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    document.documentElement.classList.toggle('sidebar-collapsed');
                    document.body.classList.toggle('sidebar-collapsed');
                    const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
            }
        });
    </script>
</head>

<body>