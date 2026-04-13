<?php
session_start();
require_once "../../config/db.php";
require_once "../includes/activity_logger.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user2") {
    header("Location: ../../index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Security check: ensure report belongs to QES module
    $result = $conn->query("SELECT * FROM remittance_reports WHERE id = $id AND module_type = 'qes'");

    if ($result && $row = $result->fetch_assoc()) {
        $filePath = "../../" . $row['file_path'];
        $fileName = $row['original_filename'] ?: basename($row['file_path']);

        if (file_exists($filePath)) {
            // Log Activity
            $userId = $_SESSION['user_id'] ?? 0;
            $spaNo = $row['spa_no'] ?? 'N/A';
            $moduleType = $row['module_type'] ?? 'qes';
            logActivity($conn, $userId, 'Download PDF', "Downloaded report: $fileName (SPA: $spaNo)", $moduleType);

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            echo "File not found.";
        }
    } else {
        echo "Report not found or access denied.";
    }
} else {
    header("Location: qes.php");
}
exit;
