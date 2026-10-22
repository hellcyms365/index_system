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
});
