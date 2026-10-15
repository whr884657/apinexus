/**
 * 文件：assets/js/user-notify.js
 * 作用：用户中心站内公告（自动弹窗 + 红点；铃铛列表不可查看；控制台可看）
 *
 * 自动弹窗防重复：sessionStorage + localStorage 双记；
 * 键绑定 userId，只存「已确认的最新弹窗 content id」一个数字。
 * 红点只跟未确认弹窗走；点「我知道了」后消失。
 */
(function () {
    'use strict';

    var POLL_MS = 12000;
    var MOBILE_MQ = '(max-width: 768px)';
    var POPUP_ACK_PREFIX = 'vs_user_msg_popup_ack_u';
    var POPUP_SEEN_PREFIX = 'vs_user_msg_popup_seen_u';
    /** 旧版无用户后缀的键（仅迁移读取，不再写入） */
    var POPUP_ACK_LEGACY = 'vs_user_msg_popup_ack';
    var POPUP_SEEN_LEGACY = 'vs_user_msg_popup_seen';

    function boot() {
        if (!window.VS) {
            setTimeout(boot, 30);
            return;
        }
        init();
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtTime(t) {
        t = String(t || '');
        if (t.length >= 16) {
            return t.slice(0, 16);
        }
        return t || '—';
    }

    function storageGet(store, key) {
        try {
            if (!store) {
                return null;
            }
            return store.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function storageSet(store, key, val) {
        try {
            if (!store) {
                return;
            }
            store.setItem(key, val);
        } catch (e2) { /* 隐私模式等忽略 */ }
    }

    function parseAck(raw) {
        var n = parseInt(raw, 10);
        return n > 0 ? n : 0;
    }

    function parseSeenIds(raw) {
        if (!raw) {
            return [];
        }
        try {
            var arr = JSON.parse(raw);
            if (!Array.isArray(arr)) {
                return [];
            }
            return arr.map(function (x) {
                return parseInt(x, 10) || 0;
            }).filter(function (n) {
                return n > 0;
            });
        } catch (e) {
            return [];
        }
    }

    function maxSeenFrom(raw) {
        var ids = parseSeenIds(raw);
        var m = 0;
        for (var i = 0; i < ids.length; i++) {
            if (ids[i] > m) {
                m = ids[i];
            }
        }
        return m;
    }

    function migrateStorageKeys(uid) {
        uid = parseInt(uid, 10) || 0;
        if (uid <= 0) {
            return;
        }
        try {
            var oldAck = storageGet(localStorage, POPUP_ACK_LEGACY);
            var oldSeen = storageGet(localStorage, POPUP_SEEN_LEGACY);
            if (oldAck) {
                storageSet(localStorage, POPUP_ACK_PREFIX + uid, oldAck);
            }
            if (oldSeen) {
                storageSet(localStorage, POPUP_SEEN_PREFIX + uid, oldSeen);
            }
            try {
                localStorage.removeItem(POPUP_ACK_LEGACY);
                localStorage.removeItem(POPUP_SEEN_LEGACY);
            } catch (eClean) { /* ignore */ }
        } catch (eMig) { /* ignore */ }
    }

    function init() {
        var root = document.getElementById('vsUserNotify');
        var btn = document.getElementById('vsUserNotifyBtn');
        var panel = document.getElementById('vsUserNotifyPanel');
        var listEl = document.getElementById('vsUserNotifyList');
        var emptyEl = document.getElementById('vsUserNotifyEmpty');
        var metaEl = document.getElementById('vsUserNotifyMeta');
        var dotEl = document.getElementById('vsUserNotifyDot');
        var countEl = document.getElementById('vsUserNotifyCount');
        var bootEl = document.getElementById('vsUserNotifyBoot');
        var dashEl = document.getElementById('ucDashMsgBody');
        /** 控制台近期列表固定条数（与近期调用排行同量级） */
        var DASH_MSG_LIMIT = 8;

        // 先解析 boot，确保 userId 可用于 ack 键（只存 content id，键带用户）
        var bootPack = { user_id: 0, items: [], popups: [], has_items: 0 };
        if (bootEl) {
            try {
                var bootParsed = JSON.parse(bootEl.textContent || '{}');
                if (bootParsed && typeof bootParsed === 'object') {
                    bootPack.user_id = parseInt(bootParsed.user_id, 10) || 0;
                    bootPack.items = Array.isArray(bootParsed.items) ? bootParsed.items : [];
                    bootPack.popups = Array.isArray(bootParsed.popups) ? bootParsed.popups : [];
                    bootPack.has_items = bootParsed.has_items != null
                        ? bootParsed.has_items
                        : (bootPack.items.length > 0 ? 1 : 0);
                }
            } catch (eBoot) { /* ignore */ }
        }

        var userId = 0;
        if (root) {
            userId = parseInt(root.getAttribute('data-user-id'), 10) || 0;
        }
        if (userId <= 0) {
            userId = bootPack.user_id || 0;
        }
        if (userId <= 0 && window.VS_USER_ID) {
            userId = parseInt(window.VS_USER_ID, 10) || 0;
        }
        if (root && userId > 0) {
            root.setAttribute('data-user-id', String(userId));
        }
        migrateStorageKeys(userId);

        var ackKey = POPUP_ACK_PREFIX + String(userId);
        var seenKey = POPUP_SEEN_PREFIX + String(userId);

        var endpoint = (root && root.getAttribute('data-endpoint')) || '';
        if (!endpoint && window.VS_BASE_URL) {
            endpoint = String(window.VS_BASE_URL).replace(/\/$/, '') + '/user/notify';
        }

        /** session + local；键含 userId；兼容迁移旧全局键 */
        function readPopupAck() {
            if (userId <= 0) {
                return 0;
            }
            var ack = 0;
            ack = Math.max(ack, parseAck(storageGet(window.sessionStorage, ackKey)));
            ack = Math.max(ack, parseAck(storageGet(window.localStorage, ackKey)));
            ack = Math.max(ack, maxSeenFrom(storageGet(window.sessionStorage, seenKey)));
            ack = Math.max(ack, maxSeenFrom(storageGet(window.localStorage, seenKey)));
            if (ack <= 0) {
                var legacy = 0;
                legacy = Math.max(legacy, parseAck(storageGet(window.sessionStorage, POPUP_ACK_LEGACY)));
                legacy = Math.max(legacy, parseAck(storageGet(window.localStorage, POPUP_ACK_LEGACY)));
                legacy = Math.max(legacy, maxSeenFrom(storageGet(window.sessionStorage, POPUP_SEEN_LEGACY)));
                legacy = Math.max(legacy, maxSeenFrom(storageGet(window.localStorage, POPUP_SEEN_LEGACY)));
                if (legacy > 0) {
                    ack = legacy;
                    writePopupAck(legacy);
                }
            }
            return ack;
        }

        function writePopupAck(nid) {
            nid = parseInt(nid, 10) || 0;
            if (nid <= 0 || userId <= 0) {
                return false;
            }
            var sid = String(nid);
            storageSet(window.sessionStorage, ackKey, sid);
            storageSet(window.localStorage, ackKey, sid);
            var payload = JSON.stringify([nid]);
            storageSet(window.sessionStorage, seenKey, payload);
            storageSet(window.localStorage, seenKey, payload);
            return true;
        }

        function isPopupAcked(id) {
            var nid = parseInt(id, 10) || 0;
            if (nid <= 0) {
                return true;
            }
            return nid <= readPopupAck();
        }

        /** 仅存 content id；绑定当前用户；返回是否写入成功 */
        function markPopupSeen(id) {
            var nid = parseInt(id, 10) || 0;
            if (nid <= 0 || userId <= 0) {
                return false;
            }
            return writePopupAck(Math.max(nid, readPopupAck()));
        }

        var open = false;
        var busy = false;
        var timer = null;
        var lastSig = '';
        var lastItems = [];
        var lastPopupId = 0;
        var panelHome = panel ? panel.parentNode : null;
        var mask = document.getElementById('vsUserNotifyMask');
        if (!mask) {
            mask = document.createElement('div');
            mask.id = 'vsUserNotifyMask';
            mask.className = 'vs-notify-mask';
            mask.hidden = true;
            mask.setAttribute('aria-hidden', 'true');
        }

        function isMobile() {
            return !!(window.matchMedia && window.matchMedia(MOBILE_MQ).matches);
        }

        function latestItemId(items) {
            var m = 0;
            (items || []).forEach(function (it) {
                var id = parseInt(it && it.id, 10) || 0;
                if (id > m) {
                    m = id;
                }
            });
            return m;
        }

        /**
         * 红点只跟「未确认的自动弹窗」走（铃铛列表不可查看，不用列表最新 id）
         * 点「我知道了」写入本地 ack 后红点/角标立即消失
         */
        function setHasItemsUi(hasItems, total, items) {
            if (Array.isArray(items)) {
                lastItems = items;
            }
            total = parseInt(total, 10) || lastItems.length || 0;
            hasItems = total > 0 || lastItems.length > 0;
            var ack = readPopupAck();
            var pending = lastPopupId > 0 && lastPopupId > ack;
            if (root) {
                root.classList.toggle('has-pending', pending);
                root.setAttribute('data-has-items', hasItems ? '1' : '0');
                root.setAttribute('data-has-pending', pending ? '1' : '0');
                root.setAttribute('data-total', String(total));
            }
            if (btn) {
                btn.classList.toggle('is-emphasis', pending);
            }
            if (dotEl) {
                dotEl.hidden = !pending;
            }
            if (countEl) {
                if (!pending) {
                    countEl.hidden = true;
                    countEl.textContent = '0';
                } else {
                    countEl.hidden = false;
                    countEl.textContent = '1';
                }
            }
            if (metaEl) {
                metaEl.textContent = '筹备中';
            }
        }

        function showDetail(item) {
            if (!item || !window.VsModal || typeof VsModal.open !== 'function') {
                return Promise.resolve(false);
            }
            var msgId = parseInt(item.id, 10) || 0;
            var time = fmtTime(item.createtime);
            var bodyHtml = item.body_html
                ? String(item.body_html)
                : ('<pre class="uc-msg-detail__plain">' + esc(item.body || '') + '</pre>');
            var html = '<div class="uc-msg-detail">'
                + '<div class="uc-msg-detail__body markdown-body vs-md-body">' + bodyHtml + '</div>'
                + '<div class="uc-msg-detail__foot"><span class="uc-msg-detail__time">' + esc(time) + '</span></div>'
                + '</div>';

            return new Promise(function (resolve) {
                var finished = false;
                function confirmRead() {
                    if (finished) {
                        return;
                    }
                    finished = true;
                    if (msgId > 0) {
                        // 确认当前这条；若存在自动弹窗则一并盖过弹窗 ack
                        var ackTarget = msgId;
                        if (lastPopupId > 0) {
                            ackTarget = Math.max(ackTarget, lastPopupId);
                        }
                        markPopupSeen(ackTarget);
                        setHasItemsUi(lastItems.length > 0, lastItems.length, lastItems);
                    }
                    if (window.VsModal && typeof VsModal.close === 'function') {
                        VsModal.close(true);
                    }
                    resolve(true);
                }
                VsModal.open({
                    title: item.title || '站内公告',
                    html: html,
                    size: 'lg',
                    // 必须点「我知道了」才确认并写本地记忆（点遮罩/Esc 不写入）
                    closeOnOverlay: false,
                    closeOnEscape: false,
                    buttons: [{
                        text: '我知道了',
                        primary: true,
                        action: confirmRead
                    }]
                });
            });
        }

        function fetchDetail(id) {
            id = parseInt(id, 10) || 0;
            if (id <= 0 || !endpoint || typeof window.VS.postForm !== 'function') {
                return Promise.resolve(null);
            }
            var fd = new FormData();
            fd.append('action', 'detail');
            fd.append('id', String(id));
            return window.VS.postForm(fd, endpoint).then(function (res) {
                if (!res || Number(res.code) !== 1) {
                    throw new Error((res && res.msg) ? res.msg : '加载失败');
                }
                var item = (res.data && res.data.item) || res.item || null;
                return item;
            });
        }

        function openDetailById(id) {
            return fetchDetail(id).then(function (item) {
                if (!item) {
                    throw new Error('公告不存在或无权查看');
                }
                return showDetail(item).then(function (okShow) {
                    if (!okShow) {
                        throw new Error('弹窗组件未就绪');
                    }
                    return item;
                });
            }).catch(function (err) {
                if (window.VS && VS.showMessage) {
                    window.VS.showMessage((err && err.message) ? err.message : '加载失败', 'error');
                }
                return null;
            });
        }

        window.VSUserNotify = {
            openDetail: openDetailById,
            refresh: function () {
                refresh(true);
            }
        };

        /** 铃铛列表不可查看：固定筹备空态，不渲染公告条目 */
        function renderList() {
            if (listEl) {
                listEl.innerHTML = '';
                listEl.hidden = true;
            }
            if (emptyEl) {
                emptyEl.hidden = false;
            }
            if (metaEl) {
                metaEl.textContent = '筹备中';
            }
        }

        function renderDash(items) {
            if (!dashEl) {
                return;
            }
            items = Array.isArray(items) ? items.slice(0, DASH_MSG_LIMIT) : [];
            if (items.length === 0) {
                dashEl.innerHTML = '<p class="uc-dash__empty">暂无站内公告</p>';
                return;
            }
            var html = '<div class="uc-dash__msg-list" role="list">';
            items.forEach(function (it) {
                var pin = Number(it.ispinned) === 1;
                html += '<button type="button" class="uc-dash__msg-row" role="listitem" data-msg-id="'
                    + esc(it.id) + '" title="' + esc(it.title || '') + '">';
                html += '<span class="uc-dash__msg-title">'
                    + esc(it.title || '（无标题）') + '</span>';
                if (pin) {
                    html += '<span class="uc-dash__msg-pin">置顶</span>';
                }
                html += '<span class="uc-dash__msg-time">' + esc(fmtTime(it.createtime)) + '</span>';
                html += '</button>';
            });
            html += '</div>';
            dashEl.innerHTML = html;
        }

        function signature(items) {
            var parts = [];
            (items || []).forEach(function (it) {
                parts.push(String(it.id || '') + ':' + String(it.ispinned || 0) + ':' + String(it.ispopup || 0));
            });
            return parts.join('|');
        }

        function applyPack(pack, opts) {
            opts = opts || {};
            pack = pack || {};
            var items = Array.isArray(pack.items) ? pack.items : [];
            var popups = Array.isArray(pack.popups) ? pack.popups : [];
            var hasItems = !!pack.has_items || items.length > 0;
            lastPopupId = 0;
            if (popups.length > 0) {
                lastPopupId = parseInt(popups[0].id, 10) || 0;
            }
            var sig = signature(items) + '|p' + lastPopupId;
            if (sig !== lastSig) {
                lastSig = sig;
                renderList();
                renderDash(items);
                setHasItemsUi(hasItems, items.length, items);
            } else {
                // 列表未变也要按本地 ack 刷新红点（例如刚点「我知道了」）
                renderList();
                setHasItemsUi(hasItems, items.length, items);
                if (opts.forceDash) {
                    renderDash(items);
                }
            }
            if (opts.runPopup !== false) {
                maybeAutoPopup(popups);
            }
            return hasItems;
        }

        function maybeAutoPopup(popups) {
            popups = Array.isArray(popups) ? popups : [];
            if (popups.length === 0) {
                return;
            }
            // 约定：服务端 popups 至多 1 条（最新可弹窗）；旧篇一律不自动弹
            var next = popups[0];
            var sid = String(next && next.id != null ? next.id : '');
            if (!sid) {
                return;
            }
            if (isPopupAcked(sid)) {
                setHasItemsUi(lastItems.length > 0, lastItems.length, lastItems);
                return;
            }
            // 仅展示；本地 ack 必须等用户点「我知道了」
            openDetailById(sid);
        }

        function mountDrawer(on) {
            if (!panel) {
                return;
            }
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
            if (!btn || !panel) {
                return;
            }
            var want = !!next;
            var mobile = isMobile();
            open = want;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.classList.toggle('is-open', open);
            if (root) {
                root.classList.toggle('is-open', open);
            }

            if (mobile) {
                if (open) {
                    panel.hidden = false;
                    panel.classList.add('is-open');
                    mountDrawer(true);
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
            if (!endpoint || typeof window.VS.postForm !== 'function') {
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
                return {
                    items: res.items || [],
                    popups: res.popups || [],
                    has_items: res.has_items
                };
            }).catch(function () {
                return null;
            });
        }

        function refresh(force, opts) {
            opts = opts || {};
            if (busy && !force) {
                return;
            }
            busy = true;
            postInbox().then(function (pack) {
                busy = false;
                if (pack) {
                    applyPack(pack, { runPopup: !!opts.runPopup });
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

        function onDashMsgClick(e) {
            var hit = e.target.closest('[data-msg-id]');
            if (!hit || !dashEl || !dashEl.contains(hit)) {
                return;
            }
            e.preventDefault();
            var id = hit.getAttribute('data-msg-id');
            if (open) {
                setOpen(false);
            }
            openDetailById(id);
        }

        // 铃铛列表不可查看；仅控制台近期站内公告可点开
        if (dashEl) {
            dashEl.addEventListener('click', onDashMsgClick);
        }

        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                setOpen(!open);
            });
        }

        mask.addEventListener('click', function () {
            if (open) {
                setOpen(false);
            }
        });

        document.addEventListener('click', function (e) {
            if (!open || isMobile() || !root || !panel) {
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

        if (!bootEl && root) {
            bootPack.has_items = root.getAttribute('data-has-items') === '1' ? 1 : 0;
        }

        applyPack(bootPack, { runPopup: false, forceDash: true });
        refresh(true, { runPopup: true });
        startPoll();
    }

    document.addEventListener('DOMContentLoaded', boot);
})();

