<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
require_once "../../config/db.php";

if (!isset($_SESSION["role"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$memberNo = $_GET['member_no'] ?? '';
$moduleType = $_GET['module_type'] ?? '';

if (empty($memberNo)) {
    echo json_encode(['success' => false, 'message' => 'Member number is required.']);
    exit;
}

try {
    $sql = "SELECT rm.personal_share, rm.employer_share, rr.period, rr.module_type, rr.spa_no, rr.control_no, rr.date_received
            FROM remittance_members rm
            JOIN remittance_reports rr ON rm.report_id = rr.id
            WHERE rm.member_no = ? ";

    if (!empty($moduleType)) {
        $sql .= " AND rr.module_type = ? ";
    }

    $sql .= " ORDER BY STR_TO_DATE(CONCAT('01-', rr.period), '%d-%m-%Y') DESC";

    $stmt = $conn->prepare($sql);

    if (!empty($moduleType)) {
        $stmt->bind_param("ss", $memberNo, $moduleType);
    } else {
        $stmt->bind_param("s", $memberNo);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];

    while ($row = $result->fetch_assoc()) {
        // Convert MM-YYYY to human readable month
        $periodDate = DateTime::createFromFormat('m-Y', $row['period']);
        $formattedPeriod = $periodDate ? $periodDate->format('F Y') : $row['period'];

        // Format Date Posted
        $datePosted = !empty($row['date_received']) ? date('M d, Y', strtotime($row['date_received'])) : '---';

        $por = $row['control_no'] ?? '---';
        if (preg_match('/[a-zA-Z]/', $por, $matches, PREG_OFFSET_CAPTURE)) {
            $por = substr($por, $matches[0][1]);
        }

        $history[] = [
            'period' => $formattedPeriod,
            'date_posted' => $datePosted,
            'por' => $por,
            'ps' => number_format((float) $row['personal_share'], 2),
            'es' => number_format((float) $row['employer_share'], 2),
            'module' => strtoupper($row['module_type']),
            'ref' => $row['spa_no']
        ];
    }

    echo json_encode(['success' => true, 'data' => $history]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>