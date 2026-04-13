<?php
$conn = new mysqli("localhost", "root", "", "sdo_leave_monitoring_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>