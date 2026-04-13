<?php include 'includes/header.php'; ?>
<?php
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Sample data
$reportCountQuery = $conn->query("SELECT COUNT(*) as total FROM remittance_reports WHERE id = $report_id");
$reportCount = $reportCountQuery ? $reportCountQuery->fetch_assoc()['total'] : 0;
if ($reportCount == 0) {
    header("Location: es-shs.php");
    exit;
}

$report = $conn->query("SELECT * FROM remittance_reports WHERE id = $report_id")->fetch_assoc();

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) && $_GET['status'] !== 'all' ? $conn->real_escape_string($_GET['status']) : '';

// Build WHERE clause
$where_clause = "WHERE report_id = $report_id";
if (!empty($search)) {
    $search_terms = explode(' ', $search);
    $search_conditions = [];
    foreach ($search_terms as $term) {
        $escaped_term = $conn->real_escape_string($term);
        if (!empty($escaped_term)) {
            $search_conditions[] = "(member_no LIKE '%$escaped_term%' OR surname LIKE '%$escaped_term%' OR given_name LIKE '%$escaped_term%' OR middle_name LIKE '%$escaped_term%')";
        }
    }
    if (!empty($search_conditions)) {
        $where_clause .= " AND (" . implode(' AND ', $search_conditions) . ")";
    }
}
if (!empty($status_filter)) {
    $where_clause .= " AND status = '$status_filter'";
}

// Fetch total count for pagination (with filters)
$totalMembersQuery = $conn->query("SELECT COUNT(*) as total FROM remittance_members $where_clause");
$totalMembersCount = $totalMembersQuery ? $totalMembersQuery->fetch_assoc()['total'] : 0;

// Pagination and Filter settings
$limit = 300; // fixed members per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Double check if report exists and matches module type for security
$reportCheck = $conn->query("SELECT id FROM remittance_reports WHERE id = $report_id AND module_type = 'es_shs'")->num_rows;
if ($reportCheck == 0) {
    header("Location: es-shs.php");
    exit;
}
$totalPages = ceil($totalMembersCount / $limit);

