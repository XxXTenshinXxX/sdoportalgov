<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<?php
include '../comfig/database.php';

// Helper for leave reason shortcuts
function getReasonShortcut($reason) {
    if (empty($reason)) return '';
    $shortcuts = [
        'Sick leave without pay' => 'SWOP',
        'Sick leave with pay' => 'SLWP',
        'Vacation leave without pay' => 'VWOP',
        'Vacation leave with pay' => 'VWP',
        'Maternity leave' => 'ML',
        'Study leave' => 'STUDY',
        'Wellness leave' => 'WL',
        'Special privilege leave' => 'SPL',
        'Forced leave' => 'FL'
    ];
    foreach ($shortcuts as $full => $short) {
        if (strcasecmp(trim($reason), $full) === 0) return $short;
    }
    return $reason;
}

// Handle filters
$search = $_GET['search'] ?? '';
$reason_filter = $_GET['reason'] ?? '';
$level_filter = $_GET['level'] ?? '';
$status_filter = $_GET['status'] ?? '';
// Page Title based on level
$page_title = "Employee Leave Records";
if ($level_filter == 'ES') $page_title = "Elementary (ES) Leave Records";
if ($level_filter == 'SEC') $page_title = "Secondary (SEC) Leave Records";

$where_clause = "1=1";
$params = [];
$types = "";

// Filter Logic Cleanup (No more soft-delete check)
if (!empty($status_filter)) {
    $where_clause .= " AND e.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Level Filter
if (!empty($level_filter)) {
    $where_clause .= " AND e.school_level = ?";
    $params[] = $level_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_clause .= " AND (
        e.surname LIKE ? OR 
        e.first_name LIKE ? OR 
        l.station LIKE ? OR 
        CONCAT(e.first_name, ' ', e.surname) LIKE ? OR 
        CONCAT(e.surname, ', ', e.first_name) LIKE ? OR
        l.reason LIKE ? OR
        l.remarks LIKE ?
    )";
    $like_search = "%$search%";
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= "sssssss";
}

if (!empty($reason_filter)) {
    if ($reason_filter === 'Others') {
        $standard_reasons = [
            'Sick leave without pay',
            'Sick leave with pay',
            'Vacation leave without pay',
            'Vacation leave with pay',
            'Maternity leave',
            'Study leave',
            'Wellness leave',
            'Special privilege leave',
            'Forced leave'
        ];
        $placeholders = implode(',', array_fill(0, count($standard_reasons), '?'));
        $where_clause .= " AND l.reason NOT IN ($placeholders)";
        foreach ($standard_reasons as $sr) {
            $params[] = $sr;
            $types .= "s";
        }
    } else {
        $where_clause .= " AND l.reason = ?";
        $params[] = $reason_filter;
        $types .= "s";
    }
}

$sql = "SELECT MAX(l.id) AS leave_id, l.period_from, l.period_to, l.reason, l.station, l.pay_status, l.total_days, l.remarks, 
               e.id AS employee_id, e.surname, e.first_name, e.middle_initial, e.date_of_birth, e.place_of_birth, e.employee_no, e.school_level, e.status
        FROM employees e
        LEFT JOIN leaves l ON e.id = l.employee_id
        WHERE $where_clause
        GROUP BY e.id
        ORDER BY e.surname ASC, e.first_name ASC";

$result = null;
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query($sql);
}
?>

<style>
    #leaveMonitoringTable tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    #leaveMonitoringTable tbody tr:hover {
        background-color: #f0f7ff !important;
    }
    .view-btn, .add-leave-btn, .edit-employee-btn, .delete-btn {
        position: relative;
        z-index: 2;
    }
</style>

