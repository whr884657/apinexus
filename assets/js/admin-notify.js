/**
 * 文件：assets/js/admin-notify.js
 * 作用：管理后台顶栏铃铛待办通知（电脑下拉 / 手机底部抽屉 + 轮询）
 */
(function () {
    'use strict';

    var POLL_MS = 5000;
    var AUTO_KEY = 'vs_admin_notify_auto_open';
    var MOBILE_MQ = '(max-width: 768px)';

    function boot() {
        if (!window.VS) {
            setTimeout(boot, 30);
            return;
        }
        init();
    }

    function init() {
        var root = document.getElementById('vsAdminNotify');
        var btn = document.getElementById('vsAdminNotifyBtn');
        var panel = document.getElementById('vsAdminNotifyPanel');
        var listEl = document.getElementById('vsAdminNotifyList');
        var emptyEl = document.getElementById('vsAdminNotifyEmpty');
        var metaEl = document.getElementById('vsAdminNotifyMeta');
        var dotEl = document.getElementById('vsAdminNotifyDot');
        var countEl = document.getElementById('vsAdminNotifyCount');
        var bootEl = document.getElementById('vsAdminNotifyBoot');
        if (!root || !btn || !panel || !listEl) {
            return;
        }

        var endpoint = root.getAttribute('data-endpoint') || '';
        var open = false;
        var busy = false;
        var timer = null;
        var lastSig = '';
        var panelHome = panel.parentNode;
        var mask = document.getElementById('vsAdminNotifyMask');
        if (!mask) {
            mask = document.createElement('div');
            mask.id = 'vsAdminNotifyMask';
            mask.className = 'vs-notify-mask';
            mask.hidden = true;
            mask.setAttribute('aria-hidden', 'true');
        }

        function isMobile() {
            return !!(window.matchMedia && window.matchMedia(MOBILE_MQ).matches);
        }

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function baseUrl() {
            return (window.VS_BASE_URL || '').replace(/\/$/, '');
        }

        function resolveUrl(path) {
            path = String(path || '');
            if (path.indexOf('http') === 0) {
                return path;
            }
            if (path.charAt(0) !== '/') {
                path = '/' + path;
            }
            return baseUrl() + path;
        }

        function parseBoot() {
            if (!bootEl) {
                return [];
            }
            try {
                var raw = JSON.parse(bootEl.textContent || '[]');
                return Array.isArray(raw) ? raw : [];
            } catch (e) {
                return [];
            }
        }

        function signature(items, total) {
            var parts = [String(total || 0)];
            (items || []).forEach(function (it) {
                parts.push((it.id || '') + ':' + (it.count || 0));
            });
            return parts.join('|');
        }

        function setPendingUi(hasPending, total) {
            total = parseInt(total, 10) || 0;
            root.classList.toggle('has-pending', !!hasPending);
            btn.classList.toggle('is-emphasis', !!hasPending);
            if (dotEl) {
                dotEl.hidden = !hasPending;
            }
            if (countEl) {
                if (!hasPending) {
                    countEl.hidden = true;
                    countEl.textContent = '0';
                } else {
                    countEl.hidden = false;
                    countEl.textContent = total > 99 ? '99+' : String(total);
                }
            }
            if (metaEl) {
                metaEl.textContent = hasPending ? ('共 ' + total + ' 项') : '暂无待办';
            }
            root.setAttribute('data-has-pending', hasPending ? '1' : '0');
            root.setAttribute('data-total', String(total));
        }

        function toneClass(tone) {
            tone = String(tone || 'info');
            if (tone === 'warning' || tone === 'danger' || tone === 'info') {
                return 'is-' + tone;
            }
            return 'is-info';
        }

        function renderItems(items) {
            items = Array.isArray(items) ? items : [];
            if (items.length === 0) {
                listEl.innerHTML = '';
                if (emptyEl) {
                    emptyEl.hidden = false;
                }
                return;
            }
            if (emptyEl) {
                emptyEl.hidden = true;
            }
            var html = '';
            items.forEach(function (it) {
                var icon = esc(it.icon || 'bell');
                html += '<a class="vs-notify-item ' + toneClass(it.tone) + '" href="'
                    + esc(resolveUrl(it.url || '#')) + '">';
                html += '<span class="vs-notify-item__icon" aria-hidden="true">'
                    + '<i class="vs-icon vs-icon--' + icon + '"></i></span>';
                html += '<span class="vs-notify-item__body">';
                html += '<span class="vs-notify-item__row">';
                html += '<span class="vs-notify-item__title">' + esc(it.title || '') + '</span>';
                if (it.count && Number(it.count) > 0) {
                    html += '<span class="vs-notify-item__badge">' + esc(it.count) + '</span>';
                }
                html += '</span>';
                html += '<span class="vs-notify-item__desc">' + esc(it.desc || '') + '</span>';
                html += '</span></a>';
            });
            listEl.innerHTML = html;
        }

        function applyPack(pack) {
            pack = pack || {};
            var items = Array.isArray(pack.items) ? pack.items : [];
            var total = parseInt(pack.total, 10) || 0;
            var hasPending = !!pack.has_pending || total > 0;
            var sig = signature(items, total);
            if (sig !== lastSig) {
                lastSig = sig;
                renderItems(items);
                setPendingUi(hasPending, total);
            }
            return hasPending;
        }

        function mountDrawer(on) {
            if (on) {
                if (mask.parentNode !== document.body) {
                    document.body.appendChild(mask);
                }
                if (panel.parentNode !== document.body) {
                    document.body.appendChild(panel);
                }
                mask.hidden = false;
                mask.setAttribute('aria-hidden', 'false');
                panel.classList.add('is-drawer');
                document.body.classList.add('vs-notify-drawer-open');
            } else {
                mask.hidden = true;
                mask.setAttribute('aria-hidden', 'true');
                panel.classList.remove('is-drawer');
                document.body.classList.remove('vs-notify-drawer-open');
                if (panelHome && panel.parentNode !== panelHome) {
                    panelHome.appendChild(panel);
                }
                if (mask.parentNode) {
                    mask.parentNode.removeChild(mask);
                }
            }
        }

        function setOpen(next) {
            var want = !!next;
            var mobile = isMobile();
            open = want;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.classList.toggle('is-open', open);
            root.classList.toggle('is-open', open);

            if (mobile) {
                if (open) {
                    panel.hidden = false;
                    panel.classList.add('is-open');
                    mountDrawer(true);
                    // 强制回流后再加开启动画类
                    void panel.offsetWidth;
                    panel.classList.add('is-drawer-open');
                } else {
                    panel.classList.remove('is-drawer-open');
                    panel.classList.remove('is-open');
                    panel.hidden = true;
                    mountDrawer(false);
                }
            } else {
                mountDrawer(false);
                panel.classList.remove('is-drawer', 'is-drawer-open');
                panel.hidden = !open;
                panel.classList.toggle('is-open', open);
            }

            if (open) {
                refresh(true);
            }
        }

        function postInbox() {
            if (!endpoint || !window.VS || typeof window.VS.postForm !== 'function') {
                return Promise.resolve(null);
            }
            var fd = new FormData();
            fd.append('action', 'inbox');
            return window.VS.postForm(fd, endpoint).then(function (res) {
                if (!res || Number(res.code) !== 1) {
                    return null;
                }
                if (res.data && typeof res.data === 'object') {
                    return res.data;
                }
                if (Array.isArray(res.items) || res.total !== undefined) {
                    return {
                        items: res.items || [],
                        total: res.total,
                        has_pending: res.has_pending
                    };
                }
                return null;
            }).catch(function () {
                return null;
            });
        }

        function refresh(force) {
            if (busy && !force) {
                return;
            }
            busy = true;
            postInbox().then(function (pack) {
                busy = false;
                if (pack) {
                    applyPack(pack);
                }
            }).catch(function () {
                busy = false;
            });
        }

        function startPoll() {
            stopPoll();
            timer = window.setInterval(function () {
                if (document.hidden) {
                    return;
                }
                refresh(false);
            }, POLL_MS);
        }

        function stopPoll() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!open);
        });

        mask.addEventListener('click', function () {
            if (open) {
                setOpen(false);
            }
        });

        document.addEventListener('click', function (e) {
            if (!open || isMobile()) {
                return;
            }
            if (root.contains(e.target) || panel.contains(e.target)) {
                return;
            }
            setOpen(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && open) {
                setOpen(false);
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refresh(false);
            }
        });

        if (window.matchMedia) {
            window.matchMedia(MOBILE_MQ).addEventListener('change', function () {
                if (open) {
                    setOpen(false);
                }
            });
        }

        var bootItems = parseBoot();
        var bootTotal = parseInt(root.getAttribute('data-total'), 10) || 0;
        var bootPending = root.getAttribute('data-has-pending') === '1' || bootTotal > 0;
        applyPack({
            items: bootItems,
            total: bootTotal,
            has_pending: bootPending
        });

        // 仅电脑端：有待办时本会话自动展开一次；手机端禁止自动展开
        if (bootPending && !isMobile()) {
            var already = false;
            try {
                already = sessionStorage.getItem(AUTO_KEY) === '1';
            } catch (e1) {
                already = false;
            }
            if (!already) {
                setOpen(true);
                try {
                    sessionStorage.setItem(AUTO_KEY, '1');
                } catch (e2) { /* ignore */ }
            }
        }

        startPoll();
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
