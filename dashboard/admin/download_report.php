<?php
session_start();
require '../../config/db.php';
require '../includes/activity_logger.php';

if (!isset($_GET['id'])) {
    die("Report ID not specified.");
}

$id = intval($_GET['id']);

// Fetch report details
$stmt = $conn->prepare("SELECT file_path, original_filename, spa_no, module_type FROM remittance_reports WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $filePath = "../../" . $row['file_path'];
    // Use original_filename if available, fallback to basename of file_path
    $fileName = $row['original_filename'] ?: basename($row['file_path']);

    if (file_exists($filePath)) {
        // Log Activity
        $userId = $_SESSION['user_id'] ?? 0;
        $spaNo = $row['spa_no'] ?? 'N/A';
        $moduleType = $row['module_type'] ?? 'unknown';
        logActivity($conn, $userId, 'Download PDF', "Downloaded report: $fileName (SPA: $spaNo)", $moduleType);

        // Clear buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // Output file
        readfile($filePath);
        exit;
    } else {
        die("Error: File not found on server.");
    }
} else {
    die("Error: Report not found in database.");
}
?>