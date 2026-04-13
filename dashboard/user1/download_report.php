<?php
session_start();
require_once "../includes/activity_logger.php";

if (!isset($_GET['id'])) {
    die("Report ID missing.");
}

$reportId = (int) $_GET['id'];
$stmt = $conn->prepare("SELECT file_path, original_filename, module_type, spa_no FROM remittance_reports WHERE id = ?");
$stmt->bind_param("i", $reportId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $filePath = "../../" . $row['file_path'];
    $originalName = $row['original_filename'] ?: basename($filePath);

    if (file_exists($filePath)) {
        // Log Activity
        $userId = $_SESSION['user_id'] ?? 0;
        $spaNo = $row['spa_no'] ?? 'N/A';
        $moduleType = $row['module_type'] ?? 'es_shs';
        logActivity($conn, $userId, 'Download PDF', "Downloaded report: $originalName (SPA: $spaNo)", $moduleType);

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf'); // Force PDF type
        header('Content-Disposition: attachment; filename="' . $originalName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        flush();
        readfile($filePath);
        exit;
    } else {
        die("File not found on server.");
    }
} else {
    die("Report not found in database.");
}
?>