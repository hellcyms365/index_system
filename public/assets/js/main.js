/**
 * 上网入口 - 前台与后台共用脚本
 */
document.addEventListener('DOMContentLoaded', function () {
  // 搜索框自动聚焦（已有内容时不打扰）
  var searchInput = document.querySelector('.search-form input[type="search"]');
  if (searchInput && searchInput.value === '') {
    searchInput.focus();
  }

  // 删除 / 危险操作确认：表单带 data-confirm 属性时弹窗确认
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var message = form.getAttribute('data-confirm');
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  // ---- 设为浏览器默认页面弹窗 ----
  var homeModal = document.getElementById('home-modal');
  var homeBtn = document.getElementById('set-home-btn');
  if (homeModal && homeBtn) {
    homeBtn.addEventListener('click', function () {
      homeModal.hidden = false;
      var urlEl = document.getElementById('home-url');
      var copyBtn = document.getElementById('home-copy-btn');
      if (urlEl && copyBtn) {
        // 打开即自动复制，做到“一键”
        copyToClipboard(urlEl.textContent, function () {
          flashButton(copyBtn, '已复制 ✓');
        }, function () {});
      }
    });
    var closeBtn = document.getElementById('home-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function () { homeModal.hidden = true; });
    homeModal.addEventListener('click', function (e) {
      if (e.target === homeModal) homeModal.hidden = true;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') homeModal.hidden = true;
    });
    var manualCopy = document.getElementById('home-copy-btn');
    if (manualCopy) manualCopy.addEventListener('click', function () {
      var urlEl = document.getElementById('home-url');
      copyToClipboard(urlEl ? urlEl.textContent : '', function () {
        flashButton(manualCopy, '已复制 ✓');
      }, function () {
        if (urlEl) window.prompt('复制失败，请手动复制：', urlEl.textContent);
      });
    });
  }

  // ---- 多引擎搜索：站内提交本页，外部引擎新窗口打开 ----
  var engineUrls = {
    site: '',
    baidu: 'https://www.baidu.com/s?wd=',
    google: 'https://www.google.com/search?q=',
    bing: 'https://www.bing.com/search?q='
  };
  var searchForm = document.querySelector('.search-form[data-engine-form]');
  if (searchForm) {
    searchForm.addEventListener('submit', function (event) {
      var engineEl = searchForm.querySelector('select[name="engine"]');
      var engine = engineEl ? engineEl.value : 'site';
      if (engine === 'site') return; // 站内：按默认行为提交到 search.php
      event.preventDefault();
      var qEl = searchForm.querySelector('input[name="q"]');
      var q = qEl ? qEl.value.trim() : '';
      if (q === '') return;
      var base = engineUrls[engine];
      if (base) {
        window.open(base + encodeURIComponent(q), '_blank', 'noopener');
      }
    });
  }

  function copyToClipboard(text, done, fail) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        fallbackCopy(text, done, fail);
      });
    } else {
      fallbackCopy(text, done, fail);
    }
  }

  function fallbackCopy(text, done, fail) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      done();
    } catch (e) {
      if (fail) fail();
    }
    document.body.removeChild(ta);
  }

  function flashButton(btn, text) {
    var old = btn.textContent;
    btn.textContent = text;
    setTimeout(function () { btn.textContent = old; }, 1200);
  }
});
