<?php include 'includes/header.php'; ?>
<?php
// Pagination for Recent Uploads
$rLimit = 10;
$rPage = isset($_GET['rpage']) ? max(1, (int) $_GET['rpage']) : 1;
$rOffset = ($rPage - 1) * $rLimit;

// Filters for Recent Uploads
$rSearch = isset($_GET['rsearch']) ? $conn->real_escape_string($_GET['rsearch']) : '';
$rModule = isset($_GET['rmodule']) && $_GET['rmodule'] !== 'all' ? $conn->real_escape_string($_GET['rmodule']) : '';

// Build WHERE clause
$rWhere = ["delete_status = 'active'"];
if (!empty($rSearch)) {
    $rWhere[] = "(original_filename LIKE '%$rSearch%' OR file_path LIKE '%$rSearch%')";
}
if (!empty($rModule)) {
    $rWhere[] = "module_type = '$rModule'";
}
$rWhereClause = implode(" AND ", $rWhere);

$rTotalRes = $conn->query("SELECT COUNT(*) as c FROM remittance_reports WHERE $rWhereClause");
$rTotal = (int) ($rTotalRes ? $rTotalRes->fetch_assoc()['c'] : 0);
$rTotalPages = max(1, ceil($rTotal / $rLimit));

// Build query string for pagination
$filterParams = "";
if (!empty($rSearch))
    $filterParams .= "&rsearch=" . urlencode($rSearch);
if (!empty($rModule))
    $filterParams .= "&rmodule=" . urlencode($rModule);
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

        <!-- Stats Cards -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card card-modern p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">ES/SHS Reports</h6>
                            <h2 class="fw-bold text-success">
                                <?php
                                $es_shs_count = $conn->query("SELECT COUNT(*) as total FROM remittance_reports WHERE module_type = 'es_shs' AND delete_status = 'active'")->fetch_assoc()['total'];
                                echo $es_shs_count;
                                ?>
                            </h2>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-modern p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">QES Reports</h6>
                            <h2 class="fw-bold" style="color: #6a1b9a;">
                                <?php
                                $qes_count = $conn->query("SELECT COUNT(*) as total FROM remittance_reports WHERE module_type = 'qes' AND delete_status = 'active'")->fetch_assoc()['total'];
                                echo $qes_count;
                                ?>
                            </h2>
                        </div>
                        <div class="stat-icon" style="color: #6a1b9a;">
                            <i class="bi bi-mortarboard"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Uploads Table -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-modern p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <h5 class="fw-bold mb-0">Recent Uploads</h5>
                        <form action="" method="GET" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="rpage" value="1">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" name="rsearch" class="form-control border-start-0 ps-0"
                                    placeholder="Search filename..." value="<?php echo htmlspecialchars($rSearch); ?>">
                            </div>
                            <select name="rmodule" class="form-select form-select-sm" style="width: 150px;"
                                onchange="this.form.submit()">
                                <option value="all">All Modules</option>
                                <option value="es_shs" <?php echo $rModule === 'es_shs' ? 'selected' : ''; ?>>ES/SHS
                                </option>
                                <option value="qes" <?php echo $rModule === 'qes' ? 'selected' : ''; ?>>QES</option>
                            </select>
                            <?php if (!empty($rSearch) || !empty($rModule)): ?>
                                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_sql = "SELECT r.*, u.username as uploader
                                             FROM remittance_reports r
                                             LEFT JOIN users u ON r.uploaded_by = u.id
                                             WHERE r.$rWhereClause
                                             ORDER BY r.created_at DESC
                                             LIMIT $rLimit OFFSET $rOffset";
                                $recent_res = $conn->query($recent_sql);
                                $counter = $rOffset + 1;

                                if ($recent_res && $recent_res->num_rows > 0):
                                    while ($row = $recent_res->fetch_assoc()):
                                        $module_display = 'Others';
                                        $module_class = 'bg-secondary';
                                        if ($row['module_type'] === 'es_shs') {
                                            $module_display = 'ES/SHS';
                                            $module_class = 'bg-success';
                                        } elseif ($row['module_type'] === 'qes') {
                                            $module_display = 'QES';
                                            $module_class = 'text-white';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                                <?php echo htmlspecialchars($row['original_filename'] ?: basename($row['file_path'])); ?>
                                            </td>
                                            <td><span
                                                    class="badge <?php echo $module_class; ?>"
                                                    <?php if ($row['module_type'] === 'qes'): ?>style="background-color: #6a1b9a;"<?php endif; ?>
                                                    ><?php echo $module_display; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars(strtoupper($row['uploader'] ?? 'SYSTEM')); ?></td>
                                            <td><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                            <td>
                                                <?php
                                                $module = $row['module_type'];
                                                if ($module === 'es_shs')
                                                    $target_page = 'es-shs.php';
                                                elseif ($module === 'qes')
                                                    $target_page = 'qes.php';
                                                else
                                                    $target_page = 'es-shs.php';
                                                ?>
                                                <a href="<?php echo $target_page; ?>?highlight=<?php echo $row['id']; ?>"
                                                    class="btn btn-sm btn-primary" title="View in Table"><i
                                                        class="bi bi-eye"></i></a>
                                                <a href="download_report.php?id=<?php echo $row['id']; ?>"
                                                    class="btn btn-sm btn-success" title="Download Report"><i
                                                        class="bi bi-download"></i></a>
                                            </td>
                                        </tr>
                                        <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <em>No recent uploads found.</em>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($rTotalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">Showing
                                <?php echo $rOffset + 1; ?>–<?php echo min($rOffset + $rLimit, $rTotal); ?> of
                                <?php echo $rTotal; ?> uploads</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $rPage <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="?rpage=<?php echo $rPage - 1 . $filterParams; ?>">&laquo;</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $rTotalPages; $p++): ?>
                                        <li class="page-item <?php echo $p === $rPage ? 'active' : ''; ?>">
                                            <a class="page-link"
                                                href="?rpage=<?php echo $p . $filterParams; ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $rPage >= $rTotalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="?rpage=<?php echo $rPage + 1 . $filterParams; ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>