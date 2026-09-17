/**
 * 用户控制台问候：主标题淡入 + 副文案打字机
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function reduceMotion() {
    try {
      return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      return false;
    }
  }

  function typeText(el, text, speed) {
    return new Promise(function (resolve) {
      if (!el) {
        resolve();
        return;
      }
      var full = String(text || '');
      el.textContent = '';
      el.classList.add('is-typing');
      if (!full || reduceMotion()) {
        el.textContent = full;
        el.classList.remove('is-typing');
        el.classList.add('is-done');
        resolve();
        return;
      }
      var i = 0;
      var timer = setInterval(function () {
        i += 1;
        el.textContent = full.slice(0, i);
        if (i >= full.length) {
          clearInterval(timer);
          el.classList.remove('is-typing');
          el.classList.add('is-done');
          resolve();
        }
      }, speed);
    });
  }

  ready(function () {
    var root = document.getElementById('ucDashboard');
    if (!root) {
      return;
    }
    var hello = root.querySelector('[data-uc-hello]');
    var hint = root.querySelector('[data-uc-hello-hint]');
    if (hello) {
      hello.classList.add('uc-dash__hello--in');
    }
    if (!hint) {
      return;
    }
    var text = hint.getAttribute('data-text') || hint.textContent || '';
    hint.setAttribute('aria-label', text);
    typeText(hint, text, 28);
  });
})();
