<?php include 'includes/header.php'; ?>
<?php
// Fetch pending delete requests for ES/SHS
$reports = [];
$result = $conn->query("SELECT r.*, u.username as uploader 
                       FROM remittance_reports r 
                       LEFT JOIN users u ON r.uploaded_by = u.id 
                       WHERE r.delete_status = 'pending' AND r.module_type = 'es_shs'
                       ORDER BY r.created_at DESC");
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 text-danger fw-bold"><i class="bi bi-trash3-fill me-2"></i>Pending ES/SHS Delete
                    Requests</h5>
                <span class="badge bg-danger rounded-pill px-3 py-2">
                    <?php echo count($reports); ?> Request(s)
                </span>
            </div>

            <?php if (empty($reports)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-check2-circle fs-1 text-success opacity-50"></i>
                    </div>
                    <h5 class="text-muted">No pending delete requests</h5>
                    <p class="text-muted small">Everything is up to date.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center border">
                        <thead class="custom-table-header">
                            <tr>
                                <th>#</th>
                                <th>Filename</th>
                                <th>Module</th>
                                <th>Uploaded By</th>
                                <th>Date Uploaded</th>
                                <th width="200px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $index => $row): ?>
                                <tr>
                                    <td>
                                        <?php echo $index + 1; ?>
                                    </td>
                                    <td class="text-start">
                                        <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                        <?php echo htmlspecialchars($row['original_filename'] ?: basename($row['file_path'])); ?>
                                    </td>
                                    <td><span class="badge bg-success">ES/SHS</span></td>
                                    <td>
                                        <?php echo htmlspecialchars(strtoupper($row['uploader'] ?? 'SYSTEM')); ?>
                                    </td>
                                    <td>
                                        <?php echo date("M d, Y", strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button class="btn btn-sm btn-success rounded-pill px-3"
                                                onclick="processRequest(<?php echo $row['id']; ?>, 'approve')">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                                onclick="processRequest(<?php echo $row['id']; ?>, 'reject')">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function processRequest(id, action) {
            const title = action === 'approve' ? 'Approve Deletion?' : 'Reject Deletion?';
            const text = action === 'approve' ? 'This will permanently delete the report and all its associated members.' : 'This will restore the report to active status.';
            const confirmBtnColor = action === 'approve' ? '#dc3545' : '#0d6efd';
            const confirmBtnText = action === 'approve' ? 'Yes, Delete Permanently' : 'Yes, Restore Report';

            Swal.fire({
                title: title,
                text: text,
                icon: action === 'approve' ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: confirmBtnText,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('process_delete_request.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, action: action })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Completed!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: data.message
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'System Error',
                                text: 'An unexpected error occurred. Please try again.'
                            });
                        });
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>