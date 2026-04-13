<?php
session_start();
require_once "../../config/db.php";
require_once "../../vendor/autoload.php";
require_once "../admin/pdf_utils.php";
require_once "../includes/activity_logger.php";
require_once "../includes/notification_helper.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "user2") {
    die(json_encode(["success" => false, "message" => "Unauthorized access."]));
}

$userId = null;
if (isset($_SESSION['username'])) {
    $uName = $_SESSION['username'];
    $uRes = $conn->query("SELECT id FROM users WHERE username = '$uName'");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $userId = $uRow['id'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['pdf_files'])) {
    $module_type = 'qes'; // Restrict to QES
    $uploadDir = "../../uploads/remittance_reports/$module_type/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $errors = [];
    $success_count = 0;

    foreach ($_FILES['pdf_files']['name'] as $key => $name) {
        if ($_FILES['pdf_files']['error'][$key] == 0) {
            $tmp_name = $_FILES['pdf_files']['tmp_name'][$key];
            $original_filename = $name;
            $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $extension;
            $destination = $uploadDir . $new_filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $filePath = "uploads/remittance_reports/$module_type/" . $new_filename;

                // Parse PDF
                $parseResult = parseRemittancePDF($destination, $module_type);

                if (isset($parseResult['members'])) {
                    $metadata = $parseResult['metadata'];
                    $members = $parseResult['members'];

                    // Duplicate SPA No. check
                    $spa_no = $metadata['spa_no'] ?? 'SPA-' . time();
                    $dupCheck = $conn->prepare("SELECT id FROM remittance_reports WHERE spa_no = ? AND module_type = ? AND delete_status = 'active' LIMIT 1");
                    $dupCheck->bind_param("ss", $spa_no, $module_type);
                    $dupCheck->execute();
                    $dupCheck->store_result();
                    if ($dupCheck->num_rows > 0) {
                        $dupCheck->close();
                        $errors[] = "Duplicate SPA No. '$spa_no' in $name — already exists in the database.";
                        continue;
                    }
                    $dupCheck->close();

                    // Use transactions to ensure atomic insertion
                    $conn->begin_transaction();

                    try {
                        // Insert report
                        $stmt = $conn->prepare("INSERT INTO remittance_reports 
                            (module_type, spa_no, period, employer_name, group_name, employer_tin, employer_type, philhealth_no, control_no, date_generated, total_members, total_ps, total_es, file_path, original_filename, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $total_ps = 0;
                        $total_es = 0;
                        foreach ($members as $m) {
                            $total_ps += (float) $m['ps'];
                            $total_es += (float) $m['es'];
                        }

                        $period = $metadata['period'] ?? date('m-Y');
                        $emp_name = $metadata['employer_name'] ?? 'UNKNOWN';
                        $group_name = $metadata['group_name'] ?? '';
                        $emp_tin = $metadata['employer_tin'] ?? '';
                        $emp_type = $metadata['employer_type'] ?? 'Government';
                        $ph_no = $metadata['philhealth_no'] ?? '';
                        $ctrl_no = $metadata['control_no'] ?? 'CTRL-' . time();
                        $date_gen = $metadata['date_generated'] ?? date('Y-m-d H:i:s');
                        $total_mems = count($members);

                        $stmt->bind_param(
                            "sssssssssssssssi",
                            $module_type,
                            $spa_no,
                            $period,
                            $emp_name,
                            $group_name,
                            $emp_tin,
                            $emp_type,
                            $ph_no,
                            $ctrl_no,
                            $date_gen,
                            $total_mems,
                            $total_ps,
                            $total_es,
                            $filePath,
                            $original_filename,
                            $userId
                        );

                        $stmt->execute();
                        $report_id = $stmt->insert_id;

                        // Insert members
                        $memberStmt = $conn->prepare("INSERT INTO remittance_members 
                            (report_id, member_no, surname, given_name, middle_name, personal_share, employer_share, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                        foreach ($members as $member) {
                            $memberStmt->bind_param(
                                "issssdss",
                                $report_id,
                                $member['no'],
                                $member['surname'],
                                $member['given'],
                                $member['middle'],
                                $member['ps'],
                                $member['es'],
                                $member['status']
                            );
                            $memberStmt->execute();
                        }

                        $conn->commit();
                        $success_count++;
                        logActivity($conn, $userId, 'Upload', "Uploaded QES report: $original_filename", 'qes');

                        // Notify admin and user3
                        notifyRoles($conn, ['admin', 'user3'], $userId, "New Upload", strtoupper($_SESSION['username'] ?? 'User') . " uploaded a new QES report: $original_filename", "info", "qes.php");
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errors[] = "Error database insertion for $name: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Error parsing PDF $name.";
                }
            } else {
                $errors[] = "Error moving uploaded file $name.";
            }
        }
    }

    if ($success_count > 0) {
        $msg = "$success_count report(s) uploaded successfully.";
        if (!empty($errors)) {
            $msg .= " However, some errors occurred: " . implode(", ", $errors);
        }
    } else {
        $msg = "Upload failed: " . implode(", ", $errors);
    }

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => $success_count > 0,
            "message" => $msg,
            "errors" => $errors
        ]);
        exit;
    }

    if ($success_count > 0 && empty($errors)) {
        header("Location: qes.php?msg=" . urlencode($msg) . "&status=success");
    } else {
        header("Location: qes.php?msg=" . urlencode($msg) . "&status=danger");
    }
} else {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "No files uploaded."]);
        exit;
    }
    header("Location: qes.php");
}
exit;
