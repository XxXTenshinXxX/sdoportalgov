<?php include 'includes/header.php'; ?>
<?php include '../includes/update_profile_logic.php'; ?>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 py-1">User Profile</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo $success; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label text-muted small">Username</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo strtoupper(htmlspecialchars($currentUser['username'])); ?>"
                                        readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small">Role</label>
                                    <div>
                                        <span
                                            class="badge bg-primary"><?php echo strtoupper($currentUser['role']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small">Member Since</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo date("F d, Y", strtotime($currentUser['created_at'])); ?>"
                                        readonly>
                                </div>
                            </div>

                            <hr>

                            <h6 class="fw-bold mb-3">Change Password</h6>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label small">Current Password</label>
                                    <input type="password" name="current_password" class="form-control"
                                        id="current_password" required>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label small">New Password</label>
                                        <input type="password" name="new_password" class="form-control"
                                            id="new_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label small">Confirm New
                                            Password</label>
                                        <input type="password" name="confirm_password" class="form-control"
                                            id="confirm_password" required>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>