// Fetch member data for current page (with filters)
$members = [];
$membersResult = $conn->query("SELECT * FROM remittance_members $where_clause LIMIT $limit OFFSET $offset");
if ($membersResult && $membersResult->num_rows > 0) {
    while ($row = $membersResult->fetch_assoc()) {
        $members[] = [
            'no' => $row['member_no'],
            'surname' => $row['surname'],
            'given' => $row['given_name'],
            'middle' => $row['middle_name'],
            'ps' => $row['personal_share'],
            'es' => $row['employer_share'],
            'status' => $row['status']
        ];
    }
}
?>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="es-shs.php">ES/SHS Reports</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View Members</li>
                    </ol>
                </nav>
                <a href="es-shs.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Reports
                </a>
            </div>

            <div class="card card-modern mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Remittance Report Details:
                        <?php echo $report['spa_no']; ?>
                    </h5>
                    <span class="badge bg-light text-success">
                        <?php
                        $periodDate = DateTime::createFromFormat('m-Y', $report['period']);
                        echo $periodDate ? $periodDate->format('F Y') : $report['period'];
                        ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
                            <h6 class="text-muted small text-uppercase fw-bold">Employer Information</h6>
                            <p class="mb-1"><strong>Name:</strong>
                                <?php echo $report['employer_name']; ?>
                            </p>
                            <p class="mb-1"><strong>Group:</strong>
                                <?php echo $report['group_name']; ?>
                            </p>
                            <p class="mb-1"><strong>TIN:</strong>
                                <?php echo $report['employer_tin']; ?>
                            </p>
                            <p class="mb-1"><strong>Type:</strong> <span class="badge"
                                    style="background-color: #2e7d32 !important;">
                                    <?php echo $report['employer_type']; ?>
                                </span></p>
                        </div>
                        <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
                            <h6 class="text-muted small text-uppercase fw-bold">Report Metadata</h6>
                            <p class="mb-1"><strong>Account No:</strong>
                                <?php echo $report['philhealth_no']; ?>
                            </p>
                            <p class="mb-1"><strong>Document Control No:</strong>
                                <?php echo $report['control_no']; ?>
                            </p>
                            <p class="mb-1"><strong>Date Generated:</strong>
                                <?php echo $report['date_generated']; ?>
                            </p>
                            <p class="mb-1"><strong>Date Received:</strong>
                                <?php echo $report['date_received']; ?>
                            </p>
                        </div>
                        <div class="col-lg-4 col-md-12">
                            <h6 class="text-muted small text-uppercase fw-bold">Financial Summary</h6>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                                <span>Total Members:</span><strong>
                                    <?php echo $report['total_members']; ?>
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                                <span>Total
                                    PS:</span><strong>₱<?php echo number_format((float) str_replace(',', '', $report['total_ps']), 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                                <span>Total
                                    ES:</span><strong>₱<?php echo number_format((float) str_replace(',', '', $report['total_es']), 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between pt-1">
                                <span class="fw-bold">Grand Total:</span>
                                <strong class="fs-5" style="color: #2e7d32 !important;">₱
                                    <?php
                                    $ps = (float) str_replace(',', '', $report['total_ps']);
                                    $es = (float) str_replace(',', '', $report['total_es']);
                                    echo number_format($ps + $es, 2);
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-modern">
            <div class="card-header bg-white py-3">
                <div class="row align-items-center g-3">
                    <div class="col-md-4">
                        <h5 class="mb-0">Member List</h5>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2 justify-content-md-end">
                            <div class="col-sm-6 col-md-5">
                                <div class="input-group shadow-sm rounded-pill overflow-hidden border">
                                    <span class="input-group-text bg-white border-0"><i
                                            class="bi bi-search text-success"></i></span>
                                    <input type="text" id="memberSearch" class="form-control border-0 ps-0"
                                        placeholder="Search name or ID..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="shadow-sm rounded-pill overflow-hidden border">
                                    <select id="statusFilter" class="form-select border-0">
                                        <option value="all" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All
                                            Statuses</option>
                                        <option value="A" <?php echo $status_filter === 'A' ? 'selected' : ''; ?>>A -
                                            Active
                                        </option>
                                        <option value="NE">NE - No Earnings</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="membersContent">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="membersTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" width="5%">No.</th>
                                    <th width="15%">Account No.</th>
                                    <th width="15%">Surname</th>
                                    <th width="15%">Given Name</th>
                                    <th width="15%">Middle Name</th>
                                    <th class="text-end" width="10%">PS</th>
                                    <th class="text-end" width="10%">ES</th>
                                    <th class="text-center" width="15%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $mem_counter = $offset + 1;
                                foreach ($members as $member): ?>
                                    <tr class="member-row"
                                        data-search="<?php echo strtolower($member['no'] . ' ' . $member['surname'] . ' ' . $member['given'] . ' ' . $member['middle']); ?>"
                                        data-status="<?php echo $member['status']; ?>"
                                        data-no="<?php echo $member['no']; ?>"
                                        data-name="<?php echo strtoupper($member['given'] . ' ' . $member['middle'] . ' ' . $member['surname']); ?>">
                                        <td class="text-center">
                                            <?php echo $mem_counter++; ?>
                                        </td>
                                        <td><code><?php echo $member['no']; ?></code></td>
                                        <td class="fw-bold">
                                            <?php echo strtoupper($member['surname']); ?>
                                        </td>
                                        <td>
                                            <?php echo strtoupper($member['given']); ?>
                                        </td>
                                        <td>
                                            <?php echo strtoupper($member['middle']); ?>
                                        </td>
                                        <td class="text-end text-primary">
                                            ₱<?php echo number_format((float) str_replace(',', '', $member['ps']), 2); ?>
                                        </td>
                                        <td class="text-end text-success">
                                            ₱<?php echo number_format((float) str_replace(',', '', $member['es']), 2); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($member['status'] == 'A'): ?>
                                                <span
                                                    class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Active</span>
                                            <?php elseif ($member['status'] == 'NE'): ?>
                                                <span
                                                    class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3">No
                                                    Earnings</span>
                                            <?php else: ?>
                                                <span
                                                    class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3"><?php echo $member['status']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small text-muted" id="tableSummary">
                            Showing <?php echo $offset + 1; ?> to
                            <?php echo min($offset + $limit, $totalMembersCount); ?>
                            of <?php echo $totalMembersCount; ?> members
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Page -->
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?id=<?php echo $report_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter ?: 'all'); ?>">Previous</a>
                                </li>

                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                if ($startPage > 1)
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link"
                                            href="?id=<?php echo $report_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter ?: 'all'); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;

                                if ($endPage < $totalPages)
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                ?>

                                <!-- Next Page -->
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?id=<?php echo $report_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter ?: 'all'); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Member History Modal -->
    <div class="modal fade" id="memberHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0 py-3" style="background-color: #2e7d32 !important;">
                    <h5 class="modal-title">
                        <i class="bi bi-clock-history me-2"></i>Contribution History: <span
                            id="historyMemberName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr style="background-color: #2e7d32 !important;">
                                    <th class="text-dark">Applicable Period</th>
                                    <th class="text-dark">Date Posted</th>
                                    <th class="text-dark">POR No.</th>
                                    <th class="text-dark">Reference</th>
                                    <th class="text-end text-dark">PS</th>
                                    <th class="text-end text-dark">ES</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <div id="historyLoader" class="text-center py-5 d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Fetching history...</p>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('memberSearch');
            const statusFilter = document.getElementById('statusFilter');
            const tableRows = document.querySelectorAll('.member-row');
            const historyModal = new bootstrap.Modal(document.getElementById('memberHistoryModal'));
            const historyTableBody = document.getElementById('historyTableBody');
            const historyLoader = document.getElementById('historyLoader');
            const historyMemberName = document.getElementById('historyMemberName');

            // Debounce function to limit AJAX calls
            function debounce(func, wait) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            // AJAX Update Logic
            function updateResults(url, pushState = true) {
                const content = document.getElementById('membersContent');
                content.style.opacity = '0.5';

                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContent = doc.getElementById('membersContent');

                        if (newContent) {
                            content.innerHTML = newContent.innerHTML;
                        }
                        content.style.opacity = '1';

                        if (pushState) {
                            window.history.pushState({}, '', url);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching results:', error);
                        content.style.opacity = '1';
                    });
            }

            // Filter Logic (AJAX update)
            function applyFilters() {
                const searchTerm = searchInput.value;
                const statusTerm = statusFilter.value;
                const url = new URL(window.location.href);
                url.searchParams.set('search', searchTerm);
                url.searchParams.set('status', statusTerm);
                url.searchParams.set('page', 1); // Reset to page 1

                updateResults(url.toString());
            }

            if (searchInput) {
                const debouncedSearch = debounce(applyFilters, 300);
                searchInput.addEventListener('input', debouncedSearch);
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyFilters();
                    }
                });
            }
            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilters);
            }

            // Handle browser back/forward buttons
            window.addEventListener('popstate', function () {
                updateResults(window.location.href, false);
            });

            // Use event delegation for Member Row Click Logic and Pagination
            document.addEventListener('click', function (e) {
                // Member Row Click
                const row = e.target.closest('.member-row');
                if (row) {
                    const memberNo = row.getAttribute('data-no');
                    const memberName = row.getAttribute('data-name');

                    historyMemberName.textContent = memberName;
                    historyTableBody.innerHTML = '';
                    historyLoader.classList.remove('d-none');
                    historyModal.show();

                    fetch(`../includes/get_member_history.php?member_no=${memberNo}`)
                        .then(response => response.json())
                        .then(data => {
                            historyLoader.classList.add('d-none');
                            if (data.success) {
                                if (data.data.length === 0) {
                                    historyTableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4">No history found.</td></tr>';
                                } else {
                                    data.data.forEach(item => {
                                        const tr = document.createElement('tr');
                                        tr.innerHTML = `
                                            <td class="fw-bold">${item.period}</td>
                                            <td>${item.date_posted}</td>
                                            <td><code class="text-primary">${item.por}</code></td>
                                            <td><small class="text-muted">${item.ref}</small></td>
                                            <td class="text-end text-primary">₱${item.ps}</td>
                                            <td class="text-end text-success">₱${item.es}</td>
                                        `;
                                        historyTableBody.appendChild(tr);
                                    });
                                }
                            } else {
                                historyTableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${data.message}</td></tr>`;
                            }
                        })
                        .catch(error => {
                            historyLoader.classList.add('d-none');
                            historyTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error fetching data.</td></tr>';
                            console.error('Error:', error);
                        });
                    return;
                }

                // Pagination Links AJAX
                const pageLink = e.target.closest('.page-link');
                if (pageLink && pageLink.hasAttribute('href')) {
                    const href = pageLink.getAttribute('href');
                    if (href && !href.startsWith('#')) {
                        e.preventDefault();
                        updateResults(href);
                    }
                }
            });
        });
    </script>
    <style>
        .member-row {
            cursor: pointer;
            transition: background 0.1s;
        }

        .member-row:hover {
            background-color: rgba(25, 135, 84, 0.05) !important;
        }

        .breadcrumb-item+.breadcrumb-item::before {
            content: "›";
            font-size: 1.2rem;
            vertical-align: middle;
        }

        .card-modern {
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }

        .bg-success-subtle {
            background-color: rgba(25, 135, 84, 0.1) !important;
        }

        #membersTable thead th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #555;
            border-bottom: 2px solid #eee;
        }

        #membersTable tbody tr:hover {
            background-color: #f8fbff;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>