/**
 * 青绿平台 · 认证页交互
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-st-pw-toggle]');
        if (!btn) return;
        var wrap = btn.closest('.st-auth__pw-wrap');
        if (!wrap) return;
        var input = wrap.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? '隐藏' : '显示';
        btn.setAttribute('aria-label', show ? '隐藏密码' : '显示密码');
    });

    window.stAuthShake = function () {
        var form = document.querySelector('.st-auth__form');
        if (!form) return;
        form.classList.remove('is-shake');
        void form.offsetWidth;
        form.classList.add('is-shake');
        window.setTimeout(function () { form.classList.remove('is-shake'); }, 420);
    };

    window.stAuthSetLoading = function (btn, loading) {
        if (!btn) return;
        btn.disabled = !!loading;
        btn.classList.toggle('is-loading', !!loading);
    };

    /** 登录页左侧动效：桌面端鼠标视差（原生 JS，无依赖） */
    function initLoginVisualParallax() {
        var visual = document.querySelector('[data-st-login-visual]');
        if (!visual || window.matchMedia('(max-width: 900px)').matches) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var inner = visual.querySelector('.st-auth__visual-inner');
        if (!inner) return;

        var raf = 0;
        var px = 0;
        var py = 0;

        function applyParallax() {
            raf = 0;
            inner.style.transform = 'translate(' + (px * 12) + 'px, ' + (py * 10) + 'px)';
        }

        visual.addEventListener('mousemove', function (e) {
            var rect = visual.getBoundingClientRect();
            px = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
            py = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
            if (!raf) raf = window.requestAnimationFrame(applyParallax);
        });

        visual.addEventListener('mouseleave', function () {
            px = 0;
            py = 0;
            if (!raf) raf = window.requestAnimationFrame(applyParallax);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoginVisualParallax);
    } else {
        initLoginVisualParallax();
    }
})();
