<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../vendor/autoload.php';
require '../../config/db.php';
require 'pdf_utils.php';
require '../includes/activity_logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_files'])) {
    $moduleType = $_POST['module_type'] ?? 'philhealth';

    // Get uploader user ID
    $uploaderId = null;
    if (isset($_SESSION['username'])) {
        $uName = $_SESSION['username'];
        $uRes = $conn->query("SELECT id FROM users WHERE username = '$uName'");
        if ($uRes && $uRow = $uRes->fetch_assoc()) {
            $uploaderId = $uRow['id'];
        }
    }

    $files = $_FILES['pdf_files'];
    $successCount = 0;
    $errors = [];

    // Define upload directories
    $baseUploadDir = "../../uploads/";
    $targetSubDir = "";

    switch ($moduleType) {
        case 'philhealth':
            $targetSubDir = "philhealth-reports/";
            break;
        case 'es_shs':
            $targetSubDir = "es-shs/";
            break;
        case 'qes':
            $targetSubDir = "qes/";
            break;
        default:
            $targetSubDir = "others/";
            break;
    }

    $finalUploadPath = $baseUploadDir . $targetSubDir;

    // Create directories if they don't exist
    if (!is_dir($finalUploadPath)) {
        mkdir($finalUploadPath, 0777, true);
    }

    foreach ($files['tmp_name'] as $key => $tmpName) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $originalFileName = $files['name'][$key];

            // Clean filename to prevent issues
            $safeFileName = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalFileName);
            $destination = $finalUploadPath . $safeFileName;

            // Parse PDF
            $parsedData = parseRemittancePDF($tmpName, $moduleType);

            if (isset($parsedData['error'])) {
                $errors[] = "Error parsing $originalFileName: " . $parsedData['error'];
                continue;
            }

            $meta = $parsedData['metadata'];
            $members = $parsedData['members'];

            // Extract SPA No. early for duplicate check
            $spaNo = $meta['spa_no'] ?? 'SPA-' . time();

            // Duplicate SPA No. check
            $dupCheck = $conn->prepare("SELECT id FROM remittance_reports WHERE spa_no = ? AND module_type = ? AND delete_status = 'active' LIMIT 1");
            $dupCheck->bind_param("ss", $spaNo, $moduleType);
            $dupCheck->execute();
            $dupCheck->store_result();
            if ($dupCheck->num_rows > 0) {
                $dupCheck->close();
                $errors[] = "Duplicate SPA No. '$spaNo' in $originalFileName — already exists in the database.";
                continue;
            }
            $dupCheck->close();

            // Start DB Transaction
            $conn->begin_transaction();

            try {
                $period = $meta['period'] ?? date('m-Y');
                $controlNo = $meta['control_no'] ?? 'CTRL-' . time();
                $dateGen = $meta['date_generated'] ?? date('Y-m-d H:i:s');
                $dateRec = $meta['date_received'] ?? date('Y-m-d H:i:s');
                $empName = $meta['employer_name'] ?? 'UNKNOWN EMPLOYER';
                $empTin = $meta['employer_tin'] ?? '000-000-000';
                $empType = $meta['employer_type'] ?? 'Government';
                $philhealthNo = $meta['philhealth_no'] ?? '';
                $groupName = $meta['group_name'] ?? '';
                $empAddress = $meta['employer_address'] ?? '';

                $totalMems = count($members);
                $totalPS = 0;
                $totalES = 0;
                foreach ($members as $m) {
                    $totalPS += (float) $m['ps'];
                    $totalES += (float) $m['es'];
                }

                // Move file to permanent location
                if (!move_uploaded_file($tmpName, $destination)) {
                    throw new Exception("Failed to move uploaded file $originalFileName");
                }

                // Path to store in DB (relative to site root or as needed)
                $dbFilePath = "uploads/" . $targetSubDir . $safeFileName;

                // Insert into remittance_reports
                $stmt = $conn->prepare("INSERT INTO remittance_reports
(module_type, spa_no, period, control_no, date_generated, date_received, employer_name, employer_tin, employer_type,
philhealth_no, group_name, employer_address, total_members, total_ps, total_es, file_path, original_filename,
uploaded_by)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssssssssssssiddssi",
                    $moduleType,
                    $spaNo,
                    $period,
                    $controlNo,
                    $dateGen,
                    $dateRec,
                    $empName,
                    $empTin,
                    $empType,
                    $philhealthNo,
                    $groupName,
                    $empAddress,
                    $totalMems,
                    $totalPS,
                    $totalES,
                    $dbFilePath,
                    $originalFileName,
                    $uploaderId
                );
                $stmt->execute();
                $reportId = $conn->insert_id;

                // Insert Members
                $mStmt = $conn->prepare("INSERT INTO remittance_members (report_id, member_no, surname, given_name, middle_name,
personal_share, employer_share, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($members as $member) {
                    $mStmt->bind_param(
                        "issssdss",
                        $reportId,
                        $member['no'],
                        $member['surname'],
                        $member['given'],
                        $member['middle'],
                        $member['ps'],
                        $member['es'],
                        $member['status']
                    );
                    $mStmt->execute();
                }

                $conn->commit();
                $successCount++;
                logActivity($conn, $uploaderId, 'Upload', "Uploaded $moduleType report: $originalFileName", $moduleType);

            } catch (Exception $e) {
                $conn->rollback();
                // If DB failed, delete the moved file if it exists
                if (file_exists($destination)) {
                    unlink($destination);
                }
                $errors[] = "Error processing $originalFileName: " . $e->getMessage();
            }
        }
    }

    // Redirect back with message
    $baseRedirect = "philhealth.php";
    if ($moduleType === 'es_shs')
        $baseRedirect = "es-shs.php";
    if ($moduleType === 'qes')
        $baseRedirect = "qes.php";

    if ($successCount > 0) {
        $msg = "Successfully uploaded and saved $successCount report(s).";
        if (count($errors) > 0)
            $msg .= " However, some errors occurred: " . implode(", ", $errors);
    } else {
        $msg = "Upload failed: " . implode(", ", $errors);
    }

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => $successCount > 0,
            "message" => $msg,
            "errors" => $errors
        ]);
        exit;
    }

    if ($successCount > 0) {
        header("Location: $baseRedirect?msg=" . urlencode($msg) . "&status=success");
    } else {
        header("Location: $baseRedirect?msg=" . urlencode($msg) . "&status=error");
    }
} else {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "No files uploaded."]);
        exit;
    }
    header("Location: philhealth.php");
}
?>