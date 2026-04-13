<!-- Topbar -->
<div class="topbar">
    <div class="d-flex align-items-center">
        <button id="sidebarToggle" class="btn btn-link link-dark p-0 me-3">
            <i class="bi bi-list fs-3"></i>
        </button>
        <h5 class="mb-0">User Overview</h5>
    </div>
    <?php
    $unreadCount = 0;
    $notifications = [];
    if (isset($_SESSION['user_id'])) {
        $uId = $_SESSION['user_id'];
        $unreadRes = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $uId AND is_read = 0");
        if ($unreadRes) {
            $unreadCount = $unreadRes->fetch_assoc()['total'];
        }

        $nRes = $conn->query("SELECT * FROM notifications WHERE user_id = $uId ORDER BY created_at DESC LIMIT 5");
        if ($nRes) {
            while ($nRow = $nRes->fetch_assoc()) {
                $notifications[] = $nRow;
            }
        }


    }
    ?>
    <div class="d-flex align-items-center">
        <div class="dropdown me-3">
            <div class="position-relative cursor-pointer" id="notificationsDropdown" data-bs-toggle="dropdown"
                aria-expanded="false" style="cursor: pointer;">
                <i class="bi bi-bell-fill fs-5" style="color: #1e3a8a;"></i>
                <span id="unread-notification-badge"
                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?php echo $unreadCount > 0 ? '' : 'd-none'; ?>"
                    style="font-size: 0.65rem; padding: 0.35em 0.5em;">
                    <?php echo $unreadCount; ?>
                </span>
            </div>
            <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-0 overflow-hidden"
                aria-labelledby="notificationsDropdown"
                style="min-width: 280px; border-radius: 12px; margin-top: 10px;">
                <div class="dropdown-header bg-primary text-white py-3">
                    <h6 class="mb-0 fw-bold small"><i class="bi bi-bell-fill me-2"></i>Notifications</h6>
                </div>
                <div id="dropdown-notification-list" class="notification-list"
                    style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($notifications)): ?>
                        <div class="p-4 text-center">
                            <div class="mb-2">
                                <i class="bi bi-megaphone fs-4 text-muted opacity-50"></i>
                            </div>
                            <h6 class="fw-bold mb-1" style="font-size: 0.85rem;">No new notifications</h6>
                            <p class="text-muted mb-0" style="font-size: 0.75rem;">We'll notify you when something important
                                happens.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <a href="../../api/read_notification.php?id=<?php echo $n['id']; ?>"
                                class="dropdown-item py-3 border-bottom <?php echo $n['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center"
                                            style="width: 35px; height: 35px;">
                                            <i class="bi bi-info-circle"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold" style="font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($n['title']); ?>
                                        </h6>
                                        <p class="mb-1 text-muted small text-wrap">
                                            <?php echo htmlspecialchars($n['message']); ?>
                                        </p>
                                        <small class="text-muted" style="font-size: 0.7rem;">
                                            <i
                                                class="bi bi-clock me-1"></i><?php echo date("M d, h:i A", strtotime($n['created_at'])); ?>
                                        </small>
                                    </div>
                                    <?php if (!$n['is_read']): ?>
                                        <div class="ms-2">
                                            <span class="p-1 bg-primary rounded-circle d-block"></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="bg-light p-2 text-center border-top">
                    <a href="notifications.php" class="text-decoration-none small fw-bold"
                        style="color: #7f8c8d; font-size: 0.75rem;">View All</a>
                </div>
            </div>
        </div>
        <div class="dropdown me-3">
            <div class="position-relative cursor-pointer" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                title="Settings" style="cursor: pointer;">
                <i class="bi bi-gear-fill fs-5" style="color: #7f8c8d;"></i>

            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="settingsDropdown"
                style="border-radius: 10px; margin-top: 10px;">
                <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>

            </ul>
        </div>
        <div>
            Welcome, <strong>
                <?php echo htmlspecialchars(strtoupper($_SESSION["username"])); ?>
            </strong>
        </div>
    </div>
</div>

<!-- Toast Notifications System -->
<script src="../../assets/js/toast-notifications.js"></script>