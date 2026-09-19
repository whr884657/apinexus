(function () {
    var wrap = document.getElementById('homeAnnouncementWrap');
    var btn = document.getElementById('homeAnnouncementBtn');
    var modalHome = document.getElementById('homeAnnouncementModal');
    var marquee = btn ? btn.querySelector('.home-announcement-marquee') : null;
    var track = btn ? btn.querySelector('.home-announcement-track') : null;
    var dataEl = document.getElementById('feer-announcement-client-data');

    /* 打开前挂到 body。留在固定定位的公告条盒子里会被顶栏盖住，点不到关闭（E299） */
    if (modalHome && modalHome.parentNode !== document.body) {
        document.body.appendChild(modalHome);
    }

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
        el.removeAttribute('inert');
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('home-announcement-modal-open');
        var focusEl = el.querySelector('.home-announcement-btn-ok') || el.querySelector('button');
        if (focusEl && typeof focusEl.focus === 'function') {
            try { focusEl.focus(); } catch (e) { /* ignore */ }
        }
    }

    function closeModalEl(modal) {
        if (!modal) return;
        var active = document.activeElement;
        if (active && modal.contains(active) && typeof active.blur === 'function') {
            try { active.blur(); } catch (e) { /* ignore */ }
        }
        if (btn && typeof btn.focus === 'function') {
            try { btn.focus({ preventScroll: true }); } catch (e2) {
                try { btn.focus(); } catch (e3) { /* ignore */ }
            }
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('inert', '');
        if (!document.querySelector('.home-announcement-modal.is-open')) {
            document.body.classList.remove('home-announcement-modal-open');
        }
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('[data-announcement-dismiss="1"]')) {
            markDismissed();
            closeModalEl(t.closest('.home-announcement-modal'));
            return;
        }
        if (t.closest('[data-close-announcement="1"]')) {
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
