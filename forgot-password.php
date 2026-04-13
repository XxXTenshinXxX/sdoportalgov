<?php
session_start();

// Simple user data (demo)
$users = [
    "admin" => ["password" => "admin123", "role" => "admin"],
    "user1" => ["password" => "user123", "role" => "user1"],
    "user2" => ["password" => "user123", "role" => "user2"],
    "user3" => ["password" => "user123", "role" => "user3"],
];

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (!isset($users[$username])) {
        $error = "Username not found.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // DEMO ONLY (no database)
        $users[$username]["password"] = $new_password;
        $message = "Password successfully updated! (Demo only)";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Schools Division Office</title>
    <link rel="icon" type="image/png" href="assets/img/SDO-Logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">

</head>

<body>

    <div class="card login-card">

        <!-- Logo -->
        <div class="logo-container">
            <img src="assets/img/SDO-Logo.png" alt="Schools Division Office Logo">
        </div>

        <!-- Titles -->
        <div class="office-title">
            Schools Division Office
        </div>
        <div class="unit-title">
            Remittance & Leave Monitoring
        </div>

        <!-- Forgot Password Form -->
        <div class="card-body p-4">
            <form method="POST">

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success text-center">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="new_password" class="form-control"
                            placeholder="Enter new password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleVisibility('new_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                            placeholder="Confirm new password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleVisibility('confirm_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">
                        Reset Password
                    </button>
                </div>

                <div class="text-center">
                    <a href="index.php" class="text-decoration-none">
                        ← Back to Login
                    </a>
                </div>

            </form>

            <div class="footer-text">
                © <?php echo date("Y"); ?> Schools Division Office - Quezon City
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>

</body>

</html>