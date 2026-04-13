<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<?php
include 'comfig/database.php';

$recent_sql = "SELECT l.*, e.surname, e.first_name, e.middle_initial
               FROM leaves l
               JOIN employees e ON l.employee_id = e.id
               ORDER BY l.id DESC LIMIT 10";
$recent_result = $conn->query($recent_sql);
?>
<!-- MAIN DASHBOARD CONTENT -->
<div class="col-md-9 col-xl-10 ms-sm-auto main-wrapper">
    <?php include 'includes/navbar.php'; ?>

    <div class="row g-4 mt-2">
        <!-- Recent leave records (full width) -->
        <div class="col-12">
            <div class="card shadow-sm border-0 recent-table-card">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold m-0" style="color:#0a2f44;"><i class="fas fa-history me-2"></i>Recent Leave
                        Entries</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center m-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3">Employee Name</th>
                                    <th class="py-3">Period Covered</th>
                                    <th class="py-3">Reason</th>
                                    <th class="py-3">Pay Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_result && $recent_result->num_rows > 0): ?>
                                    <?php while ($row = $recent_result->fetch_assoc()):
                                        $full_name = $row['surname'] . ', ' . $row['first_name'];
                                        if (!empty($row['middle_initial']))
                                            $full_name .= ' ' . $row['middle_initial'] . '.';
                                        $from = (!empty($row['period_from']) && $row['period_from'] !== '0000-00-00') ? date('M d, Y', strtotime($row['period_from'])) : 'N/A';
                                        $to = (!empty($row['period_to']) && $row['period_to'] !== '0000-00-00') ? date('M d, Y', strtotime($row['period_to'])) : 'N/A';
                                        $period = $from . ' – ' . $to;
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($full_name); ?></td>
                                            <td><?php echo $period; ?></td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($row['reason']); ?></td>
                                            <td>
                                                <?php if ($row['pay_status'] === 'With Pay'): ?>
                                                    <span class="badge bg-success-subtle text-success px-3 py-2">With Pay</span>
                                                <?php elseif ($row['pay_status'] === 'Without Pay'): ?>
                                                    <span class="badge bg-danger-subtle text-danger px-3 py-2">Without Pay</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-muted px-3 py-2">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-5">
                                            <i class="fas fa-folder-open fa-3x mb-3 d-block opacity-25"></i>
                                            No recent leave records found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pb-3 pt-0 text-end">
                    <a href="pages/leave-monitoring.php" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                        View full record <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>