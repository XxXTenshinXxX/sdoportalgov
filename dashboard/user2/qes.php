<?php include 'includes/header.php'; ?>
<?php
/* Pagination and Filter Logic */
$limit = 12; // Items per page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$year = isset($_GET['year']) ? $conn->real_escape_string($_GET['year']) : '';

$moduleType = 'qes';

// Get unique years for filter
$years = [];
$yearsRes = $conn->query("SELECT DISTINCT RIGHT(period, 4) as year FROM remittance_reports WHERE module_type = '$moduleType' AND delete_status = 'active' ORDER BY year DESC");
if ($yearsRes) {
    while ($yRow = $yearsRes->fetch_assoc()) {
        $years[] = $yRow['year'];
    }
}

// Highlight: if ?highlight=ID, jump to the page that contains that record
$highlightId = isset($_GET['highlight']) ? (int) $_GET['highlight'] : 0;
if ($highlightId && !isset($_GET['page'])) {
    $targetRes = $conn->query("SELECT created_at FROM remittance_reports WHERE id = $highlightId LIMIT 1");
    if ($targetRes && $tRow = $targetRes->fetch_assoc()) {
        $targetDate = $conn->real_escape_string($tRow['created_at']);
        $posRes = $conn->query("SELECT COUNT(*) as pos FROM remittance_reports WHERE module_type = '$moduleType' AND delete_status = 'active' AND created_at > '$targetDate'");
        $pos = $posRes ? (int) $posRes->fetch_assoc()['pos'] : 0;
        $page = (int) floor($pos / $limit) + 1;
    }
}

$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "WHERE r.module_type = '$moduleType' AND r.delete_status = 'active'";
if ($search) {
    $where .= " AND (r.spa_no LIKE '%$search%' OR r.employer_name LIKE '%$search%' OR r.original_filename LIKE '%$search%')";
}
if ($year) {
    $where .= " AND r.period LIKE '%-$year'";
}

