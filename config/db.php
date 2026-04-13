<?php
$host = "localhost";        // usually localhost
$username = "root";         // default for XAMPP
$password = "";             // default is empty
$database = "sdo_remittance_db"; // palitan kung iba name ng DB mo

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set charset to avoid encoding issues
$conn->set_charset("utf8mb4");
?>