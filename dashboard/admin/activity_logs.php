<?php include 'includes/header.php'; ?>
<?php
// Clear all logs if requested
if (isset($_GET['clear_all'])) {
    $conn->query("DELETE FROM activity_logs");
    header("Location: activity_logs.php");
    exit;
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch logs with user info
$logs = [];
// Admins can see all logs, regular users cannot see admin logs
$whereClause = ($_SESSION['role'] === 'admin') ? "" : "JOIN users u ON l.user_id = u.id WHERE u.role != 'admin'";
$countResult = $conn->query("SELECT COUNT(*) as total FROM activity_logs l $whereClause");
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$whereClauseJoin = ($_SESSION['role'] === 'admin') ? "JOIN users u ON l.user_id = u.id" : "JOIN users u ON l.user_id = u.id WHERE u.role != 'admin'";
$result = $conn->query("SELECT l.*, u.username 
                       FROM activity_logs l 
                       $whereClauseJoin
                       ORDER BY l.created_at DESC 
                       LIMIT $limit OFFSET $offset");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}
?>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="main-content">
        <div class="card card-modern p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i>System Activity Logs</h5>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-muted small">
                        Total Logs: <?php echo $totalRows; ?>
                    </div>
                    <a href="export_activity_logs.php" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </a>
                    <?php if ($totalRows > 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                            <i class="bi bi-trash"></i> Clear Logs
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-journal-x fs-1 text-muted opacity-50"></i>
                    <p class="mt-3 text-muted">No activity logs found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="custom-table-header">
                            <tr>
                                <th width="180px">Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log):
                                $badgeClass = 'bg-secondary';
                                if ($log['action'] === 'Upload')
                                    $badgeClass = 'bg-success';
                                if ($log['action'] === 'Delete Request')
                                    $badgeClass = 'bg-warning text-dark';
                                if ($log['action'] === 'Approve Deletion')
                                    $badgeClass = 'bg-danger';

                                $moduleBadge = 'bg-light text-dark border';
                                if ($log['module_type'] === 'philhealth')
                                    $moduleBadge = 'bg-info text-dark';
                                if ($log['module_type'] === 'es_shs')
                                    $moduleBadge = 'bg-success text-white';
                                if ($log['module_type'] === 'qes')
                                    $moduleBadge = 'bg-warning text-dark';
                                ?>
                                <tr>
                                    <td class="small text-muted">
                                        <?php echo date("M d, Y", strtotime($log['created_at'])); ?><br>
                                        <?php echo date("h:i A", strtotime($log['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2"
                                                style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                                            </div>
                                            <span class="fw-semibold"><?php echo strtoupper($log['username']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $log['action']; ?></span></td>
                                    <td>
                                        <?php if ($log['module_type']): ?>
                                            <span
                                                class="badge <?php echo $moduleBadge; ?>"><?php echo strtoupper($log['module_type']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-wrap" style="max-width: 300px;">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination pagination-sm justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clear Logs Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-danger" id="clearLogsModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Clear Activity Logs</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-secondary px-4 py-3">
                    Are you sure you want to permanently delete all system activity logs? This action cannot be undone.
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light shadow-sm px-4 fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <a href="?clear_all=1" class="btn btn-danger shadow-sm px-4 fw-medium">Delete All</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>