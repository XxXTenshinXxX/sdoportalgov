<?php include 'includes/header.php'; ?>

<?php
$uId = $_SESSION['user_id'];

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $uId");
    header("Location: notifications.php");
    exit;
}

// Clear all notifications if requested
if (isset($_GET['clear_all'])) {
    $conn->query("DELETE FROM notifications WHERE user_id = $uId");
    header("Location: notifications.php");
    exit;
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalRes = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $uId");
$totalRows = $totalRes->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$nRes = $conn->query("SELECT * FROM notifications WHERE user_id = $uId ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$notifications = [];
if ($nRes) {
    while ($row = $nRes->fetch_assoc()) {
        $notifications[] = $row;
    }
}
?>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-bell-fill me-2"></i>All Notifications</h5>
                            <?php if ($totalRows > 0): ?>
                                <div>
                                    <a href="?mark_all_read=1" class="btn btn-sm btn-outline-primary shadow-sm me-2">
                                        <i class="bi bi-check2-all me-1"></i>Mark all as read
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#clearAllModal">
                                        <i class="bi bi-trash me-1"></i>Clear All
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($notifications)): ?>
                                <div id="no-notifications-placeholder" class="text-center py-5">
                                    <i class="bi bi-megaphone fs-1 text-muted opacity-25"></i>
                                    <p class="mt-3 text-muted">You have no notifications yet.</p>
                                </div>
                            <?php else: ?>
                                <div id="main-notification-list" class="list-group list-group-flush">
                                    <?php foreach ($notifications as $n): ?>
                                        <a href="<?php echo htmlspecialchars($n['link'] ?? '#'); ?>"
                                            class="list-group-item list-group-item-action p-4 <?php echo $n['is_read'] ? '' : 'border-start border-primary border-4'; ?>"
                                            style="<?php echo !$n['is_read'] ? 'background-color: #f4f6f9;' : ''; ?>">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <h6 class="mb-1 fw-bold">
                                                    <?php echo htmlspecialchars($n['title']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo date("M d, Y h:i A", strtotime($n['created_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-1 text-secondary">
                                                <?php echo htmlspecialchars($n['message']); ?>
                                            </p>
                                            <?php if (!$n['is_read']): ?>
                                                <span class="badge bg-primary rounded-pill mt-2">New</span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer bg-white py-3">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm justify-content-center mb-0">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear All Modal -->
    <div class="modal fade" id="clearAllModal" tabindex="-1" aria-labelledby="clearAllModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-danger" id="clearAllModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Clear Notifications</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-secondary px-4 py-3">
                    Are you sure you want to permanently delete all your notifications? This action cannot be undone.
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