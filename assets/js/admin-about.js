/**
 * 文件：assets/js/admin-about.js
 * 作用：后台关于页入场动效（数据由 PHP / AboutCatalog 注入，本脚本不拉配置）
 */
(function () {
  'use strict';

  var root = document.getElementById('adminAboutPage');
  if (!root) {
    return;
  }

  function reveal() {
    root.classList.add('is-ready');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', reveal);
  } else {
    reveal();
  }
})();
