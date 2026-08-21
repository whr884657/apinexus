/**
 * 主题三 · 认证页交互（密码可见切换）
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-th3-pw-toggle]');
        if (!btn) return;
        var wrap = btn.closest('.th3-auth__pw-wrap');
        if (!wrap) return;
        var input = wrap.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var eye = btn.querySelector('.th3-auth__eye');
        var eyeOff = btn.querySelector('.th3-auth__eye-off');
        if (eye && eyeOff) {
            eye.style.display = show ? 'none' : '';
            eyeOff.style.display = show ? '' : 'none';
        }
        btn.setAttribute('aria-label', show ? '隐藏密码' : '显示密码');
    });

    window.th3AuthShake = function () {
        var card = document.querySelector('.th3-auth__card');
        if (!card) return;
        card.classList.remove('is-shake');
        void card.offsetWidth;
        card.classList.add('is-shake');
        window.setTimeout(function () { card.classList.remove('is-shake'); }, 420);
    };

    window.th3AuthSetLoading = function (btn, loading) {
        if (!btn) return;
        btn.disabled = !!loading;
        btn.classList.toggle('is-loading', !!loading);
    };
})();
