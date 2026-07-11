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
        var card = document.querySelector('.st-auth__card');
        if (!card) return;
        card.classList.remove('is-shake');
        void card.offsetWidth;
        card.classList.add('is-shake');
        window.setTimeout(function () { card.classList.remove('is-shake'); }, 420);
    };

    window.stAuthSetLoading = function (btn, loading) {
        if (!btn) return;
        btn.disabled = !!loading;
        btn.classList.toggle('is-loading', !!loading);
    };
})();
