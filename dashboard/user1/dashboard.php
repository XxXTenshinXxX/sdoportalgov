<?php include 'includes/header.php'; ?>
<?php
// Statistics
$es_shs_count = 0;
$countQuery = $conn->query("SELECT COUNT(*) as total FROM remittance_reports WHERE module_type = 'es_shs' AND delete_status = 'active'");
if ($countQuery) {
    $es_shs_count = $countQuery->fetch_assoc()['total'];
}

// Pagination for Recent Uploads
$rLimit = 10;
$rPage = isset($_GET['rpage']) ? max(1, (int) $_GET['rpage']) : 1;
$rOffset = ($rPage - 1) * $rLimit;

// Filters for Recent Uploads
$rSearch = isset($_GET['rsearch']) ? $conn->real_escape_string($_GET['rsearch']) : '';

// Build WHERE clause
$rWhere = "module_type = 'es_shs' AND delete_status = 'active'";
if (!empty($rSearch)) {
    $rWhere .= " AND (original_filename LIKE '%$rSearch%' OR file_path LIKE '%$rSearch%')";
}

// Fetch Recent Uploads for ES/SHS
$recentUploads = [];
$ruQuery = $conn->query("SELECT r.*, u.username as uploader_name
                        FROM remittance_reports r
                        JOIN users u ON r.uploaded_by = u.id
                        WHERE r.$rWhere
                        ORDER BY r.created_at DESC
                        LIMIT $rLimit OFFSET $rOffset");

if ($ruQuery && $ruQuery->num_rows > 0) {
    while ($row = $ruQuery->fetch_assoc()) {
        $recentUploads[] = $row;
    }
}

// Total for pagination
$totalRecent = 0;
$trQuery = $conn->query("SELECT COUNT(*) as total FROM remittance_reports WHERE $rWhere");
if ($trQuery) {
    $totalRecent = $trQuery->fetch_assoc()['total'];
}
$rTotalPages = max(1, ceil($totalRecent / $rLimit));
$counter = $rOffset + 1;

// Build query string for pagination
$filterParams = "";
if (!empty($rSearch))
    $filterParams .= "&rsearch=" . urlencode($rSearch);
?>

<body>

    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <style>
        .custom-table-header th {
            background-color: #1a237e !important;
            color: white !important;
        }
    </style>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0">Dashboard Overview</h4>
                    <p class="text-muted small mb-0">Welcome back, your portal for ES/SHS remittance reports.</p>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card card-modern border-0 shadow-sm p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">ES/SHS Reports</h6>
                                <h2 class="fw-bold mb-0 text-success"><?php echo $es_shs_count; ?></h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background-color: rgba(25,135,84,0.1);">
                                <i class="bi bi-building fs-3 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-modern p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i>Recent ES/SHS Uploads
                    </h5>
                    <div class="d-flex gap-2 align-items-center">
                        <form action="" method="GET" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="rpage" value="1">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" name="rsearch" class="form-control border-start-0 ps-0"
                                    placeholder="Search filename..." value="<?php echo htmlspecialchars($rSearch); ?>">
                            </div>
                            <?php if (!empty($rSearch)): ?>
                                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary" title="Clear Search">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                        <a href="es-shs.php" class="btn btn-sm btn-outline-primary rounded-pill">View All Reports</a>
                    </div>
                </div>

                <?php if (empty($recentUploads)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cloud-slash fs-1 text-muted opacity-50"></i>
                        <p class="mt-3 text-muted">No recent uploads found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="custom-table-header">
                                <tr>
                                    <th>#</th>
                                    <th>Filename</th>
                                    <th>Module</th>
                                    <th>Uploaded By</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUploads as $upload): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                            <?php echo htmlspecialchars($upload['original_filename'] ?: basename($upload['file_path'])); ?>
                                        </td>
                                        <td><span class="badge bg-success">ES/SHS</span></td>
                                        <td class="small">
                                            <?php echo strtoupper($upload['uploader_name']); ?>
                                        </td>
                                        <td><?php echo date("M d, Y", strtotime($upload['created_at'])); ?></td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                        <td class="text-center">
                                            <a href="es-shs.php?highlight=<?php echo $upload['id']; ?>"
                                                class="btn btn-sm btn-primary" title="View in Table">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="download_report.php?id=<?php echo $upload['id']; ?>"
                                                class="btn btn-sm btn-success" title="Download Report">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($rTotalPages > 1): ?>
                        <nav aria-label="Recent uploads pagination" class="mt-4">
                            <ul class="pagination pagination-sm justify-content-center">
                                <li class="page-item <?php echo ($rPage <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?rpage=<?php echo $rPage - 1 . $filterParams; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $rTotalPages; $i++): ?>
                                    <li class="page-item <?php echo ($rPage == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?rpage=<?php echo $i . $filterParams; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($rPage >= $rTotalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?rpage=<?php echo $rPage + 1 . $filterParams; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>