<?php
include 'c:/xampp/htdocs/sdo-portal/leave-monitoring/comfig/database.php';

$surname = 'ABALOS';
$first_name = 'ANA MERIAM ELIZABETH';

$res = $conn->query("SELECT id FROM employees WHERE surname = '$surname' AND first_name = '$first_name'");
if ($row = $res->fetch_assoc()) {
    $emp_id = $row['id'];
    echo "Employee ID: $emp_id\n";
    
    $res2 = $conn->query("SELECT * FROM leaves WHERE employee_id = $emp_id");
    echo "Found " . $res2->num_rows . " leave records.\n";
    while ($leaf = $res2->fetch_assoc()) {
        echo "ID: " . $leaf['id'] . " | From: " . $leaf['period_from'] . " | Reason: " . $leaf['reason'] . " | Remarks: " . $leaf['remarks'] . "\n";
    }
} else {
    echo "Employee not found.\n";
}
?>
