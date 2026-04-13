<?php
require_once __DIR__ . '/../../vendor/autoload.php';
session_start();

// Basic security check (admin logic)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once __DIR__ . '/../../config/db.php';

// Fetch all logs without pagination for export
$logs = [];
$result = $conn->query("SELECT l.*, u.username 
                       FROM activity_logs l 
                       JOIN users u ON l.user_id = u.id 
                       ORDER BY l.created_at DESC");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Generate HTML for PDF
ob_start();
?>
<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .meta {
            text-align: center;
            color: #7f8c8d;
            font-size: 10px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        tr:nth-child(even) {
            background-color: #fcfcfc;
        }

        .timestamp {
            font-size: 10px;
            color: #555;
        }

        .badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 9px;
            color: #fff;
            display: inline-block;
            text-transform: uppercase;
        }

        .bg-success {
            background-color: #198754;
        }

        .bg-warning {
            background-color: #ffc107;
            color: #000;
        }

        .bg-danger {
            background-color: #dc3545;
        }

        .bg-secondary {
            background-color: #6c757d;
        }

        .module-philhealth {
            background-color: #0dcaf0;
            color: #000;
        }

        .module-es-shs {
            background-color: #198754;
            color: #fff;
        }

        .module-qes {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</head>

<body>
    <h2>System Activity Logs Report</h2>
    <div class="meta">Generated on:
        <?php echo date('F d, Y h:i A'); ?> | Total Logs:
        <?php echo count($logs); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">Timestamp</th>
                <th width="15%">User</th>
                <th width="15%">Action</th>
                <th width="15%">Module</th>
                <th width="40%">Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log):
                $actionClass = 'bg-secondary';
                if ($log['action'] === 'Upload')
                    $actionClass = 'bg-success';
                if ($log['action'] === 'Delete Request')
                    $actionClass = 'bg-warning';
                if ($log['action'] === 'Approve Deletion')
                    $actionClass = 'bg-danger';

                $moduleClass = '';
                if ($log['module_type'] === 'philhealth')
                    $moduleClass = 'module-philhealth';
                if ($log['module_type'] === 'es_shs')
                    $moduleClass = 'module-es-shs';
                if ($log['module_type'] === 'qes')
                    $moduleClass = 'module-qes';
                ?>
                <tr>
                    <td class="timestamp">
                        <?php echo date("M d, Y", strtotime($log['created_at'])); ?><br>
                        <?php echo date("h:i A", strtotime($log['created_at'])); ?>
                    </td>
                    <td><strong>
                            <?php echo strtoupper($log['username']); ?>
                        </strong></td>
                    <td><span class="badge <?php echo $actionClass; ?>">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span></td>
                    <td>
                        <?php if ($log['module_type']): ?>
                            <span class="badge <?php echo $moduleClass; ?>">
                                <?php echo htmlspecialchars($log['module_type']); ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($log['details']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>
<?php
$html = ob_get_clean();

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);
    $mpdf->SetTitle('System Activity Logs');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Activity_Logs_Report_' . date('Ymd_His') . '.pdf', \Mpdf\Output\Destination::INLINE);
} catch (\Mpdf\MpdfException $e) {
    echo "Error generating PDF: " . $e->getMessage();
}
