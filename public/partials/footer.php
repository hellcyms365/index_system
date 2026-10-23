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
    <p class="footer-tools">
      <button class="footer-link" type="button" id="set-home-btn">设为浏览器默认页面</button>
    </p>
  </div>
</footer>

<!-- 设为浏览器默认页面弹窗 -->
<div class="modal-overlay" id="home-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="home-modal-title">
    <button class="modal-close" type="button" id="home-modal-close" aria-label="关闭">×</button>
    <h3 class="modal-title" id="home-modal-title">设为浏览器默认页面</h3>
    <p class="modal-tip">已自动复制本站地址；打开浏览器设置，粘贴一次即可完成。</p>
    <div class="home-url-box">
      <code class="home-url" id="home-url"><?= e(base_url()) ?></code>
      <button class="btn btn-primary btn-sm" type="button" id="home-copy-btn">复制地址</button>
    </div>
    <div class="browser-steps">
      <details open>
        <summary>Chrome / Edge（Windows）</summary>
        <ol>
          <li>点击右上角「⋮」→「设置」</li>
          <li>左侧选「启动时」→ 勾选「打开特定网页或一组网页」</li>
          <li>点「添加新网页」，粘贴地址，保存</li>
        </ol>
      </details>
      <details>
        <summary>360 极速 / QQ 浏览器</summary>
        <ol>
          <li>设置 →「基本设置」→「主页设置」</li>
          <li>选「自定义」，粘贴地址即可</li>
        </ol>
      </details>
      <details>
        <summary>Firefox</summary>
        <ol>
          <li>点击菜单（≡）→「设置」→「主页」</li>
          <li>「主页和新窗口」选「自定义网址」，粘贴地址</li>
        </ol>
      </details>
      <details>
        <summary>macOS Safari</summary>
        <ol>
          <li>Safari →「偏好设置」→「通用」</li>
          <li>「主页」粘贴地址；「新窗口打开时」选「主页」</li>
        </ol>
      </details>
    </div>
    <p class="modal-tip">说明：受浏览器安全限制，网页无法直接改写浏览器设置，只需复制粘贴一步，下次启动浏览器即自动打开本页。</p>
  </div>
</div>

<script src="assets/js/main.js?v=<?= (int)filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
