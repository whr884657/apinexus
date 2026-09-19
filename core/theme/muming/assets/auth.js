/**
 * 主题四 · 认证页交互（密码可见切换）
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-th5-pw-toggle]');
        if (!btn) return;
        var wrap = btn.closest('.th5-auth__pw-wrap');
        if (!wrap) return;
        var input = wrap.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var eye = btn.querySelector('.th5-auth__eye');
        var eyeOff = btn.querySelector('.th5-auth__eye-off');
        if (eye && eyeOff) {
            eye.style.display = show ? 'none' : '';
            eyeOff.style.display = show ? '' : 'none';
        }
        btn.setAttribute('aria-label', show ? '隐藏密码' : '显示密码');
    });

    window.TH5AuthShake = function () {
        var card = document.querySelector('.th5-auth__card');
        if (!card) return;
        card.classList.remove('is-shake');
        void card.offsetWidth;
        card.classList.add('is-shake');
        window.setTimeout(function () { card.classList.remove('is-shake'); }, 420);
    };

    window.TH5AuthSetLoading = function (btn, loading) {
        if (!btn) return;
        btn.disabled = !!loading;
        btn.classList.toggle('is-loading', !!loading);
    };
})();
