<?php
/**
 * 前台公共底部
 */
?>
</main>
<footer class="site-footer">
  <div class="container">
    <?php $footerText = trim(setting('footer_text')); ?>
    <p><?= $footerText !== '' ? e($footerText) : '🌐 ' . e(site_name()) . ' · 常用网址导航 · Powered by PHP + MySQL' ?></p>
  </div>
</footer>
<script src="assets/js/main.js?v=<?= (int)filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
