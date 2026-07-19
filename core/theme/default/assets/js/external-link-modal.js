(function () {
    function injectStyle() {
        if (document.getElementById('external-link-modal-style')) return;
        var css = ''
        + ':root{'
        + '--modal-open-dur:250ms;'
        + '--modal-close-dur:150ms;'
        + '--modal-scale:0.96;'
        + '--modal-scale-close:0.96;'
        + '--modal-ease:cubic-bezier(0.22,1,0.36,1);'
        + '}'
        + '.external-link-mask{position:fixed;inset:0;background:transparent;backdrop-filter:none;z-index:9998;opacity:0;pointer-events:none;transition:opacity var(--modal-open-dur) var(--modal-ease);}'
        + '.external-link-mask.is-open{opacity:1;pointer-events:auto;}'
        + '.external-link-mask.is-closing{opacity:0;pointer-events:none;transition:opacity var(--modal-close-dur) var(--modal-ease);}'
        + '.external-link-dialog.t-modal{position:fixed;z-index:9999;left:50%;top:50%;max-width:420px;width:90%;background:var(--bg-panel);border-radius:14px;border:1px solid var(--border-color);box-shadow:0 18px 40px rgba(15,23,42,0.7);padding:1.4rem 1.5rem 1.25rem;transform-origin:center;transform:translate(-50%,-50%) scale(var(--modal-scale));opacity:0;pointer-events:none;transition:transform var(--modal-open-dur) var(--modal-ease),opacity var(--modal-open-dur) var(--modal-ease);will-change:transform,opacity;}'
        + '.external-link-dialog.t-modal.is-open{transform:translate(-50%,-50%) scale(1);opacity:1;pointer-events:auto;}'
        + '.external-link-dialog.t-modal.is-closing{transform:translate(-50%,-50%) scale(var(--modal-scale-close));opacity:0;pointer-events:none;transition:transform var(--modal-close-dur) var(--modal-ease),opacity var(--modal-close-dur) var(--modal-ease);}'
        + '[data-theme="light"] .external-link-dialog{background:#ffffff;box-shadow:0 16px 32px rgba(148,163,184,0.35);}'
        + '.external-link-title{font-size:.95rem;font-weight:600;margin-bottom:.35rem;}'
        + '.external-link-subtitle{font-size:.78rem;color:var(--text-muted);margin-bottom:.85rem;}'
        + '.external-link-url{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-size:.75rem;color:var(--accent-primary);background:rgba(15,23,42,.7);border-radius:8px;border:1px solid var(--border-color);padding:.5rem .6rem;word-break:break-all;margin-bottom:.9rem;}'
        + '[data-theme="light"] .external-link-url{background:#f9fafb;}'
        + '.external-link-actions{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:flex-end;}'
        + '.external-link-btn{border-radius:999px;font-size:.78rem;padding:.45rem .95rem;border:1px solid var(--border-color);background:transparent;color:var(--text-muted);cursor:pointer;}'
        + '.external-link-btn-primary{border-color:var(--accent-primary);background:var(--accent-primary);color:#020817;}'
        + '@media (prefers-reduced-motion:reduce){.external-link-dialog.t-modal,.external-link-mask{transition:none!important;}}';
        var style = document.createElement('style');
        style.id = 'external-link-modal-style';
        style.textContent = css;
        document.head.appendChild(style);
    }

    function createModal() {
        injectStyle();
        if (document.getElementById('external-link-mask')) return;

        var mask = document.createElement('div');
        mask.id = 'external-link-mask';
        mask.className = 'external-link-mask';

        var dialog = document.createElement('div');
        dialog.id = 'external-link-dialog';
        dialog.className = 'external-link-dialog t-modal';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');

        dialog.innerHTML = ''
          + '<div class="external-link-title" id="external-link-title">外部链接跳转提示</div>'
          + '<div class="external-link-subtitle" id="external-link-subtitle">你即将访问外部网站：</div>'
          + '<div class="external-link-url" id="external-link-url"></div>'
          + '<div class="external-link-actions">'
          + '  <button type="button" class="external-link-btn" id="external-link-cancel">取消</button>'
          + '  <button type="button" class="external-link-btn external-link-btn-primary" id="external-link-confirm">继续访问</button>'
          + '</div>';

        document.body.appendChild(mask);
        document.body.appendChild(dialog);
    }

    document.addEventListener('DOMContentLoaded', function () {
        createModal();

        var mask = document.getElementById('external-link-mask');
        var dialog = document.getElementById('external-link-dialog');
        if (!mask || !dialog) return;

        var urlEl = document.getElementById('external-link-url');
        var titleEl = document.getElementById('external-link-title');
        var subEl = document.getElementById('external-link-subtitle');
        var cancelBtn = document.getElementById('external-link-cancel');
        var confirmBtn = document.getElementById('external-link-confirm');

        var pendingHref = null;
        var closeTimer = null;
        var closeMs = parseFloat(
            getComputedStyle(document.documentElement).getPropertyValue('--modal-close-dur')
        ) || 150;

        function openModal(href, type) {
            pendingHref = href;
            var displayUrl = href;
            try {
                var u = new URL(href, window.location.origin);
                displayUrl = u.href;
            } catch (e) {}

            if (type === 'friend') {
                if (titleEl) titleEl.textContent = '友情链接跳转提示';
                if (subEl) subEl.textContent = '你即将访问本站收录的友情链接：';
            } else {
                if (titleEl) titleEl.textContent = '外部链接跳转提示';
                if (subEl) subEl.textContent = '你即将访问外部网站：';
            }

            if (urlEl) urlEl.textContent = displayUrl;

            if (closeTimer) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }
            mask.classList.remove('is-closing');
            dialog.classList.remove('is-closing');
            mask.classList.add('is-open');
            dialog.classList.add('is-open');
        }

        function closeModal() {
            if (!dialog.classList.contains('is-open') && !mask.classList.contains('is-open')) return;

            mask.classList.remove('is-open');
            dialog.classList.remove('is-open');
            mask.classList.add('is-closing');
            dialog.classList.add('is-closing');
            pendingHref = null;

            if (closeTimer) clearTimeout(closeTimer);
            closeTimer = setTimeout(function () {
                mask.classList.remove('is-closing');
                dialog.classList.remove('is-closing');
                closeTimer = null;
            }, closeMs);
        }

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        mask.addEventListener('click', closeModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (!pendingHref) return;
                window.location.href = pendingHref;
            });
        }

        // 统一拦截：友情链接 + 其它外部链接（host 不同）
        var links = document.querySelectorAll('a[href]');
        links.forEach(function (a) {
            var href = a.getAttribute('href');
            if (!href) return;
            if (href.startsWith('#')) return;
            if (href.toLowerCase().startsWith('javascript:')) return;

            // 判断是否为友情链接或合作伙伴（这些链接直接跳转，不弹窗）
            var isFriend = !!(a.closest('.footer-links') || a.closest('.links-grid') || a.closest('.partners-grid'));

            // 底部板块（版权、备案、自定义底栏等）直接跳转，不弹窗
            if (a.closest('footer')) return;

            var isExternal = false;
            var u;
            try {
                u = new URL(href, window.location.origin);
                if (u.origin !== window.location.origin) {
                    isExternal = true;
                }
            } catch (e) {
                // ignore parse errors
            }

            // 申请友链页不弹窗，直接跳转
            if (u && (u.pathname.indexOf('/applylink') !== -1 || u.pathname.indexOf('applylink.php') !== -1)) return;

            // 合作伙伴直接跳转，不弹窗
            if (a.closest('.partners-grid')) return;

            if (!isFriend && !isExternal) return;

            a.addEventListener('click', function (e) {
                var h = a.getAttribute('href');
                if (!h) return;
                e.preventDefault();
                openModal(h, isFriend ? 'friend' : 'external');
            });
        });
    });
})();
