<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']); 
?>
<!-- SIDEBAR -->
<div class="col-auto col-md-3 col-xl-2 sidebar d-flex flex-column">
    <div class="sidebar-header text-center pb-4">
        <img src="/sdo-portal/leave-monitoring/assets/images/SDO-Logo.png" alt="SDO Logo" class="img-fluid mb-3" style="max-width: 120px;">
        <h6 class="fw-bold m-0" style="font-size: 0.85rem; line-height: 1.4;">School Division Office<br>Leave Monitoring</h6>
    </div>
    <nav class="nav flex-column mb-auto" id="sidebarAccordion">
        <a class="nav-link <?php echo($current_page == 'index.php') ? 'active' : ''; ?>" href="/sdo-portal/leave-monitoring/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a class="nav-link <?php echo($current_page == 'leave-monitoring.php' && !isset($_GET['level']) && !isset($_GET['status'])) ? 'active' : ''; ?>" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php"><i class="fas fa-users"></i> All Employees</a>
        

        <!-- ELEMENTARY (ES) DROPDOWN -->
        <div class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center <?php echo(isset($_GET['level']) && $_GET['level'] == 'ES') ? 'active' : ''; ?>" 
               data-bs-toggle="collapse" href="#esCollapse" role="button" aria-expanded="<?php echo(isset($_GET['level']) && $_GET['level'] == 'ES') ? 'true' : 'false'; ?>">
                <span><i class="fas fa-school"></i> Elementary (ES)</span>
                <i class="fas fa-chevron-down small transition-icon"></i>
            </a>
            <div class="collapse <?php echo(isset($_GET['level']) && $_GET['level'] == 'ES') ? 'show' : ''; ?>" id="esCollapse" data-bs-parent="#sidebarAccordion">
                <nav class="nav flex-column ms-3 ps-2 border-start border-light border-opacity-10">
                    <a class="nav-link py-1 px-3 mt-1 <?php echo(isset($_GET['level']) && $_GET['level'] == 'ES' && isset($_GET['status']) && $_GET['status'] == 'Active') ? 'active bg-primary bg-opacity-10' : ''; ?>" 
                       style="font-size: 0.85rem;" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php?level=ES&status=Active">Active</a>
                    <a class="nav-link py-1 px-3 <?php echo(isset($_GET['level']) && $_GET['level'] == 'ES' && isset($_GET['status']) && $_GET['status'] == 'Inactivation') ? 'active bg-primary bg-opacity-10' : ''; ?>" 
                       style="font-size: 0.85rem;" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php?level=ES&status=Inactivation">Inactivation</a>
                    <a class="nav-link py-1 px-3 mb-1 <?php echo(isset($_GET['level']) && $_GET['level'] == 'ES' && isset($_GET['status']) && $_GET['status'] == 'Separation') ? 'active bg-primary bg-opacity-10' : ''; ?>" 
                       style="font-size: 0.85rem;" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php?level=ES&status=Separation">Separation</a>
                </nav>
            </div>
        </div>

        <!-- SECONDARY (SEC) DROPDOWN -->
        <div class="nav-item">
            <a class="nav-link d-flex justify-content-between align-items-center <?php echo(isset($_GET['level']) && $_GET['level'] == 'SEC') ? 'active' : ''; ?>" 
               data-bs-toggle="collapse" href="#secCollapse" role="button" aria-expanded="<?php echo(isset($_GET['level']) && $_GET['level'] == 'SEC') ? 'true' : 'false'; ?>">
                <span><i class="fas fa-graduation-cap"></i> Secondary (SEC)</span>
                <i class="fas fa-chevron-down small transition-icon"></i>
            </a>
            <div class="collapse <?php echo(isset($_GET['level']) && $_GET['level'] == 'SEC') ? 'show' : ''; ?>" id="secCollapse" data-bs-parent="#sidebarAccordion">
                <nav class="nav flex-column ms-3 ps-2 border-start border-light border-opacity-10">
                    <a class="nav-link py-1 px-3 mt-1 <?php echo(isset($_GET['level']) && $_GET['level'] == 'SEC' && isset($_GET['status']) && $_GET['status'] == 'Active') ? 'active bg-primary bg-opacity-10' : ''; ?>" 
                       style="font-size: 0.85rem;" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php?level=SEC&status=Active">Active</a>
                    <a class="nav-link py-1 px-3 <?php echo(isset($_GET['level']) && $_GET['level'] == 'SEC' && isset($_GET['status']) && $_GET['status'] == 'Inactivation') ? 'active bg-primary bg-opacity-10' : ''; ?>" 
                       style="font-size: 0.85rem;" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php?level=SEC&status=Inactivation">Inactivation</a>
                    <a class="nav-link py-1 px-3 mb-1 <?php echo(isset($_GET['level']) && $_GET['level'] == 'SEC' && isset($_GET['status']) && $_GET['status'] == 'Separation') ? 'active bg-primary bg-opacity-10' : ''; ?>" 
                       style="font-size: 0.85rem;" href="/sdo-portal/leave-monitoring/pages/leave-monitoring.php?level=SEC&status=Separation">Separation</a>
                </nav>
            </div>
        </div>


        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a class="nav-link" href="javascript:void(0);" onclick="switchApp('/sdo-portal/dashboard/admin/dashboard.php', 'Remittances')">
            <i class="fas fa-file-invoice-dollar"></i> Remittances
        </a>
        <?php endif; ?>
    </nav>

    <div class="mt-auto pb-4">
        <hr class="border-light border-opacity-10 mx-3">
        <a class="nav-link text-danger logout-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <style>
        .nav-link[aria-expanded="true"] .transition-icon {
            transform: rotate(180deg);
        }
        .transition-icon {
            transition: transform 0.2s ease-in-out;
        }
        .nav-link.active {
            font-weight: 600;
        }
        .logout-link {
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 0 10px;
        }
        .logout-link:hover {
            background: rgba(220, 53, 69, 0.1);
            transform: translateX(5px);
        }
    </style>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fas fa-sign-out-alt fa-3x text-danger"></i>
                </div>
                <h5 class="fw-bold mb-2">Are you sure?</h5>
                <p class="text-secondary small mb-0">Do you really want to logout? You will need to login again to access the system.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="font-size: 0.85rem;">Cancel</button>
                <a href="/sdo-portal/index.php" class="btn btn-danger px-4" style="font-size: 0.85rem;">Logout</a>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . "/../../includes/app-switcher.php"; ?>
