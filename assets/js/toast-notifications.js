// Toast Notification System
document.addEventListener('DOMContentLoaded', function () {
    // Inject animation CSS
    if (!document.getElementById('toast-anim-styles')) {
        let style = document.createElement('style');
        style.id = 'toast-anim-styles';
        style.innerHTML = `
            @keyframes slideInRightToast {
                from { transform: translateX(110%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            #toast-notification-container .toast {
                animation: slideInRightToast 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            }
        `;
        document.head.appendChild(style);
    }

    // Check if we already have a wrapper
    let toastContainer = document.getElementById('toast-notification-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-notification-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3 mt-3 me-2';
        toastContainer.style.zIndex = '1055'; // Above normal modabls etc (1050)
        document.body.appendChild(toastContainer);
    }

    // Store notified IDs only in memory for the duration of this page load
    let notifiedIds = [];
    let isPolling = false;
    let isFirstLoad = true;

    function pollNotifications() {
        if (isPolling) return;
        isPolling = true;

        // Add a timestamp to prevent the browser from caching the response!
        let nocache = new Date().getTime();
        fetch('../../api/get_latest_notifications.php?t=' + nocache)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update the bell icon badge count dynamically
                    let badge = document.getElementById('unread-notification-badge');
                    if (badge) {
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.classList.remove('d-none');
                        } else {
                            badge.classList.add('d-none');
                        }
                    }

                    // Update the settings gear badge count dynamically
                    let logBadge = document.getElementById('activity-log-badge');
                    let logDot = document.getElementById('activity-log-dot');
                    if (data.log_count > 0) {
                        if (logBadge) {
                            logBadge.textContent = data.log_count;
                            logBadge.classList.remove('d-none');
                        }
                        if (logDot) {
                            logDot.classList.remove('d-none');
                        }
                    } else {
                        if (logBadge) {
                            logBadge.classList.add('d-none');
                        }
                        if (logDot) {
                            logDot.classList.add('d-none');
                        }
                    }

                    if (data.notifications && data.notifications.length > 0) {
                        // Retrieve the permanently saved list of already-shown notification IDs
                        let notifiedIds = JSON.parse(localStorage.getItem('notified_ids') || '[]');
                        let newNotifications = data.notifications.filter(n => !notifiedIds.includes(n.id.toString()));

                        // If it's the absolute first time we load the page, don't show all historical unread ones.
                        // Instead, just quietly add them to our "already seen" list.
                        if (isFirstLoad) {
                            data.notifications.forEach(n => {
                                if (!notifiedIds.includes(n.id.toString())) {
                                    notifiedIds.push(n.id.toString());
                                }
                            });
                            localStorage.setItem('notified_ids', JSON.stringify(notifiedIds));
                            isFirstLoad = false;
                            return; 
                        }

                        if (newNotifications.length > 0) {
                            newNotifications.forEach((n, index) => {
                                setTimeout(() => {
                                    showToast(n);
                                    addNotificationToList(n);
                                    addNotificationToMainPage(n);
                                    notifiedIds.push(n.id.toString());
                                    localStorage.setItem('notified_ids', JSON.stringify(notifiedIds));
                                }, index * 600); 
                            });
                        }
                    }
                }
                isFirstLoad = false;
            })
            .catch(error => console.error('Error polling notifications:', error))
            .finally(() => {
                isPolling = false;
            });
    }

    function addNotificationToList(n) {
        let list = document.getElementById('dropdown-notification-list');
        if (!list) return;

        // If there's a "No new notifications" message, remove it
        if (list.querySelector('.p-4.text-center')) {
            list.innerHTML = '';
        }

        // Format date string similar to PHP: "Mar 17, 09:41 AM"
        let dateObj = new Date(n.created_at);
        let options = { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
        let dateString = dateObj.toLocaleString('en-US', options).replace(',', '');

        let item = document.createElement('a');
        item.href = '../../api/read_notification.php?id=' + n.id;
        item.className = 'dropdown-item py-3 border-bottom bg-light';
        item.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 me-3">
                    <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-info-circle"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-bold" style="font-size: 0.8rem;">${n.title}</h6>
                    <p class="mb-1 text-muted small text-wrap">${n.message}</p>
                    <small class="text-muted" style="font-size: 0.7rem;">
                        <i class="bi bi-clock me-1"></i>${dateString}
                    </small>
                </div>
                <div class="ms-2">
                    <span class="p-1 bg-primary rounded-circle d-block"></span>
                </div>
            </div>
        `;

        list.prepend(item);
    }

    function showToast(notification) {
        let toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center bg-white border-0 border-start border-primary border-4 shadow-lg mb-3 rounded-2';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="toast-header bg-white border-bottom-0 pb-1 pt-3">
                <div class="rounded-circle bg-primary-subtle p-1 me-2 d-flex align-items-center justify-content-center" style="width: 25px; height: 25px;">
                    <i class="bi bi-bell-fill text-primary" style="font-size: 0.8rem;"></i>
                </div>
                <strong class="me-auto text-primary text-uppercase" style="font-size: 0.85rem; letter-spacing: 0.5px; font-weight: 700;">${notification.title}</strong>
                <small class="text-muted fw-medium ms-2" style="font-size: 0.75rem;">Just now</small>
                <button type="button" class="btn-close shadow-none ms-3" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body pt-1 pb-3 px-3" style="font-size:0.9rem; color: #495057;">
                ${notification.message}
                ${notification.link ? `<div class="mt-3 text-end"><a href="../../api/read_notification.php?id=${notification.id}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-sm fw-medium" style="font-size: 0.75rem;">View Details</a></div>` : ''}
            </div>
        `;
        
        // Add to DOM
        document.getElementById('toast-notification-container').appendChild(toastEl);
        
        // Play sound (optional, kept simple and silent by default, uncomment to enable)
        let audio = new Audio('../../assets/sounds/sound.mp3');
        audio.volume = 0.3;
        
        // Attempt to play immediately
        let playPromise = audio.play();
        if (playPromise !== undefined) {
            playPromise.catch(error => {
                // If blocked because the user hasn't clicked yet, try playing it on their next click
                let playOnInteract = function() {
                    audio.play().catch(e => {}); 
                    document.removeEventListener('click', playOnInteract);
                    document.removeEventListener('keydown', playOnInteract);
                };
                document.addEventListener('click', playOnInteract);
                document.addEventListener('keydown', playOnInteract);
                console.log("Audio playback waiting for user interaction...");
            });
        }

        let toastObj = new bootstrap.Toast(toastEl, {
            delay: 7000, 
            autohide: true
        });
        toastObj.show();
        
        // Add an event listener to clean up DOM after it hides
        toastEl.addEventListener('hidden.bs.toast', () => {
             toastEl.remove();
        });
    }

    function addNotificationToMainPage(n) {
        let list = document.getElementById('main-notification-list');
        let placeholder = document.getElementById('no-notifications-placeholder');

        if (placeholder) {
            placeholder.remove();
        }

        // If list doesn't exist but we are on notifications.php (detect by .main-content presence)
        if (!list && document.querySelector('.main-content') && window.location.pathname.includes('notifications.php')) {
            let container = document.querySelector('.main-content .card-body');
            if (container) {
                list = document.createElement('div');
                list.id = 'main-notification-list';
                list.className = 'list-group list-group-flush';
                container.appendChild(list);
            }
        }

        if (!list) return;

        let dateObj = new Date(n.created_at);
        let options = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
        let dateString = dateObj.toLocaleString('en-US', options).replace(',', '');

        let item = document.createElement('a');
        item.href = n.link || '#';
        item.className = 'list-group-item list-group-item-action p-4 border-start border-primary border-4';
        item.style.backgroundColor = '#f4f6f9';
        item.style.animation = 'slideInRightToast 0.5s ease-out';
        
        item.innerHTML = `
            <div class="d-flex w-100 justify-content-between align-items-center">
                <h6 class="mb-1 fw-bold">${n.title}</h6>
                <small class="text-muted">${dateString}</small>
            </div>
            <p class="mb-1 text-secondary">${n.message}</p>
            <span class="badge bg-primary rounded-pill mt-2">New</span>
        `;

        list.prepend(item);
    }

    // Poll right away, and then every 10 seconds
    pollNotifications();
    setInterval(pollNotifications, 10000);
});
