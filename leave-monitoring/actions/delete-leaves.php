<?php
include '../comfig/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No records selected.']);
        exit;
    }

    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        // Permanently delete ALL leave records for the selected employees
        $stmt = $conn->prepare("DELETE FROM leaves WHERE employee_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

        if ($stmt->execute()) {
            $stmt->close();

            // Permanently delete employees who have NO leave records left at all
            $conn->query("DELETE FROM employees WHERE id NOT IN (SELECT DISTINCT employee_id FROM leaves)");

            echo json_encode(['status' => 'success', 'message' => count($ids) . ' record(s) permanently deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete records.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>