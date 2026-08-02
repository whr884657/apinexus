/**
 * 主题二 slate · 404 动效（标题打字 / 返回）
 */
(function () {
    var cfg = window.__ST404__ || {};
    var back = document.getElementById('st404Back');
    var title = document.getElementById('st404Title');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (back) {
        back.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = cfg.home || '/';
            }
        });
    }

    if (!title || reduce) {
        return;
    }

    var full = title.textContent || '';
    if (!full) {
        return;
    }
    title.textContent = '';
    title.classList.add('is-typed');
    var i = 0;

    function tick() {
        title.textContent = full.slice(0, ++i);
        if (i < full.length) {
            window.setTimeout(tick, 28 + Math.random() * 36);
        } else {
            window.setTimeout(function () {
                title.classList.remove('is-typed');
            }, 900);
        }
    }

    window.setTimeout(tick, 280);
})();
