<?php
header('Content-Type: application/json');
include '../comfig/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$employee_id = intval($_POST['employee_id'] ?? 0);
$surname = trim($_POST['surname'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_initial = trim($_POST['middle_initial'] ?? '');
$dob = $_POST['dob'] ?? '';
$pob = trim($_POST['pob'] ?? '');
$employee_no = trim($_POST['employee_no'] ?? '');
$station = trim($_POST['station'] ?? '');
$period_from = $_POST['period_from'] ?? '';
$period_to = $_POST['period_to'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$other_reason = trim($_POST['other_reason'] ?? '');
$pay_status = $_POST['pay_status'] ?? 'N/A';
if (empty($pay_status)) $pay_status = 'N/A';
$total_days = intval($_POST['total_days'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');
$school_level = $_POST['school_level'] ?? 'ES';
$status = $_POST['status'] ?? 'Active';

// If reason is "Others", use the specified reason
if ($reason === 'Others' && !empty($other_reason)) {
    $reason = $other_reason;
}

// Validate required employee fields
if (empty($surname) || empty($first_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Surname and First Name are required.']);
    exit;
}

// Check if we should save a leave record
$is_leave_valid = !empty($period_from) && !empty($period_to) && !empty($reason) && !empty($station) && !empty($pay_status);

// Convert empty strings to NULL for DB
$db_dob = !empty($dob) ? $dob : null;
$db_pob = !empty($pob) ? $pob : null;
// Check if we already have an employee_id
if ($employee_id > 0) {
    // Update employee_no if provided
    if (!empty($employee_no)) {
        $upd_emp = $conn->prepare("UPDATE employees SET employee_no = ? WHERE id = ?");
        $upd_emp->bind_param("si", $employee_no, $employee_id);
        $upd_emp->execute();
        $upd_emp->close();
    }
} else {
    // Check if employee already exists by details
    if ($db_dob) {
        $check_sql = "SELECT id FROM employees WHERE surname = ? AND first_name = ? AND middle_initial = ? AND date_of_birth = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssss", $surname, $first_name, $middle_initial, $db_dob);
    } else {
        $check_sql = "SELECT id FROM employees WHERE surname = ? AND first_name = ? AND middle_initial = ? AND date_of_birth IS NULL";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $surname, $first_name, $middle_initial);
    }

    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        $employee_id = $row['id'];
        
        if (!empty($employee_no)) {
            $upd_emp = $conn->prepare("UPDATE employees SET employee_no = ? WHERE id = ?");
            $upd_emp->bind_param("si", $employee_no, $employee_id);
            $upd_emp->execute();
            $upd_emp->close();
        }
    } else {
        // Insert new employee
        $emp_sql = "INSERT INTO employees (surname, first_name, middle_initial, date_of_birth, place_of_birth, employee_no, school_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param("ssssssss", $surname, $first_name, $middle_initial, $db_dob, $db_pob, $employee_no, $school_level, $status);
        
        if (!$emp_stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save employee: ' . $emp_stmt->error]);
            exit;
        }
        $employee_id = $conn->insert_id;
        $emp_stmt->close();
    }
    $check_stmt->close();
}

// Insert leave record ONLY if data is valid
if ($is_leave_valid) {
    $leave_sql = "INSERT INTO leaves (employee_id, period_from, period_to, reason, station, pay_status, total_days, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $leave_stmt = $conn->prepare($leave_sql);
    $leave_stmt->bind_param("isssssis", $employee_id, $period_from, $period_to, $reason, $station, $pay_status, $total_days, $remarks);
    
    if ($leave_stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Leave record saved successfully!', 'employee_id' => $employee_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save leave: ' . $leave_stmt->error]);
    }
    $leave_stmt->close();
} else {
    echo json_encode(['status' => 'success', 'message' => 'Employee profile saved successfully!', 'employee_id' => $employee_id]);
}
$conn->close();
?>
