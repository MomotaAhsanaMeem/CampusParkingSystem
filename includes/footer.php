<?php
// footer.php — closes the page shell opened by header.php.
?>
</main><!-- /#main-content -->

<footer class="site-footer" role="contentinfo">
    <div class="site-footer-inner">
        <div class="flex items-center gap-sm">
            <img src="/parking-system/assets/images/logo.jpg" alt="CampusPark" style="width:24px;height:24px;border-radius:6px;object-fit:cover;" aria-hidden="true" />
            <span class="footer-copy">CampusPark &copy; <?= date('Y') ?></span>
        </div>
        <ul class="footer-links" role="list">
            <li><a href="#">Privacy Policy</a></li>
            <li><a href="#">Terms of Service</a></li>
            <li><a href="#">Support</a></li>
        </ul>
    </div>
</footer>

<script src="/parking-system/assets/js/main.js?v=<?= file_exists($_SERVER['DOCUMENT_ROOT'] . '/parking-system/assets/js/main.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/parking-system/assets/js/main.js') : time() ?>"></script>
</body>
</html>