// Get total count for pagination
$countQuery = $conn->query("SELECT COUNT(*) as total FROM remittance_reports r $where");
$totalRows = $countQuery->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$reports = [];
$result = $conn->query("SELECT r.*, u.username as uploader_name 
                        FROM remittance_reports r 
                        LEFT JOIN users u ON r.uploaded_by = u.id 
                        $where 
                        ORDER BY r.created_at DESC 
                        LIMIT $limit OFFSET $offset");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}
?>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="main-content">
        <div class="card card-modern p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                <h5 class="mb-0">QES Remittance Reports</h5>
                <div class="d-flex align-items-center gap-3">
                    <!-- Year Filter -->
                    <div class="shadow-sm rounded-pill overflow-hidden border bg-white d-flex align-items-center px-2"
                        style="height: 31px;">
                        <select id="yearFilter" class="form-select border-0 bg-transparent py-0 small"
                            style="box-shadow: none; font-size: 0.85rem;" onchange="applyFilters()">
                            <option value="">All Years</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Search Input -->
                    <div class="search-box" style="min-width: 250px;">
                        <form onsubmit="event.preventDefault(); applyFilters();" class="m-0">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 rounded-pill-start ps-3">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" id="tableSearch"
                                    class="form-control border-start-0 rounded-pill-end pe-3"
                                    placeholder="Search SPA, Employer, etc..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm"
                            id="btn-toggle-delete" onclick="toggleDeleteMode()">
                            <i class="bi bi-trash3-fill me-1"></i> Delete
                        </button>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm"
                            data-bs-toggle="modal" data-bs-target="#uploadReportModal">
                            <i class="bi bi-cloud-upload-fill me-1"></i> Upload New Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete Selected Button -->
            <div class="mb-3 d-none" id="delete-selected-container">
                <button class="btn btn-danger btn-sm" onclick="deleteSelected()" id="btn-delete-selected">
                    <i class="bi bi-trash-fill"></i> Delete Selected (<span id="selected-count">0</span>)
                </button>
                <button class="btn btn-outline-secondary btn-sm ms-2" onclick="clearSelection()">
                    <i class="bi bi-x-circle"></i> Clear Selection
                </button>
            </div>

            <div class="table-responsive">
                <form id="reports-form">
                    <table class="table table-hover align-middle text-center table-bordered small" id="reports-table">
                        <thead class="custom-table-header">
                            <tr>
                                <th class="text-center delete-column d-none" width="3%">
                                    <input type="checkbox" class="form-check-input select-all-checkbox" id="select-all"
                                        onclick="toggleSelectAll()">
                                </th>
                                <th class="text-center">#</th>
                                <th class="text-center">SPA No.</th>
                                <th class="text-center">Applicable Period</th>
                                <th class="text-center">Employer Name</th>
                                <th class="text-center">Group Name</th>
                                <th class="text-center">Total Members</th>
                                <th class="text-center">Total PS</th>
                                <th class="text-center">Total ES</th>
                                <th class="text-center">File Name</th>
                                <th class="text-center">Uploaded By</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = $offset + 1;
                            foreach ($reports as $report): ?>
                                <tr data-report-id="<?php echo $report['id']; ?>">
                                    <td class="text-center delete-column d-none">
                                        <input type="checkbox" class="form-check-input report-checkbox"
                                            value="<?php echo $report['id']; ?>" onchange="updateSelection()">
                                    </td>
                                    <td class="text-center">
                                        <?php echo $counter++; ?>
                                    </td>
                                    <td class="text-center"><strong>
                                            <?php echo $report['spa_no']; ?>
                                        </strong></td>
                                    <td class="text-center">
                                        <span
                                            class="badge rounded-pill px-3" style="background-color: #6a1b9a !important; color: white !important;">
                                            <?php
                                            $periodDate = DateTime::createFromFormat('m-Y', $report['period']);
                                            echo $periodDate ? $periodDate->format('F Y') : $report['period'];
                                            ?>
                                        </span>
                                    </td>
                                    <td class="text-start">
                                        <?php echo $report['employer_name']; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $report['group_name']; ?>
                                    </td>
                                    <td class="text-center">
                                            <?php echo $report['total_members']; ?>
                                    </td>
                                    <td class="text-end">₱<?php echo number_format($report['total_ps'], 2); ?></td>
                                    <td class="text-end">₱<?php echo number_format($report['total_es'], 2); ?></td>
                                    <td class="text-start">
                                        <span class="small text-muted">
                                            <?php echo htmlspecialchars($report['original_filename'] ?: basename($report['file_path'])); ?>
                                        </span>
                                    </td>
                                     <td class="text-center small">
                                         <div class="fw-bold"><?php echo strtoupper($report['uploader_name'] ?? 'Unknown'); ?></div>
                                         <div class="text-muted" style="font-size: 0.7rem;">
                                             <?php echo date("M d, Y", strtotime($report['created_at'])); ?>
                                         </div>
                                     </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="qes-members.php?id=<?php echo $report['id']; ?>"
                                                class="btn btn-sm btn-info icon-btn" title="View Members">
                                                <i class="bi bi-people-fill"></i>
                                            </a>
                                            <?php if (!empty($report['file_path'])): ?>
                                                <a href="../../<?php echo htmlspecialchars($report['file_path']); ?>"
                                                    target="_blank" class="btn btn-sm btn-success icon-btn" title="View PDF">
                                                    <i class="bi bi-file-earmark-pdf-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="download_report.php?id=<?php echo $report['id']; ?>"
                                                class="btn btn-sm btn-success icon-btn" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger icon-btn"
                                                onclick="deleteSingle(<?php echo $report['id']; ?>)" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <!-- Pagination UI -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-none"
                                href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo urlencode($year); ?>"
                                tabindex="-1">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link shadow-none"
                                    href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo urlencode($year); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-none"
                                href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo urlencode($year); ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                    <div class="text-center text-muted small mt-2">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalRows); ?> of
                        <?php echo $totalRows; ?> reports
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p id="delete-message" class="mb-1">Are you sure you want to delete the selected report(s)?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="confirm-delete-btn" onclick="confirmDelete()">
                        <i class="bi bi-trash-fill me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title" id="uploadReportModalLabel">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Upload QES Reports
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form action="save_remittance_report.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="module_type" value="qes">
                    <div class="modal-body p-4">
                        <div class="upload-zone text-center p-4 mb-3 border border-2 border-dashed rounded-3 bg-light"
                            id="dropZone">
                            <i class="bi bi-file-earmark-pdf text-primary mb-3" style="font-size: 3rem;"></i>
                            <h6 class="fw-bold mb-1">Select PDF files to upload</h6>
                            <p class="text-muted small mb-3">You can select multiple files at once</p>
                            <input type="file" name="pdf_files[]" id="fileInput" class="d-none" multiple accept=".pdf">
                            <button type="button" class="btn btn-outline-primary btn-sm px-4"
                                onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-folder2-open me-1"></i> Browse Files
                            </button>
                        </div>
                        <div id="filePreviewContainer" class="d-none">
                            <h6 class="small fw-bold text-uppercase text-muted mb-2">Selected Files (<span
                                    id="fileCount">0</span>)</h6>
                            <div class="list-group list-group-flush border rounded-3 overflow-auto"
                                style="max-height: 200px;" id="fileList"></div>
                            <div class="text-end mt-2">
                                <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                    onclick="clearFiles()">
                                    <i class="bi bi-trash-fill small"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4" id="uploadSubmitBtn" disabled>
                            <i class="bi bi-upload me-1"></i> Start Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts (Same logic as PhilHealth/ES-SHS) -->
    <script>
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const filePreviewContainer = document.getElementById('filePreviewContainer');
        const fileCountSpan = document.getElementById('fileCount');
        const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
        const dropZone = document.getElementById('dropZone');

        fileInput.addEventListener('change', handleFileSelect);
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-primary', 'bg-primary-subtle'); });
        dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-primary', 'bg-primary-subtle'); });
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('border-primary', 'bg-primary-subtle'); if (e.dataTransfer.files.length > 0) { fileInput.files = e.dataTransfer.files; handleFileSelect(); } });

        function handleFileSelect() {
            const files = fileInput.files;
            fileList.innerHTML = '';
            if (files.length > 0) {
                filePreviewContainer.classList.remove('d-none');
                fileCountSpan.textContent = files.length;
                uploadSubmitBtn.disabled = false;
                Array.from(files).forEach((file, index) => {
                    if (file.type !== 'application/pdf') return;
                    const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
                    item.id = `file-item-${index}`;
                    item.innerHTML = `
                        <div class="d-flex align-items-center overflow-hidden">
                            <i class="bi bi-file-pdf-fill text-danger me-2 fs-5"></i>
                            <div class="text-truncate">
                                <div class="small fw-bold text-truncate">${file.name}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">${fileSize}</div>
                            </div>
                        </div>
                        <div class="status-indicator">
                            <i class="bi bi-clock-history text-muted ms-2"></i>
                        </div>`;
                    fileList.appendChild(item);
                });
            } else { clearFiles(); }
        }

        document.querySelector('#uploadReportModal form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const files = fileInput.files;
            if (files.length === 0) return;

            uploadSubmitBtn.disabled = true;
            const btnOriginal = uploadSubmitBtn.innerHTML;
            uploadSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';

            let successCount = 0;
            let errorCount = 0;
            let errorMessages = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const statusIndicator = document.querySelector(`#file-item-${i} .status-indicator`);
                statusIndicator.innerHTML = '<span class="spinner-border spinner-border-sm text-primary ms-2"></span>';

                const formData = new FormData();
                formData.append('pdf_files[]', file);
                formData.append('module_type', 'qes');
                formData.append('ajax', '1');

                try {
                    const response = await fetch('save_remittance_report.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        statusIndicator.innerHTML = '<i class="bi bi-check-circle-fill text-success ms-2"></i>';
                        successCount++;
                    } else {
                        const errMsg = result.message || 'Upload failed.';
                        statusIndicator.innerHTML = `<span title="${errMsg}"><i class="bi bi-x-circle-fill text-danger ms-2"></i></span>`;
                        errorMessages.push(errMsg);
                        errorCount++;
                    }
                } catch (error) {
                    statusIndicator.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning ms-2"></i>';
                    errorCount++;
                }
            }

            uploadSubmitBtn.innerHTML = btnOriginal;
            uploadSubmitBtn.disabled = false;

            if (successCount > 0) {
                Swal.fire({
                    icon: errorCount > 0 ? 'warning' : 'success',
                    title: 'Upload Complete',
                    text: `Successfully uploaded ${successCount} report(s). ${errorCount > 0 ? errorCount + ' failed.' : ''}`,
                    confirmButtonText: 'Great!',
                    confirmButtonColor: '#0d6efd'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    html: errorMessages.length > 0
                        ? '<ul class="text-start small mb-0">' + errorMessages.map(m => `<li>${m}</li>`).join('') + '</ul>'
                        : 'None of the files were uploaded successfully.',
                    confirmButtonText: 'Close'
                });
            }
        });

        function clearFiles() { fileInput.value = ''; fileList.innerHTML = ''; filePreviewContainer.classList.add('d-none'); uploadSubmitBtn.disabled = true; }

        let selectedIds = [];
        let deleteModal;
        document.addEventListener('DOMContentLoaded', function () { deleteModal = new bootstrap.Modal(document.getElementById('deleteModal')); });

        function toggleDeleteMode() {
            const deleteColumns = document.querySelectorAll('.delete-column');
            const btnToggle = document.getElementById('btn-toggle-delete');
            const isDeleting = btnToggle.classList.contains('btn-danger');
            if (isDeleting) {
                deleteColumns.forEach(el => el.classList.add('d-none'));
                btnToggle.classList.remove('btn-danger', 'btn-active');
                btnToggle.classList.add('btn-outline-danger');
                btnToggle.innerHTML = '<i class="bi bi-trash3-fill me-1"></i> Delete';
                clearSelection();
            } else {
                deleteColumns.forEach(el => el.classList.remove('d-none'));
                btnToggle.classList.remove('btn-outline-danger');
                btnToggle.classList.add('btn-danger', 'btn-active');
                btnToggle.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Cancel Delete';
            }
        }

        function toggleSelectAll() { const selectAllCheckbox = document.getElementById('select-all'); const checkboxes = document.querySelectorAll('.report-checkbox'); checkboxes.forEach(checkbox => { checkbox.checked = selectAllCheckbox.checked; }); updateSelection(); }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.report-checkbox:checked');
            selectedIds = Array.from(checkboxes).map(cb => cb.value);
            const deleteContainer = document.getElementById('delete-selected-container');
            const selectedCount = document.getElementById('selected-count');
            if (selectedIds.length > 0) { deleteContainer.classList.remove('d-none'); selectedCount.textContent = selectedIds.length; } else { deleteContainer.classList.add('d-none'); }
            const allCheckboxes = document.querySelectorAll('.report-checkbox');
            const selectAllCheckbox = document.getElementById('select-all');
            selectAllCheckbox.checked = selectedIds.length === allCheckboxes.length && allCheckboxes.length > 0;
            selectAllCheckbox.indeterminate = selectedIds.length > 0 && selectedIds.length < allCheckboxes.length;
        }

        function clearSelection() { const checkboxes = document.querySelectorAll('.report-checkbox'); checkboxes.forEach(checkbox => checkbox.checked = false); document.getElementById('select-all').checked = false; updateSelection(); }
        function deleteSingle(id) { selectedIds = [id.toString()]; document.getElementById('delete-message').textContent = 'Are you sure you want to delete this report?'; deleteModal.show(); }
        function deleteSelected() { document.getElementById('delete-message').textContent = `Are you sure you want to delete ${selectedIds.length} selected report(s)?`; deleteModal.show(); }
        function confirmDelete() {
            if (selectedIds.length === 0) return;

            fetch('delete_remittance_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: selectedIds })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        selectedIds.forEach(id => {
                            const row = document.querySelector(`tr[data-report-id="${id}"]`);
                            if (row) {
                                row.style.transition = 'all 0.3s ease';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 300);
                            }
                        });


                        setTimeout(() => {
                            clearSelection();
                            deleteModal.hide();
                            showAlert('Report(s) deleted successfully!', 'success');
                            if (document.querySelectorAll('#reports-table tbody tr').length === 0) {
                                location.reload();
                            }
                        }, 400);
                    } else {
                        showAlert('Error: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while deleting reports.', 'danger');
                });
        }
        function showAlert(message, type) {
            Swal.fire({
                icon: type === 'danger' ? 'error' : type,
                title: type === 'success' ? 'Success!' : 'Notice',
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        }

        // Check for URL parameters for notifications
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            const status = urlParams.get('status');

            if (msg) {
                showAlert(decodeURIComponent(msg), status === 'success' ? 'success' : 'danger');

                // Clean up URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        // Filter Table Logic - Redirect for server-side filtering
        function applyFilters() {
            const search = document.getElementById('tableSearch').value;
            const year = document.getElementById('yearFilter').value;
            window.location.href = `?search=${encodeURIComponent(search)}&year=${encodeURIComponent(year)}`;
        }
    </script>

    <style>
        .custom-table-header {
            background-color: #6a1b9a !important;
            color: white !important;
        }

        .rounded-pill-start {
            border-top-left-radius: 50rem !important;
            border-bottom-left-radius: 50rem !important;
        }

        .rounded-pill-end {
            border-top-right-radius: 50rem !important;
            border-bottom-right-radius: 50rem !important;
        }

        #tableSearch:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }

        .custom-table-header th {
            background-color: #6a1b9a !important;
            color: white !important;
            font-weight: 600;
        }

        .form-check-input {
            cursor: pointer;
            width: 1.25em;
            height: 1.25em;
            border: 2px solid #6a1b9a;
        }

        .form-check-input:checked {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .select-all-checkbox {
            border-color: #fff !important;
        }

        tr.selected-row {
            background-color: #fff5f5 !important;
        }

        .delete-column {
            background-color: #fffafa !important;
            transition: all 0.3s ease;
        }

        .icon-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .icon-btn i {
            font-size: 1rem;
        }

        #reports-table tbody td {
            font-size: 0.75rem;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($highlightId): ?>
        <style>
            @keyframes highlightPulse {
                0% {
                    background-color: #fff3cd;
                    box-shadow: 0 0 0 3px #ffc107;
                }

                50% {
                    background-color: #ffe69c;
                    box-shadow: 0 0 0 6px rgba(255, 193, 7, 0.3);
                }

                100% {
                    background-color: transparent;
                    box-shadow: none;
                }
            }

            .row-highlighted td {
                background-color: #ffeb3b !important;
                outline: 3px solid #ffc107 !important;
                transition: background-color 3s ease, outline 3s ease;
            }

            .row-highlighted.fade-out td {
                background-color: transparent !important;
                outline: none !important;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const targetRow = document.querySelector('tr[data-report-id="<?php echo $highlightId; ?>"]');
                if (targetRow) {
                    targetRow.classList.add('row-highlighted');
                    setTimeout(() => targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
                    setTimeout(() => targetRow.classList.add('fade-out'), 3500);
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>