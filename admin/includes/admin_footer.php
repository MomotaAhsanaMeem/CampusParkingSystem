<?php
// admin/includes/admin_footer.php — Shared Admin Footer Template
?>
        </main><!-- /.admin-body -->

        <!-- Admin System Footer -->
        <footer style="padding:16px 28px; border-top:1px solid var(--clr-border); background:var(--clr-surface); font-size:12px; color:var(--clr-text-muted); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span>CampusPark Management Suite</span>
                <span>&bull;</span>
                <span>Zone Live Radar v2.4</span>
            </div>
            <div>
                <span>Server Time: <?= date('M j, Y — g:i A') ?> (UTC+6)</span>
            </div>
        </footer>

    </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- Core Main Scripts for Theme Toggle & Utilities -->
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= file_exists(__DIR__ . '/../../assets/js/main.js') ? filemtime(__DIR__ . '/../../assets/js/main.js') : time() ?>"></script>

<!-- Admin Sidebar Mobile Interactivity -->
<script>
(function() {
    var menuBtn = document.getElementById('adminMenuToggle');
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('adminSidebarOverlay');
    var closeBtn = document.getElementById('adminSidebarCloseBtn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('admin-sidebar--open');
        if (overlay) overlay.classList.add('admin-sidebar-overlay--visible');
        if (closeBtn) closeBtn.style.display = 'inline-flex';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('admin-sidebar--open');
        if (overlay) overlay.classList.remove('admin-sidebar-overlay--visible');
        if (closeBtn) closeBtn.style.display = 'none';
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('admin-sidebar--open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    // Auto-close drawer when window resizes back to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });
})();
</script>

</body>
</html>
