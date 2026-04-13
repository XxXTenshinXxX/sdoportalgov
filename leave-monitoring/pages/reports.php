<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<?php
include '../comfig/database.php';

// Handle filter
$filter_month = $_GET['month'] ?? '';
$filter_year = $_GET['year'] ?? '';

$where_clause = "1=1";
$params = [];
$types = "";

if (!empty($filter_month)) {
    $where_clause .= " AND MONTH(l.period_from) = ?";
    $params[] = $filter_month;
    $types .= "s";
}
if (!empty($filter_year)) {
    $where_clause .= " AND YEAR(l.period_from) = ?";
    $params[] = $filter_year;
    $types .= "s";
}

$sql = "SELECT l.*, e.surname, e.first_name, e.middle_initial, e.date_of_birth, e.place_of_birth 
        FROM leaves l 
        JOIN employees e ON l.employee_id = e.id 
        WHERE $where_clause
        ORDER BY l.created_at DESC";

// Use prepared statement if there are filters
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>

<!-- MAIN CONTENT -->
<div class="col-md-9 col-xl-10 ms-sm-auto main-wrapper">

    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3 d-print-none">
        <h5 class="fw-bold m-0" style="color:#0a2f44;"><i class="fas fa-file-alt me-2"></i> Leave Reports</h5>
        <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-2"></i> Print
            Report</button>
    </div>

    <!-- FILTER FORM (Hidden when printing) -->
    <div class="card shadow-sm border-0 mb-4 d-print-none">
        <div class="card-body bg-light">
            <form method="GET" action="reports.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Month</label>
                    <select class="form-select" name="month">
                        <option value="">All Months</option>
                        <?php
                        for ($m = 1; $m <= 12; $m++) {
                            $selected = ($filter_month == $m) ? 'selected' : '';
                            echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Year</label>
                    <select class="form-select" name="year">
                        <option value="">All Years</option>
                        <?php
                        $currentYear = date('Y') + 1; // Future year buffer
                        for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                            $selected = ($filter_year == $y) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"
                        style="background:#0a2f44; border:none;">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="reports.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- REPORT PRINT HEADER (Hidden on screen, visible on print) -->
    <div class="d-none d-print-block text-center mb-4">
        <div class="mb-3">
            <h4 class="fw-bold mb-1">SDO Leave Monitoring System</h4>
            <h5 class="fw-bold mb-1">Generated Leave Report</h5>
            <p class="mb-0">
                Period: <?php
                if ($filter_month) {
                    echo date('F', mktime(0, 0, 0, $filter_month, 1)) . " ";
                }
                if ($filter_year) {
                    echo $filter_year;
                }
                if (!$filter_month && !$filter_year) {
                    echo "All Time";
                }
                ?>
            </p>
        </div>
    </div>

    <!-- DATA TABLE -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle m-0 text-center" style="font-size: 0.8rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="py-2">Employee Name</th>
                            <th class="py-2">Period Covered (From - To)</th>
                            <th class="py-2">Reason</th>
                            <th class="py-2">Pay Status</th>
                            <th class="py-2">Station / Place of Assignment</th>
                            <th class="py-2">Total Days</th>
                            <th class="py-2">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $full_name = $row['surname'] . ', ' . $row['first_name'];
                                if (!empty($row['middle_initial']))
                                    $full_name .= ' ' . $row['middle_initial'] . '.';
                                $period = date('M d, Y', strtotime($row['period_from'])) . ' - ' . date('M d, Y', strtotime($row['period_to']));
                                ?>
                                <tr>
                                    <td class="text-start"><?php echo htmlspecialchars($full_name); ?></td>
                                    <td><?php echo $period; ?></td>
                                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                    <td><?php echo htmlspecialchars($row['pay_status']); ?></td>
                                    <td><?php echo htmlspecialchars($row['station']); ?></td>
                                    <td><?php echo htmlspecialchars($row['total_days']); ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No records found for this period.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- CUSTOM PRINT CSS -->
<style>
    @media print {
        body {
            background-color: #fff !important;
        }

        .sidebar {
            display: none !important;
        }

        .main-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 0 !important;
        }

        .card {
            box-shadow: none !important;
            border: none !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        .table {
            border-collapse: collapse !important;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #000 !important;
            padding: 8px !important;
        }

        /* Ensure colors print correctly */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>