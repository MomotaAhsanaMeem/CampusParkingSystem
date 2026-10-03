<?php
// footer.php — closes the page shell opened by header.php.
?>
</main><!-- /#main-content -->

<footer class="site-footer" role="contentinfo">
    <div class="site-footer-inner">
        <div class="flex items-center gap-sm">
            <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="CampusPark" style="width:24px;height:24px;border-radius:6px;object-fit:cover;" aria-hidden="true" />
            <span class="footer-copy">CampusPark &copy; <?= date('Y') ?></span>
        </div>
        <ul class="footer-links" role="list">
            <li><a href="<?= BASE_URL ?>/public/privacy-policy.php">Privacy Policy</a></li>
            <li><a href="<?= BASE_URL ?>/public/terms-of-service.php">Terms of Service</a></li>
            <li><a href="#">Support</a></li>
        </ul>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time() ?>"></script>

<?php if (!empty($_SESSION['user_id'])): ?>
<script>
// Reminder poller — simulates a background cron without a server scheduler.
// Calls check-reminders.php every 60 s while a logged-in page is open.
(function () {
    var INTERVAL_MS = 60000;
    var endpoint    = '<?= BASE_URL ?>/public/check-reminders.php';
    function pollReminders() {
        fetch(endpoint, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .catch(function () {}); // silently ignore network errors
    }
    pollReminders(); // fire once immediately on page load, then every 60 s
    setInterval(pollReminders, INTERVAL_MS);
})();
</script>
<?php endif; ?>
</body>
</html>
