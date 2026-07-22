(function () {
    var wrap = document.getElementById('homeAnnouncementWrap');
    var btn = document.getElementById('homeAnnouncementBtn');
    var modalHome = document.getElementById('homeAnnouncementModal');
    var marquee = btn ? btn.querySelector('.home-announcement-marquee') : null;
    var track = btn ? btn.querySelector('.home-announcement-track') : null;
    var dataEl = document.getElementById('feer-announcement-client-data');

    var LS_DISMISS_PREFIX = 'feer_announcement_dismiss_';
    var annDataCache = null;

    function getAnnData() {
        if (annDataCache) return annDataCache;
        if (!dataEl) return null;
        try {
            annDataCache = JSON.parse(dataEl.textContent);
        } catch (e) {
            annDataCache = null;
        }
        return annDataCache;
    }

    function dismissKey() {
        var data = getAnnData();
        var key = (data && data.home && data.home.popup_key) ? String(data.home.popup_key) : 'default';
        return LS_DISMISS_PREFIX + key;
    }

    function isDismissed() {
        try {
            return localStorage.getItem(dismissKey()) === '1';
        } catch (e) {
            return false;
        }
    }

    function markDismissed() {
        try {
            localStorage.setItem(dismissKey(), '1');
        } catch (e) { /* ignore */ }
    }

    var hydrated = false;
    function hydrateAnnouncementModals() {
        if (hydrated) return;
        var data = getAnnData();
        if (!data || !modalHome) return;
        var ht = modalHome.querySelector('#homeAnnouncementModalTitle') || modalHome.querySelector('.home-announcement-modal__title');
        var hb = modalHome.querySelector('[data-announcement-body="home"]');
        if (data.home && ht && hb) {
            ht.textContent = data.home.title || '';
            hb.innerHTML = data.home.html || '';
        }
        hydrated = true;
    }

    function openModal(el) {
        if (!el) return;
        hydrateAnnouncementModals();
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('home-announcement-modal-open');
    }

    function closeModalEl(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.home-announcement-modal.is-open')) {
            document.body.classList.remove('home-announcement-modal-open');
        }
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.getAttribute) return;
        if (t.getAttribute('data-announcement-dismiss') === '1') {
            markDismissed();
            closeModalEl(t.closest('.home-announcement-modal'));
            return;
        }
        if (t.getAttribute('data-close-announcement') === '1') {
            // 「我知道了」/关闭：仅关闭，下次访问仍可自动弹出
            closeModalEl(t.closest('.home-announcement-modal'));
        }
    });

    if (btn && modalHome) {
        btn.addEventListener('click', function () {
            openModal(modalHome);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (modalHome && modalHome.classList.contains('is-open')) {
            closeModalEl(modalHome);
        }
    });

    function setupMarqueeSpeed() {
        if (!marquee || !track) return;
        var speedPxPerSecond = 36;
        var startX = marquee.clientWidth;
        var endX = -track.scrollWidth;
        var distance = startX - endX;
        var durationSec = distance > 0 ? (distance / speedPxPerSecond) : 20;
        track.style.setProperty('--notice-start', startX + 'px');
        track.style.setProperty('--notice-end', endX + 'px');
        track.style.setProperty('--notice-duration', durationSec.toFixed(2) + 's');
        track.classList.add('is-ready');
    }

    function revealBanner() {
        setupMarqueeSpeed();
        if (wrap) {
            wrap.classList.remove('home-announcement-wrap--pending');
            wrap.classList.add('home-announcement-wrap--ready');
        }
    }

    requestAnimationFrame(revealBanner);
    window.addEventListener('resize', setupMarqueeSpeed);

    // 弹窗公告：自动弹出；仅「不再提示」写入本地存储后不再弹出（清缓存可恢复）
    (function tryAutoPopup() {
        var data = getAnnData();
        if (!data || !data.home || !data.home.autopopup || !modalHome) {
            return;
        }
        if (isDismissed()) {
            return;
        }
        setTimeout(function () {
            openModal(modalHome);
        }, 600);
    })();
})();
