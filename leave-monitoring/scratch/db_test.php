<?php
include 'c:/xampp/htdocs/sdo-portal/leave-monitoring/comfig/database.php';
$tables = [];
$res = $conn->query('SHOW TABLES');
if ($res) {
    while ($row = $res->fetch_array())
        $tables[] = $row[0];
    echo "Tables: " . implode(', ', $tables) . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$counts = ['employees', 'leaves'];
foreach ($counts as $table) {
    if (in_array($table, $tables)) {
        $res = $conn->query("SELECT COUNT(*) FROM $table");
        if ($res) {
            echo "Count for $table: " . $res->fetch_row()[0] . "\n";
        } else {
            echo "Count failed for $table: " . $conn->error . "\n";
        }
    } else {
        echo "Table $table NOT FOUND\n";
    }
}
?>