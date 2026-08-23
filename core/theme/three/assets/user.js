/**
 * 主题三 · 用户中心控制台轻交互（侧栏见 shell/user-shell.js）
 */
(function () {
    'use strict';

    function initDashPress() {
        var cards = document.querySelectorAll('#ucDashboard [data-uc-press]');
        if (!cards.length) {
            return;
        }
        cards.forEach(function (el) {
            el.addEventListener('pointerdown', function () {
                el.classList.add('is-pressed');
            });
            el.addEventListener('pointerup', function () {
                el.classList.remove('is-pressed');
            });
            el.addEventListener('pointerleave', function () {
                el.classList.remove('is-pressed');
            });
            el.addEventListener('pointercancel', function () {
                el.classList.remove('is-pressed');
            });
        });
    }

    function initDashAvatarShake() {
        var box = document.getElementById('ucDashAvatarBox');
        var img = document.getElementById('ucDashAvatarImg');
        if (!box || !img) {
            return;
        }
        function shake() {
            img.classList.remove('uc-dash__avatar--shake');
            void img.offsetWidth;
            img.classList.add('uc-dash__avatar--shake');
            window.setTimeout(function () {
                img.classList.remove('uc-dash__avatar--shake');
            }, 600);
        }
        window.setTimeout(shake, 800);
        box.addEventListener('click', shake);
    }

    function boot() {
        initDashPress();
        initDashAvatarShake();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
