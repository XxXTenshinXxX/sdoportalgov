<?php
session_start();

require 'config/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION["user_id"] = $row["id"];
            $_SESSION["username"] = $row["username"];
            $_SESSION["role"] = $row["role"];

            require 'dashboard/includes/activity_logger.php';
            require 'dashboard/includes/notification_helper.php';
            // Log activity
            logActivity($conn, $row['id'], 'Login', "User logged in successfully.");

            // Notify admin and user3 if user1 or user2 logs in
            if ($row['role'] === 'user1' || $row['role'] === 'user2') {
                notifyRoles($conn, ['admin', 'user3'], $row['id'], "User Login", strtoupper($row['username']) . " has logged in.", 'info', 'users.php');
            }

            // Redirect based on role
            switch ($_SESSION["role"]) {
                case "admin":
                    header("Location: dashboard/admin/dashboard.php");
                    exit;
                case "user1":
                    header("Location: dashboard/user1/dashboard.php");
                    exit;
                case "user2":
                    header("Location: dashboard/user2/dashboard.php");
                    exit;
                case "user3":
                    header("Location: dashboard/user3/dashboard.php");
                    exit;
                default:
                    $error = "Unknown user role.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Schools Division Office - Remittance Unit (PhilHealth)</title>
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
            <!-- Replace logo.png with your actual image filename -->
            <img src="assets/img/SDO-Logo.png" alt="Schools Division Office Logo">
        </div>

        <!-- Titles -->
        <div class="office-title">
            Schools Division Office
        </div>
        <div class="unit-title">
            Remmitance & Leave Monitoring
        </div>

        <!-- Login Form -->
        <div class="card-body p-4">
            <form id="loginForm" method="POST">

                <?php if ($error): ?>
                    <div class="text-danger" style="text-align:center;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <br>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" id="username" placeholder="Enter username"
                        required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control" id="password"
                            placeholder="Enter password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>

                <div class="text-center">
                    <br>
                    <a href="forgot-password.php" class="text-decoration-none" style="font-size: 0.9rem;">
                        Forgot Password?
                    </a>
                </div>

                <div id="errorMessage" class="text-danger mt-3 text-center" style="display:none;"></div>
            </form>

            <div class="footer-text">
                © <?php echo date("Y"); ?> Schools Division Office - Quezon City
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>

</body>

</html>