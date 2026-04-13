<?php
header('Content-Type: application/json');
include '../comfig/database.php';

if (!isset($_GET['surname']) || !isset($_GET['first_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

$surname = trim($_GET['surname']);
$first_name = trim($_GET['first_name']);
$mi = trim($_GET['mi'] ?? '');

// 1. Fetch Employee Info
$emp_sql = "SELECT id, surname, first_name, middle_initial, date_of_birth, place_of_birth, employee_no 
            FROM employees 
            WHERE surname = ? AND first_name = ? AND (middle_initial = ? OR middle_initial IS NULL OR middle_initial = '')
            LIMIT 1";
$emp_stmt = $conn->prepare($emp_sql);
$emp_stmt->bind_param("sss", $surname, $first_name, $mi);
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
$employee_info = $emp_result->fetch_assoc();
$emp_stmt->close();

if (!$employee_info) {
    echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
    exit;
}

// 2. Fetch Leave History
$leaves_sql = "SELECT id, employee_id, period_from, period_to, reason, station, pay_status, total_days, remarks 
              FROM leaves 
              WHERE employee_id = ? 
              ORDER BY period_from DESC";
$leaves_stmt = $conn->prepare($leaves_sql);
$leaves_stmt->bind_param("i", $employee_info['id']);
$leaves_stmt->execute();
$leaves_result = $leaves_stmt->get_result();

$leaves = [];
while ($row = $leaves_result->fetch_assoc()) {
    $leaves[] = $row;
}
$leaves_stmt->close();

echo json_encode([
    'status' => 'success',
    'employee' => [
        'surname' => $employee_info['surname'],
        'first_name' => $employee_info['first_name'],
        'middle_initial' => $employee_info['middle_initial'],
        'dob' => $employee_info['date_of_birth'],
        'pob' => $employee_info['place_of_birth'],
        'employee_no' => $employee_info['employee_no']
    ],
    'leaves' => $leaves
]);

$conn->close();
 ?>
