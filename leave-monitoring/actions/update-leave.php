<?php
session_start();
include '../comfig/database.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $leave_id = $_POST['leave_id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';

    // Employee details
    $surname = strtoupper(trim($_POST['surname'] ?? ''));
    $first_name = strtoupper(trim($_POST['first_name'] ?? ''));
    $middle_initial = strtoupper(trim($_POST['middle_initial'] ?? ''));
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $pob = trim($_POST['pob'] ?? '');
    $employee_no = trim($_POST['employee_no'] ?? '');
    $school_level = $_POST['school_level'] ?? 'ES';
    $status = $_POST['status'] ?? 'Active';

    // Leave details
    $period_from = $_POST['period_from'] ?? '';
    $period_to = $_POST['period_to'] ?? '';
    $reason = $_POST['reason'] ?? '';
    if ($reason === 'Others') {
        $reason = trim($_POST['other_reason'] ?? '');
    }
    $station = trim($_POST['station'] ?? '');
    $pay_status = $_POST['pay_status'] ?? '';
    $total_days = $_POST['total_days'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    // Strictly require IDs
    if (empty($leave_id) || empty($employee_id)) {
        echo json_encode(["status" => "error", "message" => "Missing record identifiers."]);
        exit;
    }

    // Check if total_days is valid if provided
    if ($total_days === 'Invalid') {
        echo json_encode(["status" => "error", "message" => "Invalid leave period provided."]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Update Employee record — preserve existing values if submitted ones are empty
        $emp_sql = "UPDATE employees SET 
            surname = IF(? = '', surname, ?),
            first_name = IF(? = '', first_name, ?),
            middle_initial = IF(? = '', middle_initial, ?),
            date_of_birth = IF(? IS NULL, date_of_birth, ?),
            place_of_birth = IF(? = '', place_of_birth, ?),
            employee_no = IF(? = '', employee_no, ?),
            school_level = IF(? = '', school_level, ?),
            status = IF(? = '', status, ?)
            WHERE id=?";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param(
            "ssssssssssssssssi",
            $surname,
            $surname,
            $first_name,
            $first_name,
            $middle_initial,
            $middle_initial,
            $dob,
            $dob,
            $pob,
            $pob,
            $employee_no,
            $employee_no,
            $school_level,
            $school_level,
            $status,
            $status,
            $employee_id
        );
        $emp_stmt->execute();

        // Only update Leave record if leave particulars were submitted
        if (!empty($_POST['period_from'])) {
            $leave_sql = "UPDATE leaves SET period_from=?, period_to=?, reason=?, station=?, pay_status=?, total_days=?, remarks=? WHERE id=? AND employee_id=?";
            $leave_stmt = $conn->prepare($leave_sql);
            $leave_stmt->bind_param("sssssissi", $period_from, $period_to, $reason, $station, $pay_status, $total_days, $remarks, $leave_id, $employee_id);
            $leave_stmt->execute();
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Record updated successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Error saving to database: " . $e->getMessage()]);
    }

    // Close connections
    if (isset($emp_stmt))
        $emp_stmt->close();
    if (isset($leave_stmt))
        $leave_stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>