<!-- MAIN CONTENT -->
<div class="col-md-9 col-xl-10 ms-sm-auto main-wrapper">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0" style="color:#0a2f44;"><i class="fas fa-user-clock me-2"></i> <?php echo $page_title; ?></h5>
        <div class="d-flex gap-2">
            <button class="btn btn-danger d-none" id="deleteSelectedBtn">
                <i class="fas fa-trash-alt me-2"></i>Delete Selected (<span id="selectedCount">0</span>)
            </button>
            <button class="btn btn-danger" id="toggleDeleteMode" title="Enable/Disable Delete Mode">
                <i class="fas fa-trash-alt me-2"></i>Delete
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadExcelModal">
                <i class="fas fa-file-excel me-2"></i>Upload
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeLeaveModal" style="background:#0a2f44; border:none;">
                <i class="fas fa-plus me-2"></i>Add Employee
            </button>
        </div>
    </div>

    <!-- Success/Error Alert -->
    <div id="alertContainer"></div>

    <!-- FILTER FORM -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light">
            <form method="GET" action="leave-monitoring.php" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Search (Employee Name or Station)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Enter keywords..." value="<?php echo htmlspecialchars($search); ?>" oninput="clearTimeout(this.delay); this.delay = setTimeout(() => this.form.submit(), 500);">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter by Reason</label>
                    <select class="form-select" name="reason" onchange="this.form.submit()">
                        <option value="">All Reasons</option>
                        <option value="Sick leave without pay" <?php if ($reason_filter == 'Sick leave without pay') echo 'selected'; ?>>Sick leave (SWOP/SLWP)</option>
                        <option value="Vacation leave without pay" <?php if ($reason_filter == 'Vacation leave without pay') echo 'selected'; ?>>Vacation leave (VWOP/VWP)</option>
                        <option value="Maternity leave" <?php if ($reason_filter == 'Maternity leave') echo 'selected'; ?>>Maternity leave (ML)</option>
                        <option value="Study leave" <?php if ($reason_filter == 'Study leave') echo 'selected'; ?>>Study leave (STUDY)</option>
                        <option value="Wellness leave" <?php if ($reason_filter == 'Wellness leave') echo 'selected'; ?>>Wellness leave (WL)</option>
                        <option value="Special privilege leave" <?php if ($reason_filter == 'Special privilege leave') echo 'selected'; ?>>Special privilege (SPL)</option>
                        <option value="Forced leave" <?php if ($reason_filter == 'Forced leave') echo 'selected'; ?>>Forced leave (FL)</option>
                        <option value="Others" <?php if ($reason_filter == 'Others') echo 'selected'; ?>>Others</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filter by Status</label>
                    <div class="d-flex gap-2">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="Active" <?php if ($status_filter == 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactivation" <?php if ($status_filter == 'Inactivation') echo 'selected'; ?>>Inactivation</option>
                            <option value="Separation" <?php if ($status_filter == 'Separation') echo 'selected'; ?>>Separation</option>
                        </select>
                        <input type="hidden" name="level" value="<?php echo htmlspecialchars($level_filter); ?>">
                        <a href="leave-monitoring.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- DATA TABLE -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="leaveMonitoringTable" class="table table-bordered table-hover align-middle m-0 text-center" style="font-size: 0.75rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="py-2 checkbox-col d-none" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll">
                            </th>
                            <th class="py-2">Employee No.</th>
                            <th class="py-2">Surname</th>
                            <th class="py-2">First Name</th>
                            <th class="py-2">M.I.</th>
                            <th class="py-2">Date of Birth / Place of Birth</th>
                            <th class="py-2">Module</th>
                            <th class="py-2 text-center">Status</th>
                            <th class="py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="clickable-row" style="cursor: pointer;"
                                    data-employee-id="<?php echo $row['employee_id']; ?>"
                                    data-surname="<?php echo htmlspecialchars($row['surname']); ?>"
                                    data-firstname="<?php echo htmlspecialchars($row['first_name']); ?>"
                                    data-mi="<?php echo htmlspecialchars($row['middle_initial']); ?>"
                                    data-dob="<?php echo htmlspecialchars($row['date_of_birth']); ?>"
                                    data-pob="<?php echo htmlspecialchars($row['place_of_birth']); ?>"
                                    data-employeeno="<?php echo htmlspecialchars($row['employee_no']); ?>"
                                    data-schoollevel="<?php echo htmlspecialchars($row['school_level']); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                    <td class="checkbox-col d-none">
                                        <input type="checkbox" class="form-check-input row-checkbox" value="<?php echo $row['employee_id']; ?>">
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['employee_no'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['surname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['middle_initial']); ?></td>
                                    <td><?php 
                                        $dob_display = (!empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00') ? date('M d, Y', strtotime($row['date_of_birth'])) : 'N/A';
                                        $pob_display = (!empty($row['place_of_birth'])) ? $row['place_of_birth'] : 'N/A';
                                        echo htmlspecialchars($dob_display) . ' / ' . htmlspecialchars($pob_display); 
                                    ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-bold" style="font-size: 0.7rem;">
                                            <?php echo htmlspecialchars($row['school_level']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $status = $row['status'] ?: 'Active';
                                            $badge_class = 'bg-success';
                                            if ($status === 'Inactivation') $badge_class = 'bg-warning text-dark';
                                            if ($status === 'Separation') $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex justify-content-center gap-1">
                                            <!-- VIEW HISTORY -->
                                            <button class="btn btn-sm btn-info text-white view-btn" title="View historical records"
                                                data-id="<?php echo $row['leave_id']; ?>"
                                                data-employee-id="<?php echo $row['employee_id']; ?>"
                                                data-surname="<?php echo htmlspecialchars($row['surname']); ?>"
                                                data-firstname="<?php echo htmlspecialchars($row['first_name']); ?>"
                                                data-mi="<?php echo htmlspecialchars($row['middle_initial']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- ADD LEAVE -->
                                            <button class="btn btn-sm btn-success text-white add-leave-btn" title="Add new leave record"
                                                data-employee-id="<?php echo $row['employee_id']; ?>"
                                                data-surname="<?php echo htmlspecialchars($row['surname']); ?>"
                                                data-firstname="<?php echo htmlspecialchars($row['first_name']); ?>"
                                                data-mi="<?php echo htmlspecialchars($row['middle_initial']); ?>">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>

                                            <!-- EDIT EMPLOYEE (Always Available) -->
                                            <button class="btn btn-sm btn-primary text-white edit-employee-details-btn" title="Edit employee details"
                                                data-employee-id="<?php echo $row['employee_id']; ?>"
                                                data-surname="<?php echo htmlspecialchars($row['surname']); ?>"
                                                data-firstname="<?php echo htmlspecialchars($row['first_name']); ?>"
                                                data-mi="<?php echo htmlspecialchars($row['middle_initial']); ?>"
                                                data-dob="<?php echo htmlspecialchars($row['date_of_birth']); ?>"
                                                data-pob="<?php echo htmlspecialchars($row['place_of_birth']); ?>"
                                                data-employeeno="<?php echo htmlspecialchars($row['employee_no']); ?>"
                                                data-schoollevel="<?php echo htmlspecialchars($row['school_level']); ?>"
                                                data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                                <i class="fas fa-user-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php
    endwhile; ?>
                        <?php
else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-secondary py-4">No leave records found.</td>
                            </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ADD EMPLOYEE LEAVE MODAL -->
    <div class="modal fade" id="addEmployeeLeaveModal" tabindex="-1" aria-labelledby="addEmployeeLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:#0a2f44; color:white;">
                    <h5 class="modal-title" id="addEmployeeLeaveModalLabel"><i class="fas fa-user-plus me-2"></i>Add Employee</h5>
                    <div class="d-flex align-items-center gap-2">
                        <input type="file" id="importFromExcelInput" accept=".xlsx, .xls" class="d-none">
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <form id="employeeLeaveForm">
                        <input type="hidden" name="employee_id" id="newEmployeeId">
                        
                        <div id="employeeDetailsSection">
                            <!-- Employee Info -->
                            <div class="row g-3 mb-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">School Level</label>
                                    <select class="form-select" name="school_level" required>
                                        <option value="ES" <?php echo ($level_filter == 'ES') ? 'selected' : ''; ?>>Elementary (ES)</option>
                                        <option value="SEC" <?php echo ($level_filter == 'SEC') ? 'selected' : ''; ?>>Secondary (SEC)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="Active" selected>Active</option>
                                        <option value="Inactivated">Inactivated</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Employee No.</label>
                                    <input type="text" class="form-control" name="employee_no" placeholder="Optional Employee Number">
                                </div>
                            </div>

                            <!-- Name Section -->
                            <h6 class="bg-light p-2 border-start border-secondary border-4 mb-3 fw-bold text-dark rounded-end">
                                <i class="fas fa-id-badge me-2 text-secondary"></i>Employee Details
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Surname</label>
                                    <input type="text" class="form-control" name="surname" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">First Name</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Middle Initial</label>
                                    <input type="text" class="form-control" name="middle_initial" maxlength="2">
                                </div>
                            </div>

                            <!-- Birth Details -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Date of Birth</label>
                                    <input type="date" class="form-control" name="dob">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Place of Birth</label>
                                    <input type="text" class="form-control" name="pob">
                                </div>
                            </div>
                        </div>
                        <!-- Leave Details Section -->
                        <div id="leaveParticularsSection" class="d-none">
                            <h6 class="bg-light p-2 border-start border-secondary border-4 mb-3 fw-bold text-dark rounded-end">
                                <i class="fas fa-calendar-alt me-2 text-secondary"></i>Leave Particulars
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Period Covered (From)</label>
                                    <input type="date" class="form-control" name="period_from" id="leaveFrom">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Period Covered (To)</label>
                                    <input type="date" class="form-control" name="period_to" id="leaveTo">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Station / Place of Assignment</label>
                                    <input type="text" class="form-control" name="station">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Reason</label>
                                    <select class="form-select" name="reason" id="leaveReason">
                                        <option value="" selected disabled>Select Reason...</option>
                                        <option value="Sick leave without pay">Sick leave without pay</option>
                                        <option value="Sick leave with pay">Sick leave with pay</option>
                                        <option value="Vacation leave without pay">Vacation leave without pay</option>
                                        <option value="Vacation leave with pay">Vacation leave with pay</option>
                                        <option value="Maternity leave">Maternity leave</option>
                                        <option value="Study leave">Study leave</option>
                                        <option value="Wellness leave">Wellness leave</option>
                                        <option value="Special privilege leave">Special privilege leave</option>
                                        <option value="Forced leave">Forced leave</option>
                                        <option value="Others">Others (Please specify)</option>
                                    </select>
                                    <input type="text" class="form-control mt-2 d-none" name="other_reason" id="otherReason" placeholder="Please specify reason">
                                </div>
                            </div>

                            <!-- Calculation Section -->
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Pay Status</label>
                                    <select class="form-select" name="pay_status" id="payStatus">
                                        <option value="With Pay">With Pay</option>
                                        <option value="Without Pay">Without Pay</option>
                                        <option value="N/A">N/A</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Total Days</label>
                                    <input type="text" class="form-control bg-light" name="total_days" id="totalDays" readonly placeholder="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Remarks</label>
                                    <input type="text" class="form-control" name="remarks" placeholder="Optional remarks">
                                </div>
                            </div>
                        </div><!-- /addLeaveParticularsSectionAdd -->
                        <input type="hidden" name="employee_id" id="addLeaveEmployeeId">
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveLeaveBtn" style="background:#0a2f44; border:none;"><i class="fas fa-save me-2"></i>Save Record</button>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW LEAVE MODAL -->
    <div class="modal fade" id="viewLeaveModal" tabindex="-1" aria-labelledby="viewLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 bg-white text-dark" style="font-family: Arial, sans-serif; font-size: 0.8rem; line-height: 1.2;">
                    
                    <!-- Print Button & Close -->
                    <div class="d-flex justify-content-end mb-2 d-print-none gap-2">
                        <button type="button" class="btn btn-sm btn-success" id="addLeaveFromViewBtn"><i class="fas fa-plus-circle"></i> Add Leave</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printViewModal()"><i class="fas fa-print"></i> Print</button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div id="printableForm">
                        <!-- header -->
                        <div class="text-center fw-bold mb-3">
                            <h5 class="fw-bold m-0" style="font-family: Arial, sans-serif;">LEAVE MONITORING</h5>
                            <div>(To be Accomplished by the Employer)</div>
                        </div>

                        <div class="text-end mb-2 pe-3">
                            Employee no: <span id="viewExcelEmpNo" style="display:inline-block; min-width:100px; border-bottom:1px solid #000;"></span>
                        </div>

                        <!-- Top details table -->
                        <table class="table-borderless w-100 mb-4" style="border-collapse: collapse;">
                            <tbody>
                                <tr>
                                    <td class="fw-bold fs-6" style="width: 10%; vertical-align: bottom;">NAME</td>
                                    <td class="text-center fw-bold fs-6" style="border-bottom: 2px solid #000; width: 20%;" id="viewExcelSurname"></td>
                                    <td class="text-center fw-bold fs-6" style="border-bottom: 2px solid #000; width: 25%;" id="viewExcelFirstName"></td>
                                    <td class="text-center fw-bold fs-6" style="border-bottom: 2px solid #000; width: 10%;" id="viewExcelMi"></td>
                                    <td style="width: 35%; padding-left: 10px; font-size: 0.75rem;">(If married woman, give<br>also full maiden name)</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td class="text-center" style="font-size: 0.75rem;">(Surname)</td>
                                    <td class="text-center" style="font-size: 0.75rem;">(First Name)</td>
                                    <td class="text-center" style="font-size: 0.75rem;">(M.I.)</td>
                                    <td></td>
                                </tr>
                                <tr><td colspan="5" style="height:15px;"></td></tr>
                                <tr>
                                    <td class="fw-bold fs-6" style="vertical-align: bottom;">BIRTH</td>
                                    <td colspan="2" class="text-center fs-6" style="border-bottom: 2px solid #000;" id="viewExcelDob"></td>
                                    <td class="text-center fs-6" style="border-bottom: 2px solid #000;" id="viewExcelPob"></td>
                                    <td style="padding-left: 10px; font-size: 0.75rem;">(Date herein should be<br>checked from birth/bap-<br>tismal certificate or some<br>reliable documents.)</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="2" class="text-center" style="font-size: 0.75rem;">(Date of Birth)</td>
                                    <td class="text-center" style="font-size: 0.75rem;">(Place of Birth)</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        <!-- Filter Bar -->
                        <div class="row g-2 mb-3 d-print-none bg-light p-2 rounded shadow-sm border" style="margin: 0 10px;">
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-secondary"></i></span>
                                    <input type="text" id="historySearch" class="form-control form-control-sm border-start-0" placeholder="Search keywords...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select id="historyYearFilter" class="form-select form-select-sm">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="historyReasonFilter" class="form-select form-select-sm">
                                    <option value="">All Reasons</option>
                                    <option value="Sick leave without pay">Sick leave without pay (SWOP)</option>
                                    <option value="Sick leave with pay">Sick leave with pay (SLWP)</option>
                                    <option value="Vacation leave without pay">Vacation leave without pay (VWOP)</option>
                                    <option value="Vacation leave with pay">Vacation leave with pay (VWP)</option>
                                    <option value="Maternity leave">Maternity leave (ML)</option>
                                    <option value="Study leave">Study leave (STUDY)</option>
                                    <option value="Wellness leave">Wellness leave (WL)</option>
                                    <option value="Special privilege leave">Special privilege leave (SPL)</option>
                                    <option value="Forced leave">Forced leave (FL)</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="resetHistoryFilters">Clear</button>
                            </div>
                        </div>

                        <!-- Data table -->
                        <table class="table table-bordered border-dark text-center align-middle mb-4" style="border: 2px solid #000;">
                            <thead style="background-color: #f2f2f2; font-weight: bold;">
                                <tr>
                                    <td colspan="2" style="border: 2px dotted #000;">PERIOD COVERED<br><span style="font-weight: normal;">(Inclusive Date)</span></td>
                                    <td rowspan="2" style="border: 2px dotted #000;">REASON</td>
                                    <td rowspan="2" style="border: 2px dotted #000;">STATION<br>PLACE OF<br>ASSIGNMENT</td>
                                    <td rowspan="2" style="border: 2px dotted #000;">ABSENCE<br>WITHOUT<br>PAY</td>
                                    <td rowspan="2" style="border: 2px dotted #000;">ABSENCE<br>WITH PAY</td>
                                    <td rowspan="2" style="border: 2px dotted #000;">REMARKS</td>
                                    <td rowspan="2" class="d-print-none" style="border: 2px dotted #000; width: 80px;">ACTIONS</td>
                                </tr>
                                <tr>
                                    <td style="border: 2px dotted #000;">From</td>
                                    <td style="border: 2px dotted #000;">To</td>
                                </tr>
                            </thead>
                            <tbody id="viewLeaveTableBody">
                                <!-- Dynamic rows will be inserted here via JavaScript -->
                            </tbody>
                                <tr style="height: 30px;"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                                <tr style="height: 30px;"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                                <tr style="height: 30px;"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                                <tr>
                                    <td>x</td>
                                    <td>x</td>
                                    <td>x</td>
                                    <td>x</td>
                                    <td>x</td>
                                    <td>x</td>
                                    <td>x</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Footer details -->
                        <div class="mb-4 ps-2">
                            Issued in compliance with Executive Order No. 54 dated August 10, 1954 and in accordance with Circular No. 58 dated August 10,<br>1954 of the system.
                        </div>

                        <div class="row align-items-end mt-5 pt-3">
                            <div class="col-6">
                                <div class="mb-5">
                                    <span class="fw-bold">Purpose:</span> &nbsp;&nbsp;&nbsp;&nbsp;GSIS Updating
                                </div>
                                <div class="mt-4" style="width: 150px; text-align: center;">
                                    <div style="border-bottom: 1px solid #000; letter-spacing: 2px;" class="fw-bold">*********</div>
                                    <div style="font-size: 0.75rem;">(Date)</div>
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="text-start d-inline-block">
                                    <div class="fw-bold fs-6">CERTIFIED CORRECT:</div>
                                    <div class="mb-4">As to the service rendered in Quezon City</div>
                                    <div class="mb-4 pb-4">For the Schools Division Superintendent:</div>
                                    
                                    <div class="text-center mt-5 pt-2">
                                        <div class="fw-bold text-decoration-underline fs-6">MARIVEL E. UNCIANO</div>
                                        <div>Administrative Officer V</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- /printableForm -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function printViewModal() {
            var printContents = document.getElementById('printableForm').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = '<div style="background:white; margin:0; padding:20px; font-family:Arial;">' + printContents + '</div>';
            window.print();
            document.body.innerHTML = originalContents;
            window.location.reload();
        }
    </script>

    <!-- EDIT LEAVE MODAL -->
    <div class="modal fade" id="editLeaveModal" tabindex="-1" aria-labelledby="editLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:#ffc107; color:#212529;">
                    <h5 class="modal-title fw-bold" id="editLeaveModalLabel"><i class="fas fa-edit me-2"></i>Edit Leave Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editLeaveForm">
                        <input type="hidden" name="leave_id" id="editLeaveId">
                        <input type="hidden" name="employee_id" id="editEmployeeId">
                        
                        <div id="editEmployeeDetailsSection">
                            <!-- Employee Info -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Employee No.</label>
                                    <input type="text" class="form-control" name="employee_no" id="editEmpNo" placeholder="Optional Employee Number">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">School Level</label>
                                    <select class="form-select" name="school_level" id="editSchoolLevel" required>
                                        <option value="ES">Elementary (ES)</option>
                                        <option value="SEC">Secondary (SEC)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select class="form-select" name="status" id="editStatus" required>
                                        <option value="Active">Active</option>
                                        <option value="Inactivated">Inactivated</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Name Section -->
                            <h6 class="bg-light p-2 border-start border-secondary border-4 mb-3 fw-bold text-dark rounded-end">
                                <i class="fas fa-id-badge me-2 text-secondary"></i>Employee Details
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Surname</label>
                                    <input type="text" class="form-control" name="surname" id="editSurname" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">First Name</label>
                                    <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Middle Initial</label>
                                    <input type="text" class="form-control" name="middle_initial" id="editMi" maxlength="2">
                                </div>
                            </div>

                            <!-- Birth Details -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Date of Birth</label>
                                    <input type="date" class="form-control" name="dob" id="editDob">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Place of Birth</label>
                                    <input type="text" class="form-control" name="pob" id="editPob">
                                </div>
                            </div>
                        </div>

                        <!-- Leave Details Section -->
                        <div id="editLeaveParticularsSection">
                        <h6 class="bg-light p-2 border-start border-secondary border-4 mb-3 fw-bold text-dark rounded-end">
                            <i class="fas fa-calendar-alt me-2 text-secondary"></i>Leave Particulars
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Period Covered (From)</label>
                                <input type="date" class="form-control" name="period_from" id="editLeaveFrom">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Period Covered (To)</label>
                                <input type="date" class="form-control" name="period_to" id="editLeaveTo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Station / Place of Assignment</label>
                                <input type="text" class="form-control" name="station" id="editStation">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reason</label>
                                <select class="form-select" name="reason" id="editLeaveReason">
                                    <option value="" selected disabled>Select Reason...</option>
                                    <option value="Sick leave without pay">Sick leave without pay</option>
                                    <option value="Sick leave with pay">Sick leave with pay</option>
                                    <option value="Vacation leave without pay">Vacation leave without pay</option>
                                    <option value="Vacation leave with pay">Vacation leave with pay</option>
                                    <option value="Maternity leave">Maternity leave</option>
                                    <option value="Study leave">Study leave</option>
                                    <option value="Wellness leave">Wellness leave</option>
                                    <option value="Special privilege leave">Special privilege leave</option>
                                    <option value="Forced leave">Forced leave</option>
                                    <option value="Others">Others (Please specify)</option>
                                </select>
                                <input type="text" class="form-control mt-2 d-none" name="other_reason" id="editOtherReason" placeholder="Please specify reason">
                            </div>
                        </div>

                        <!-- Calculation Section -->
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Pay Status</label>
                                <select class="form-select" name="pay_status" id="editPayStatus">
                                    <option value="N/A" selected>N/A</option>
                                    <option value="With Pay">With Pay</option>
                                    <option value="Without Pay">Without Pay</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Total Days</label>
                                <input type="text" class="form-control bg-light" name="total_days" id="editTotalDays" readonly placeholder="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Remarks</label>
                                <input type="text" class="form-control" name="remarks" id="editRemarks" placeholder="Optional remarks">
                            </div>
                        </div>
                        </div><!-- /editLeaveParticularsSection -->
                        <div class="modal-footer bg-light mt-4 mx-n3 mb-n3 px-3 py-3 border-top">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-2"></i>Update Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD LEAVE PARTICULARS MODAL (From View History) -->
    <div class="modal fade" id="addLeaveParticularsModal" tabindex="-1" aria-labelledby="addLeaveParticularsModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addLeaveParticularsModalLabel"><i class="fas fa-plus-circle me-2"></i>Add Leave Particulars</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="addLeaveParticularsForm">
                        <input type="hidden" name="employee_id" id="historyEmployeeId">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Period Covered (From)</label>
                                <input type="date" class="form-control" name="period_from" id="historyLeaveFrom" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Period Covered (To)</label>
                                <input type="date" class="form-control" name="period_to" id="historyLeaveTo">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Station / Place of Assignment</label>
                                <input type="text" class="form-control" name="station" placeholder="Enter station/location">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Reason</label>
                                <select class="form-select" name="reason" id="historyLeaveReason" required>
                                    <option value="" selected disabled>Select Reason...</option>
                                    <option value="Sick leave without pay">Sick leave without pay</option>
                                    <option value="Sick leave with pay">Sick leave with pay</option>
                                    <option value="Vacation leave without pay">Vacation leave without pay</option>
                                    <option value="Vacation leave with pay">Vacation leave with pay</option>
                                    <option value="Maternity leave">Maternity leave</option>
                                    <option value="Study leave">Study leave</option>
                                    <option value="Wellness leave">Wellness leave</option>
                                    <option value="Special privilege leave">Special privilege leave</option>
                                    <option value="Forced leave">Forced leave</option>
                                    <option value="Others">Others (Please specify)</option>
                                </select>
                                <input type="text" class="form-control mt-2 d-none" name="other_reason" id="historyOtherReason" placeholder="Please specify reason">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Pay Status</label>
                                <select class="form-select" name="pay_status" id="historyPayStatus">
                                    <option value="With Pay">With Pay</option>
                                    <option value="Without Pay">Without Pay</option>
                                    <option value="N/A" selected>N/A</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Total Days</label>
                                <input type="text" class="form-control bg-light" id="historyTotalDays" readonly placeholder="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Remarks</label>
                                <input type="text" class="form-control" name="remarks" placeholder="Optional remarks">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveHistoryLeaveBtn"><i class="fas fa-save me-2"></i>Save Leave Record</button>
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD EXCEL MODAL -->

    <div class="modal fade" id="uploadExcelModal" tabindex="-1" aria-labelledby="uploadExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title" id="uploadExcelModalLabel"><i class="fas fa-file-excel me-2"></i>Upload Leave Records</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="uploadExcelForm" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Excel Files (.xlsx, .xls)</label>
                            <input type="file" class="form-control" name="excel_file[]" id="excelFileInput" accept=".xlsx, .xls" required multiple>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i> You can select multiple files at once.
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">School Level (Module)</label>
                                <select class="form-select" name="upload_level" id="uploadLevelSelect">
                                    <option value="ES" <?php echo ($level_filter == 'ES') ? 'selected' : ''; ?>>Elementary (ES)</option>
                                    <option value="SEC" <?php echo ($level_filter == 'SEC') ? 'selected' : ''; ?>>Secondary (SEC)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Record Status</label>
                                <select class="form-select" name="upload_status" id="uploadStatusSelect">
                                    <option value="Active" <?php echo ($status_filter == 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactivation" <?php echo ($status_filter == 'Inactivation' || empty($status_filter)) ? 'selected' : ''; ?>>Inactivation</option>
                                    <option value="Separation" <?php echo ($status_filter == 'Separation') ? 'selected' : ''; ?>>Separation</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Preview Table -->
                        <div id="fileUploadList" class="mb-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <h6 class="fw-bold m-0">Data Preview</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="clearUploadBtn">
                                    <i class="fas fa-trash-alt me-1"></i>Clear Selection
                                </button>
                            </div>
                            <div class="table-responsive border rounded" style="max-height: 300px;">
                                <table class="table table-sm table-hover mb-0" style="font-size: 0.7rem;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>#</th>
                                            <th>Emp No.</th>
                                            <th>Surname</th>
                                            <th>First Name</th>
                                            <th>MI</th>
                                            <th>Birthdate / POB</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody">
                                        <!-- Dynamically populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success fw-bold" id="uploadBtn">
                                <i class="fas fa-upload me-2"></i>Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRM DELETE MODAL -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title" id="confirmDeleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-trash-alt fa-4x text-danger opacity-25"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Are you sure?</h5>
                    <p class="text-secondary mb-0">You are about to delete <span id="confirmDeleteCount" class="fw-bold text-danger">0</span> selected record(s).<br>Deleted records can be viewed and restored in the "Deleted" section.</p>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4 fw-bold" id="confirmDeleteBtn">Yes, Delete now</button>
                </div>
            </div>
        </div>
    </div>
    <script src="/sdo-portal/leave-monitoring/assets/js/leave.js"></script>
    
<?php include '../includes/footer.php'; ?>
