<?php include 'includes/header.php'; ?>
<?php
// Display alerts if any
if (isset($_GET['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            ' . htmlspecialchars($_GET['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}
if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            ' . htmlspecialchars($_GET['error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}
?>
<?php
/* Fetch users from database - ASCENDING ORDER */
$result = $conn->query("SELECT * FROM users ORDER BY id ASC");
?>

<body>

    <?php include 'includes/sidebar.php'; ?>

    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">

        <div class="card card-modern p-4">

            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Registered Users</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                    <thead class="user-table-header">
                        <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">Username</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Date Created</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center"><?php echo $row["id"]; ?></td>
                                    <td class="text-center">
                                        <?php echo strtoupper(htmlspecialchars($row["username"])); ?>
                                        <?php if ($row['id'] == $_SESSION['user_id']): ?>
                                            <span>(You)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $row["role"] === 'admin' ? 'danger' : 'secondary'; ?>">
                                            <?php echo strtoupper($row["role"]); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php echo date("F d, Y g:ia", strtotime($row["created_at"])); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="user-status" data-last-active="<?php echo $row['last_active']; ?>">
                                            <span class="spinner-border spinner-border-sm text-muted"></span>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-warning"
                                            onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['username']); ?>')"
                                            title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <!-- Edit User Modal -->
    <div class='modal fade' id='editUserModal' tabindex='-1' aria-labelledby='editUserModalLabel' aria-hidden='true'>
        <div class='modal-dialog modal-dialog-centered'>
            <div class='modal-content border-0 shadow'>
                <div class='modal-header bg-warning text-dark border-0'>
                    <h5 class='modal-title fw-bold' id='editUserModalLabel'>
                        <i class='bi bi-person-gear me-2'></i>Edit User
                    </h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <form action='update_user.php' method='POST'>
                    <div class='modal-body p-4'>
                        <input type='hidden' name='user_id' id='edit_user_id'>
                        <div class='mb-3'>
                            <label class='form-label fw-bold small text-uppercase'>Username</label>
                            <input type='text' name='username' id='edit_username' class='form-control' required>
                        </div>
                        <hr class='my-4'>
                        <p class='text-muted small mb-3'><i class='bi bi-info-circle me-1'></i>Leave password fields
                            blank if you don't want to change the password.</p>
                        <div class='mb-3'>
                            <label class='form-label fw-bold small text-uppercase'>New Password</label>
                            <input type='password' name='new_password' class='form-control' autocomplete='new-password'>
                        </div>
                        <div class='mb-0'>
                            <label class='form-label fw-bold small text-uppercase'>Confirm Password</label>
                            <input type='password' name='confirm_password' class='form-control'>
                        </div>
                    </div>
                    <div class='modal-footer border-0 p-4 pt-0'>
                        <button type='button' class='btn btn-light px-4' data-bs-dismiss='modal'>Cancel</button>
                        <button type='submit' class='btn btn-warning px-4 fw-bold'>Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .user-table-header {
            background-color: #344767 !important;
            color: white !important;
        }

        .user-table-header th {
            color: white !important;
            font-weight: 600;
            background-color: #344767 !important;
        }
    </style>
    <script>
        function updateStatuses() {
            const now = new Date();
            document.querySelectorAll('.user-status').forEach(el => {
                const lastActiveStr = el.getAttribute('data-last-active');
                if (!lastActiveStr) return;

                // Parse MySQL timestamp (YYYY-MM-DD HH:MM:SS)
                // Need to handle Safari/IE by replacing '-' with '/' or using a more robust parser
                const t = lastActiveStr.split(/[- :]/);
                const lastActive = new Date(t[0], t[1] - 1, t[2], t[3], t[4], t[5]);

                const diffMs = now - lastActive;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMins / 60);
                const diffDays = Math.floor(diffHours / 24);

                let statusHTML = '';
                if (diffMins < 2) {
                    statusHTML = '<span class="badge bg-success"><i class="bi bi-circle-fill small me-1"></i> Active Now</span>';
                } else if (diffMins < 60) {
                    statusHTML = `<span class="badge bg-light text-dark border">${diffMins} mins ago</span>`;
                } else if (diffHours < 24) {
                    statusHTML = `<span class="badge bg-light text-dark border">${diffHours} hours ago</span>`;
                } else {
                    statusHTML = `<span class="badge bg-light text-dark border">${diffDays} days ago</span>`;
                }
                el.innerHTML = statusHTML;
            });
        }

        function openEditModal(id, username) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;

            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }

        setInterval(updateStatuses, 30000); // Update every 30s
        updateStatuses();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>