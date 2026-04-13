<!-- App Switcher Overlay -->
<div id="appSwitcherOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(10, 47, 68, 0.9); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); z-index: 999999; justify-content: center; align-items: center; flex-direction: column; color: white;">
    <div class="switcher-content text-center">
        <!-- Modern SDO Logo Spinner -->
        <div class="spinner-container mb-4" style="position: relative; width: 120px; height: 120px; margin: 0 auto;">
            <div class="outer-ring" style="position: absolute; width: 100%; height: 100%; border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid #fff; border-radius: 50%; animation: switcher-spin 1s linear infinite;"></div>
            <img src="/sdo-portal/leave-monitoring/assets/images/SDO-Logo.png" alt="SDO Logo" style="position: absolute; width: 70%; top: 15%; left: 15%; animation: switcher-pulse 2s ease-in-out infinite;">
        </div>
        <h4 id="switcherText" class="fw-bold" style="letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px; font-family: 'Segoe UI', Roboto, sans-serif;">Switching to...</h4>
        <p id="switcherAppName" class="fs-5 opacity-75" style="font-family: 'Segoe UI', Roboto, sans-serif;"></p>
        
        <!-- Progress Bar -->
        <div class="progress mt-4" style="width: 250px; height: 6px; background: rgba(255,255,255,0.2); margin: 0 auto; border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);">
            <div id="switcherProgress" style="width: 0%; height: 100%; background: #fff; transition: width 0.8s ease-in-out;"></div>
        </div>
    </div>
</div>

<style>
    @keyframes switcher-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes switcher-pulse { 0%, 100% { transform: scale(1); opacity: 0.9; } 50% { transform: scale(1.1); opacity: 1; } }
    
    #appSwitcherOverlay.active {
        display: flex !important;
        animation: switcher-fadeIn 0.3s ease-out forwards;
    }
    
    @keyframes switcher-fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<script>
    function switchApp(targetUrl, appName) {
        const overlay = document.getElementById('appSwitcherOverlay');
        const appNameText = document.getElementById('switcherAppName');
        const progress = document.getElementById('switcherProgress');
        
        if (!overlay) return;
        
        // Disable scroll
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        
        appNameText.textContent = appName;
        overlay.classList.add('active');
        
        // Animate progress bar
        setTimeout(() => {
            progress.style.width = '100%';
        }, 50);
        
        // Redirect after animation
        setTimeout(() => {
            window.location.href = targetUrl;
        }, 1000);
    }
</script>
