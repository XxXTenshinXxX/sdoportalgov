<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch pending delete requests count for QES
$pending_delete_count = 0;
require_once "../../config/db.php";
$count_res = $conn->query("SELECT COUNT(*) as total FROM remittance_reports WHERE delete_status = 'pending' AND module_type = 'qes'");
if ($count_res) {
    $pending_delete_count = $count_res->fetch_assoc()['total'];
}
?>
<!-- Sidebar -->
<div class="sidebar d-flex flex-column">

    <div class="logo-container">
        <img src="../../assets/img/SDO-Logo.png" alt="Schools Division Office Logo">
    </div>

    <div class="office-title">
        Schools Division Office
    </div>
    <div class="unit-title">
        REMITTANCE UNIT <br> 
        (PhilHealth) <br> 
        <b>USER 2 PORTAL</b>
    </div>

    <hr class="text-white opacity-50 mx-3">

    <nav class="nav flex-column mt-2">

        <a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-house-door me-2"></i> Dashboard
        </a>

        <a href="qes.php"
            class="nav-link <?php echo ($current_page == 'qes.php' || $current_page == 'qes-members.php') ? 'active' : ''; ?>">
            <i class="bi bi-mortarboard me-2"></i> QES
        </a>

        <a href="delete_requests.php"
            class="nav-link d-flex align-items-center <?php echo ($current_page == 'delete_requests.php') ? 'active' : ''; ?>">
            <i class="bi bi-trash me-2"></i> Delete Requests
            <?php if ($pending_delete_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-auto small-badge"><?php echo $pending_delete_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="javascript:void(0);" onclick="confirmLogout()" class="nav-link nav-link-danger mt-3">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>

    </nav>
</div>

<script>
    function confirmLogout() {
        Swal.fire({
            title: 'Confirm Logout',
            text: "Are you sure you want to logout of your session?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Stay logged in'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "../../logout.php";
            }
        })
    }
</script>

<style>
    .small-badge {
        font-size: 0.7rem;
        padding: 0.35em 0.65em;
        font-weight: 600;
        opacity: 0.85;
    }

    .nav-link:hover .small-badge {
        opacity: 1;
        background-color: #fff !important;
        color: #000 !important;
    }
</style>