        </div>
    </div>
    </div>
</div>
<button type="button" id="backToTop" class="back-to-top" title="Back to top" aria-label="Back to top">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
</button>
<?php // Loaded on every page for the nav bell dropdown. Re-executing api.js/utils.js's
      // function declarations on pages that already loaded them is harmless (same code, last
      // definition wins), so no need to guard against the duplicate <script> tag. ?>
<script src="assets/js/api.js?v=<?= filemtime(__DIR__ . '/../assets/js/api.js') ?>"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/../assets/js/utils.js') ?>"></script>
<script src="assets/js/notif-bell.js?v=<?= filemtime(__DIR__ . '/../assets/js/notif-bell.js') ?>"></script>
<script>
(function () {
    var sidebar = document.getElementById('siteSidebar');
    var toggle = document.getElementById('sidebarToggle');
    if (!sidebar || !toggle) return;
    toggle.addEventListener('click', function () {
        var collapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('bel_sidebar_collapsed', collapsed ? '1' : '0');
    });
})();
(function () {
    var btn = document.getElementById('backToTop');
    if (!btn) return;
    function toggleVisible() {
        btn.classList.toggle('visible', window.scrollY > 300);
    }
    window.addEventListener('scroll', toggleVisible, { passive: true });
    toggleVisible();
    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>
</body>
</html>
