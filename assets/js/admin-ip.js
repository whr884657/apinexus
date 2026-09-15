/**
 * admin-ip.js — 后台全站 IP 白名单 / 出口代理（多列 + 测试/编辑）
 * AjaxResponse 扁平：Number(res.code)===1；刷新 VsRefreshBtn（E242）
 */
(function () {
  'use strict';

  function boot() {
    if (!window.VS) {
      setTimeout(boot, 30);
      return;
    }
    init();
  }

  function init() {
    var page = document.getElementById('adminIpPage');
    if (!page) return;

    var searchInput = document.getElementById('adminIpSearch');
    var refreshBtn = document.getElementById('adminIpRefreshBtn');
    var tabs = document.getElementById('adminIpTabs');
    var allowReady = page.getAttribute('data-allow-ready') === '1';
    var proxyReady = page.getAttribute('data-proxy-ready') === '1';
    var currentTab = 'allow';

    var formOverlay = document.getElementById('adminProxyFormOverlay');
    var testOverlay = document.getElementById('adminProxyTestOverlay');
    var proxyForm = document.getElementById('adminProxyForm');
    var proxyMode = document.getElementById('adminProxyMode');
    var proxySaveBtn = document.getElementById('adminProxySaveBtn');
    var testLogEl = document.getElementById('adminProxyTestLog');
    var testCopyBtn = document.getElementById('adminProxyTestCopy');
    var lastTestPlain = '';

    if (formOverlay && formOverlay.parentNode !== document.body) {
      document.body.appendChild(formOverlay);
    }
    if (testOverlay && testOverlay.parentNode !== document.body) {
      document.body.appendChild(testOverlay);
    }

    function toast(msg, type) {
      if (window.VS && typeof window.VS.toast === 'function') {
        window.VS.toast(msg, type || 'info');
        return;
      }
      if (type === 'error') console.error(msg);
      else console.log(msg);
    }

    function postForm(fields) {
      var body = new FormData();
      Object.keys(fields || {}).forEach(function (k) {
        body.append(k, fields[k]);
      });
      return window.VS.postForm(body);
    }

    function confirmAct(message, title) {
      if (window.VsModal && typeof window.VsModal.confirm === 'function') {
        return window.VsModal.confirm(message, title || '确认', { danger: true });
      }
      if (window.VS && typeof window.VS.confirm === 'function') {
        return window.VS.confirm(message);
      }
      return Promise.resolve(window.confirm(message));
    }

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function truncate(text, max) {
      max = max || 42;
      var s = String(text == null ? '' : text);
      if (s.length <= max) return s;
      return s.slice(0, Math.max(1, max - 1)) + '…';
    }

    function openOverlay(el) {
      if (!el) return;
      el._returnFocus = document.activeElement;
      el.hidden = false;
      el.setAttribute('aria-hidden', 'false');
      el.classList.add('is-open');
      document.body.classList.add('is-overlay-open');
    }

    function closeOverlay(el) {
      if (!el) return;
      el.hidden = true;
      el.setAttribute('aria-hidden', 'true');
      el.classList.remove('is-open');
      if (!document.querySelector('.vs-overlay.is-open')) {
        document.body.classList.remove('is-overlay-open');
      }
      if (el._returnFocus && typeof el._returnFocus.focus === 'function') {
        try { el._returnFocus.focus(); } catch (e) { /* ignore */ }
        el._returnFocus = null;
      }
    }

    function bindOverlayClose(overlay) {
      if (!overlay) return;
      overlay.addEventListener('click', function (e) {
        var closer = e.target.closest ? e.target.closest('[data-overlay-close]') : null;
        if (closer && closer.getAttribute('data-overlay-close') === '1') {
          closeOverlay(overlay);
        }
      });
    }
    bindOverlayClose(formOverlay);
    bindOverlayClose(testOverlay);

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (testOverlay && testOverlay.classList.contains('is-open')) {
        closeOverlay(testOverlay);
        return;
      }
      if (formOverlay && formOverlay.classList.contains('is-open')) {
        closeOverlay(formOverlay);
      }
    });

    function ownerFromAllow(row) {
      return {
        userid: Number(row.userid || 0),
        username: String(row.username || ('用户#' + (row.userid || ''))),
        avatar: String(row.avatar || '')
      };
    }

    function ownerFromProxy(row) {
      var uid = Number(row.userid || 0);
      var name = String(row.ownername || '').trim();
      if (!name) name = '用户#' + uid;
      return {
        userid: uid,
        username: name,
        avatar: String(row.owneravatar || '')
      };
    }

    function ownerCellHtml(owner) {
      var html = '<div class="content-author-cell">';
      if (owner.avatar) {
        html += '<img class="content-author-cell__avatar" src="' + esc(owner.avatar) + '" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer">';
      } else {
        var ch = owner.username ? owner.username.charAt(0) : '?';
        html += '<span class="content-author-cell__fallback">' + esc(ch) + '</span>';
      }
      html += '<div class="content-author-cell__meta">'
        + '<span class="content-author-cell__name">' + esc(owner.username) + '</span>'
        + '</div></div>';
      return html;
    }

    function modeBadgeClass(mode) {
      return Number(mode) === 1 ? 'vs-badge--warning' : 'vs-badge--info';
    }

    function protoBadgeClass(label) {
      var p = String(label || '').toUpperCase();
      if (p === 'SOCKS5' || p === 'SOCKS4') return 'vs-badge--info';
      return 'vs-badge--default';
    }

    function endpointOf(row) {
      var mode = Number(row.mode || 0);
      var full;
      if (mode === 1) {
        full = String(row.extract || '').trim();
      } else {
        var host = String(row.host || '').trim();
        var port = Number(row.port || 0);
        full = host ? (host + (port > 0 ? (':' + port) : '')) : '';
      }
      if (!full) full = '—';
      return { full: full, short: truncate(full, 42) };
    }

    function allowSearchKey(row, owner) {
      return (owner.username + ' ' + (row.ip || '')).toLowerCase();
    }

    function proxySearchKey(row, owner) {
      var ep = endpointOf(row);
      return [
        owner.username, row.title || '', row.proxycode || '',
        row.modelabel || '', row.protolabel || '', ep.full
      ].join(' ').toLowerCase();
    }

    function allowRowHtml(row) {
      var owner = ownerFromAllow(row);
      var ip = String(row.ip || '');
      var search = allowSearchKey(row, owner);
      return '<tr data-allow-row data-user-id="' + esc(owner.userid) + '" data-ip="' + esc(ip) + '" data-search="' + esc(search) + '">'
        + '<td>' + ownerCellHtml(owner) + '</td>'
        + '<td><code class="vs-log-mono">' + esc(ip) + '</code></td>'
        + '<td class="vs-col-actions vs-content-actions-cell">'
        + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-remove-allow"'
        + ' data-user-id="' + esc(owner.userid) + '" data-ip="' + esc(ip) + '">移除</button>'
        + '</td></tr>';
    }

    function allowCardHtml(row) {
      var owner = ownerFromAllow(row);
      var ip = String(row.ip || '');
      var search = allowSearchKey(row, owner);
      return '<div class="admin-ip-card admin-ip-card--allow" data-allow-row data-user-id="' + esc(owner.userid)
        + '" data-ip="' + esc(ip) + '" data-search="' + esc(search) + '">'
        + '<div class="admin-ip-card__header">' + ownerCellHtml(owner) + '</div>'
        + '<div class="admin-ip-card__meta"><code class="vs-log-mono">' + esc(ip) + '</code></div>'
        + '<div class="admin-ip-card__actions admin-ip-card__actions--end">'
        + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-remove-allow"'
        + ' data-user-id="' + esc(owner.userid) + '" data-ip="' + esc(ip) + '">移除</button>'
        + '</div></div>';
    }

    function proxyActBtns(owner, pid, statusOn) {
      return '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-test"'
        + ' data-user-id="' + esc(owner.userid) + '" data-proxy-id="' + esc(pid) + '">测试</button>'
        + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-toggle"'
        + ' data-user-id="' + esc(owner.userid) + '" data-proxy-id="' + esc(pid) + '"'
        + ' data-status="' + (statusOn ? '0' : '1') + '">' + (statusOn ? '禁用' : '启用') + '</button>'
        + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-admin-ip-proxy-edit"'
        + ' data-user-id="' + esc(owner.userid) + '" data-proxy-id="' + esc(pid) + '">编辑</button>'
        + '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-btn--outline-danger vs-admin-ip-proxy-delete"'
        + ' data-user-id="' + esc(owner.userid) + '" data-proxy-id="' + esc(pid) + '">删除</button>';
    }

    function displayProxyTitle(title, code, pid) {
      var t = String(title || '').trim();
      var c = String(code || '').trim();
      var id = Number(pid || 0);
      var generic = false;
      if (!t) {
        generic = true;
      } else {
        var lower = t.toLowerCase().replace(/\s+/g, '');
        if (lower === '代理' || lower === '出口代理' || lower === 'ip代理'
            || lower === 'proxy' || lower === 'proxies') {
          generic = true;
        } else if (/^代理#?\d*$/.test(t)) {
          generic = true;
        }
      }
      if (!generic) {
        return { text: t, showCode: !!c };
      }
      if (c) return { text: '短码 ' + c, showCode: false };
      return { text: '#' + id, showCode: false };
    }

    function proxyProp(row) {
      var owner = ownerFromProxy(row);
      var pid = Number(row.id || 0);
      var title = String(row.title || '');
      var code = String(row.proxycode || '');
      var mode = Number(row.mode || 0);
      var statusOn = !!Number(row.status || 0);
      var modeLabel = String(row.modelabel || '');
      var protoLabel = String(row.protolabel || '');
      var ep = endpointOf(row);
      var disp = displayProxyTitle(title, code, pid);
      return {
        owner: owner, pid: pid, code: code, mode: mode, statusOn: statusOn,
        modeLabel: modeLabel, protoLabel: protoLabel, ep: ep,
        label: disp.text, showCode: disp.showCode,
        search: proxySearchKey(row, owner)
      };
    }

    function proxyRowHtml(row) {
      var p = proxyProp(row);
      return '<tr data-proxy-row data-user-id="' + esc(p.owner.userid) + '" data-proxy-id="' + esc(p.pid)
        + '" data-search="' + esc(p.search) + '">'
        + '<td>' + ownerCellHtml(p.owner) + '</td>'
        + '<td><strong>' + esc(p.label) + '</strong>'
        + (p.showCode ? '<div class="vs-admin-ip-preview">短码 ' + esc(p.code) + '</div>' : '')
        + '</td>'
        + '<td><span class="vs-badge ' + modeBadgeClass(p.mode) + '">' + esc(p.modeLabel || '—') + '</span></td>'
        + '<td><span class="vs-badge ' + protoBadgeClass(p.protoLabel) + '">' + esc(p.protoLabel || '—') + '</span></td>'
        + '<td><code class="vs-log-mono vs-admin-ip-endpoint" title="' + esc(p.ep.full) + '">'
        + esc(p.ep.short) + '</code></td>'
        + '<td><span class="vs-badge ' + (p.statusOn ? 'vs-badge--success' : 'vs-badge--default') + '">'
        + (p.statusOn ? '启用' : '禁用') + '</span></td>'
        + '<td class="vs-col-actions vs-content-actions-cell"><div class="action-btns">'
        + proxyActBtns(p.owner, p.pid, p.statusOn)
        + '</div></td></tr>';
    }

    function proxyCardHtml(row) {
      var p = proxyProp(row);
      return '<div class="admin-ip-card admin-ip-card--proxy" data-proxy-row data-user-id="' + esc(p.owner.userid)
        + '" data-proxy-id="' + esc(p.pid) + '" data-search="' + esc(p.search) + '">'
        + '<div class="admin-ip-card__header">' + ownerCellHtml(p.owner)
        + '<span class="vs-badge ' + (p.statusOn ? 'vs-badge--success' : 'vs-badge--default') + '">'
        + (p.statusOn ? '启用' : '禁用') + '</span></div>'
        + '<div class="admin-ip-card__title-row">'
        + '<strong class="admin-ip-card__title">' + esc(p.label) + '</strong>'
        + '<span class="vs-badge ' + modeBadgeClass(p.mode) + '">' + esc(p.modeLabel || '—') + '</span>'
        + '<span class="vs-badge ' + protoBadgeClass(p.protoLabel) + '">' + esc(p.protoLabel || '—') + '</span>'
        + '</div>'
        + '<code class="admin-ip-card__endpoint vs-log-mono" title="' + esc(p.ep.full) + '">'
        + esc(p.ep.short) + '</code>'
        + (p.showCode ? '<p class="admin-ip-card__meta">短码 <code>' + esc(p.code) + '</code></p>' : '')
        + '<div class="admin-ip-card__actions admin-ip-card__actions--end action-btns">'
        + proxyActBtns(p.owner, p.pid, p.statusOn)
        + '</div></div>';
    }

    var pageState = { allow: 1, proxy: 1 };

    function renderAllow(list) {
      var tbody = document.getElementById('adminIpAllowTableBody');
      var cards = document.getElementById('adminIpAllowCards');
      if (!tbody || !cards) return;
      var rows = Array.isArray(list) ? list : [];
      tbody.innerHTML = rows.map(allowRowHtml).join('');
      cards.innerHTML = rows.map(allowCardHtml).join('');
      pageState.allow = 1;
      applyView('allow');
    }

    function renderProxy(list) {
      var tbody = document.getElementById('adminIpProxyTableBody');
      var cards = document.getElementById('adminIpProxyCards');
      if (!tbody || !cards) return;
      var rows = Array.isArray(list) ? list : [];
      tbody.innerHTML = rows.map(proxyRowHtml).join('');
      cards.innerHTML = rows.map(proxyCardHtml).join('');
      pageState.proxy = 1;
      applyView('proxy');
    }

    function defaultPageSize() {
      return window.matchMedia('(max-width: 900px)').matches ? 10 : 20;
    }

    function pageSizeEl(kind) {
      return document.getElementById(kind === 'proxy' ? 'adminIpProxyPageSize' : 'adminIpAllowPageSize');
    }

    function getPageSize(kind) {
      var el = pageSizeEl(kind);
      var n = el ? parseInt(el.value, 10) : 0;
      if (!n || n < 1) n = defaultPageSize();
      if ([10, 20, 30, 50].indexOf(n) === -1) n = defaultPageSize();
      return n;
    }

    function desktopRows(kind) {
      var tbody = document.getElementById(kind === 'proxy' ? 'adminIpProxyTableBody' : 'adminIpAllowTableBody');
      if (!tbody) return [];
      var sel = kind === 'proxy' ? 'tr[data-proxy-row]' : 'tr[data-allow-row]';
      return Array.prototype.slice.call(tbody.querySelectorAll(sel));
    }

    function findMobileCard(kind, desk) {
      var cardsEl = document.getElementById(kind === 'proxy' ? 'adminIpProxyCards' : 'adminIpAllowCards');
      if (!cardsEl || !desk) return null;
      var list = cardsEl.querySelectorAll(kind === 'proxy' ? '[data-proxy-row]' : '[data-allow-row]');
      var i;
      for (i = 0; i < list.length; i++) {
        if (kind === 'proxy') {
          if (String(list[i].getAttribute('data-user-id')) === String(desk.getAttribute('data-user-id'))
              && String(list[i].getAttribute('data-proxy-id')) === String(desk.getAttribute('data-proxy-id'))) {
            return list[i];
          }
        } else if (String(list[i].getAttribute('data-user-id')) === String(desk.getAttribute('data-user-id'))
            && String(list[i].getAttribute('data-ip')) === String(desk.getAttribute('data-ip'))) {
          return list[i];
        }
      }
      return null;
    }

    function syncRowOrder(kind, rows) {
      var tbody = document.getElementById(kind === 'proxy' ? 'adminIpProxyTableBody' : 'adminIpAllowTableBody');
      var cardsEl = document.getElementById(kind === 'proxy' ? 'adminIpProxyCards' : 'adminIpAllowCards');
      if (!tbody) return;
      rows.forEach(function (row) {
        tbody.appendChild(row);
        var card = findMobileCard(kind, row);
        if (card && cardsEl) cardsEl.appendChild(card);
      });
    }

    function matchedDesktopRows(kind) {
      var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
      var all = desktopRows(kind);
      var filtered = all.filter(function (row) {
        if (!q) return true;
        var hay = (row.getAttribute('data-search') || '').toLowerCase();
        return hay.indexOf(q) !== -1;
      });
      syncRowOrder(kind, filtered);
      return filtered;
    }

    function renderPagerNav(kind, totalPages, matchedLen) {
      var nav = document.getElementById(kind === 'proxy' ? 'adminIpProxyPagerNav' : 'adminIpAllowPagerNav');
      if (!nav) return;
      var cur = pageState[kind] || 1;
      var canPrev = cur > 1;
      var canNext = matchedLen > 0 && cur < totalPages;
      nav.innerHTML = '<button type="button" class="vs-api-pager__nav" data-p="-1"'
        + (canPrev ? '' : ' disabled') + '>上一页</button>'
        + '<span class="vs-api-pager__info">' + cur + '</span>'
        + '<button type="button" class="vs-api-pager__nav" data-p="1"'
        + (canNext ? '' : ' disabled') + '>下一页</button>';
    }

    function applyView(kind) {
      if (kind !== 'allow' && kind !== 'proxy') return;
      var all = desktopRows(kind);
      var matched = matchedDesktopRows(kind);
      var pageSize = getPageSize(kind);
      var totalPages = Math.max(1, Math.ceil(matched.length / pageSize) || 1);
      if (pageState[kind] > totalPages) pageState[kind] = totalPages;
      if (pageState[kind] < 1) pageState[kind] = 1;
      var start = (pageState[kind] - 1) * pageSize;
      var end = start + pageSize;

      matched.forEach(function (row, idx) {
        var show = idx >= start && idx < end;
        if (show) {
          row.removeAttribute('hidden');
          row.style.display = '';
        } else {
          row.setAttribute('hidden', '');
          row.style.display = 'none';
        }
        var card = findMobileCard(kind, row);
        if (card) {
          if (show) {
            card.removeAttribute('hidden');
            card.style.display = '';
          } else {
            card.setAttribute('hidden', '');
            card.style.display = 'none';
          }
        }
      });

      all.forEach(function (row) {
        if (matched.indexOf(row) === -1) {
          row.setAttribute('hidden', '');
          row.style.display = 'none';
          var card = findMobileCard(kind, row);
          if (card) {
            card.setAttribute('hidden', '');
            card.style.display = 'none';
          }
        }
      });

      var hasAny = all.length > 0;
      var hasVisible = matched.length > 0;
      var q = searchInput ? String(searchInput.value || '').trim() : '';
      var emptyEl = document.getElementById(kind === 'proxy' ? 'adminIpProxyEmpty' : 'adminIpAllowEmpty');
      var searchEmpty = document.getElementById(kind === 'proxy' ? 'adminIpProxySearchEmpty' : 'adminIpAllowSearchEmpty');
      var tableWrap = document.getElementById(kind === 'proxy' ? 'adminIpProxyTableWrap' : 'adminIpAllowTableWrap');
      var cards = document.getElementById(kind === 'proxy' ? 'adminIpProxyCards' : 'adminIpAllowCards');
      var badge = document.getElementById(kind === 'proxy' ? 'adminIpProxyBadge' : 'adminIpAllowBadge');
      var footer = document.getElementById(kind === 'proxy' ? 'adminIpProxyFooter' : 'adminIpAllowFooter');
      var totalEl = document.getElementById(kind === 'proxy' ? 'adminIpProxyTotal' : 'adminIpAllowTotal');

      if (badge) badge.textContent = String(all.length);
      if (emptyEl) {
        if (!hasAny) emptyEl.removeAttribute('hidden');
        else emptyEl.setAttribute('hidden', '');
      }
      if (searchEmpty) {
        if (hasAny && q !== '' && !hasVisible) searchEmpty.removeAttribute('hidden');
        else searchEmpty.setAttribute('hidden', '');
      }
      var showList = hasAny && hasVisible;
      if (tableWrap) {
        if (showList) tableWrap.removeAttribute('hidden');
        else tableWrap.setAttribute('hidden', '');
      }
      if (cards) {
        if (showList) cards.removeAttribute('hidden');
        else cards.setAttribute('hidden', '');
      }
      if (footer) {
        if (hasAny) footer.removeAttribute('hidden');
        else footer.setAttribute('hidden', '');
      }
      if (totalEl) totalEl.textContent = '共 ' + matched.length + ' 条';
      renderPagerNav(kind, matched.length === 0 ? 1 : totalPages, matched.length);
    }

    function applySearch(resetPage) {
      if (resetPage !== false) {
        pageState.allow = 1;
        pageState.proxy = 1;
      }
      applyView('allow');
      applyView('proxy');
    }

    function setTab(name) {
      currentTab = name === 'proxy' ? 'proxy' : 'allow';
      if (tabs) {
        tabs.querySelectorAll('[data-ip-tab]').forEach(function (btn) {
          var on = btn.getAttribute('data-ip-tab') === currentTab;
          btn.classList.toggle('is-active', on);
          btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
      }
      page.querySelectorAll('[data-ip-panel]').forEach(function (panel) {
        var on = panel.getAttribute('data-ip-panel') === currentTab;
        if (on) panel.removeAttribute('hidden');
        else panel.setAttribute('hidden', '');
      });
      applyView(currentTab);
    }

    function removeAllowNodes(userId, ip) {
      page.querySelectorAll('[data-allow-row]').forEach(function (el) {
        if (String(el.getAttribute('data-user-id')) === String(userId)
            && String(el.getAttribute('data-ip')) === String(ip)) {
          el.parentNode && el.parentNode.removeChild(el);
        }
      });
      applyView('allow');
    }

    function removeProxyNodes(userId, proxyId) {
      page.querySelectorAll('[data-proxy-row]').forEach(function (el) {
        if (String(el.getAttribute('data-user-id')) === String(userId)
            && String(el.getAttribute('data-proxy-id')) === String(proxyId)) {
          el.parentNode && el.parentNode.removeChild(el);
        }
      });
      applyView('proxy');
    }

    function patchProxyStatus(userId, proxyId, newStatus) {
      var on = Number(newStatus) === 1;
      page.querySelectorAll('[data-proxy-row]').forEach(function (el) {
        if (String(el.getAttribute('data-user-id')) !== String(userId)
            || String(el.getAttribute('data-proxy-id')) !== String(proxyId)) {
          return;
        }
        el.querySelectorAll('.admin-ip-card__header > .vs-badge, td > .vs-badge').forEach(function (badge) {
          if (badge.textContent === '启用' || badge.textContent === '禁用'
              || badge.classList.contains('vs-badge--success')
              || (badge.classList.contains('vs-badge--default') && !badge.classList.contains('vs-badge--info')
                  && !badge.classList.contains('vs-badge--warning'))) {
            /* only status badges in status column / header - patch all matching success/default that are 启用/禁用 */
          }
        });
        el.querySelectorAll('.vs-badge').forEach(function (badge) {
          var t = badge.textContent;
          if (t === '启用' || t === '禁用') {
            badge.className = 'vs-badge ' + (on ? 'vs-badge--success' : 'vs-badge--default');
            badge.textContent = on ? '启用' : '禁用';
          }
        });
        el.querySelectorAll('.vs-admin-ip-proxy-toggle').forEach(function (btn) {
          btn.setAttribute('data-status', on ? '0' : '1');
          btn.textContent = on ? '禁用' : '启用';
        });
      });
    }

    function refreshLists() {
      if (refreshBtn && window.VsRefreshBtn && VsRefreshBtn.isBusy(refreshBtn)) return;
      if (refreshBtn && window.VsRefreshBtn) VsRefreshBtn.start(refreshBtn);
      var tasks = [];
      if (allowReady) {
        tasks.push(postForm({ action: 'allow_list' }).then(function (res) {
          if (!res || Number(res.code) !== 1) throw new Error((res && res.msg) || '白名单刷新失败');
          renderAllow(res.list || []);
        }));
      }
      if (proxyReady) {
        tasks.push(postForm({ action: 'proxy_list' }).then(function (res) {
          if (!res || Number(res.code) !== 1) throw new Error((res && res.msg) || '代理刷新失败');
          renderProxy(res.list || []);
        }));
      }
      if (tasks.length === 0) {
        if (refreshBtn && window.VsRefreshBtn) VsRefreshBtn.stop(refreshBtn);
        return;
      }
      Promise.all(tasks).then(function () {
        applySearch();
        toast('已刷新', 'success');
      }).catch(function (err) {
        toast((err && err.message) || '刷新失败', 'error');
      }).then(function () {
        if (refreshBtn && window.VsRefreshBtn) VsRefreshBtn.stop(refreshBtn);
      });
    }

    function syncModeFields() {
      if (!formOverlay) return;
      var isExtract = proxyMode && String(proxyMode.value) === '1';
      formOverlay.querySelectorAll('.vs-admin-ip-field-tunnel').forEach(function (el) {
        el.hidden = !!isExtract;
      });
      formOverlay.querySelectorAll('.vs-admin-ip-field-extract').forEach(function (el) {
        el.hidden = !isExtract;
      });
      var extfmtEl = document.getElementById('adminProxyExtfmt');
      var fmt = extfmtEl ? String(extfmtEl.value) : '0';
      var showJson = isExtract && fmt !== '1';
      formOverlay.querySelectorAll('.vs-admin-ip-field-json').forEach(function (el) {
        el.hidden = !showJson;
      });
    }

    function fillProxyForm(row, userId) {
      if (!proxyForm || !row) return;
      document.getElementById('adminProxyUserId').value = String(userId || 0);
      document.getElementById('adminProxyId').value = String(row.id || 0);
      document.getElementById('adminProxyTitle').value = row.title || '';
      document.getElementById('adminProxyMode').value = String(row.mode != null ? row.mode : 0);
      document.getElementById('adminProxyProto').value = String(row.proto != null ? row.proto : 0);
      document.getElementById('adminProxyStatus').value = String(Number(row.status) === 0 ? 0 : 1);
      document.getElementById('adminProxyHost').value = row.host || '';
      document.getElementById('adminProxyPort').value = row.port ? String(row.port) : '';
      document.getElementById('adminProxyUser').value = row.username || '';
      document.getElementById('adminProxyPass').value = '';
      document.getElementById('adminProxyPass').placeholder = row.haspass
        ? '已保存密码，留空不修改' : '留空表示不修改已保存密码';
      document.getElementById('adminProxyExtract').value = row.extract || '';
      document.getElementById('adminProxyExtfmt').value = String(row.extfmt != null ? row.extfmt : 0);
      document.getElementById('adminProxyJsonHost').value = row.jsonhost || '';
      document.getElementById('adminProxyJsonPort').value = row.jsonport || '';
      document.getElementById('adminProxyTtlmin').value = String(row.ttlmin != null ? row.ttlmin : 10);
      document.getElementById('adminProxySort').value = String(row.sort != null ? row.sort : 0);
      document.getElementById('adminProxyFormTitle').textContent = '编辑出口代理';
      syncModeFields();
      if (window.VSPick && typeof window.VSPick.refresh === 'function') {
        window.VSPick.refresh(proxyForm);
      } else if (window.VS && typeof window.VS.refreshPick === 'function') {
        window.VS.refreshPick(proxyForm);
      }
    }

    function renderTestLog(logs, summaryMsg) {
      if (!testLogEl) return;
      var items = Array.isArray(logs) ? logs : [];
      var plain = [];
      var html = [];
      items.forEach(function (item) {
        var type = 'info';
        var msg = '';
        if (typeof item === 'string') {
          msg = item;
        } else if (item && item.msg) {
          type = String(item.t || 'info');
          if (['info', 'ok', 'err', 'req', 'res', 'warn'].indexOf(type) < 0) type = 'info';
          msg = String(item.msg);
        }
        if (!msg) return;
        plain.push('[' + type + '] ' + msg);
        html.push('<span class="vs-admin-ip-test-line is-' + esc(type) + '">' + esc(msg) + '</span>');
      });
      if (summaryMsg) {
        plain.push('——');
        plain.push(String(summaryMsg));
        html.push('<span class="vs-admin-ip-test-line is-info">——</span>');
        html.push('<span class="vs-admin-ip-test-line is-info">' + esc(String(summaryMsg)) + '</span>');
      }
      if (html.length === 0) {
        html.push('<span class="vs-admin-ip-test-line is-warn">无详细日志</span>');
        plain.push('无详细日志');
      }
      lastTestPlain = plain.join('\n');
      testLogEl.innerHTML = html.join('');
      testLogEl.scrollTop = testLogEl.scrollHeight;
    }

    if (proxyMode) {
      proxyMode.addEventListener('change', syncModeFields);
    }
    var extfmtEl = document.getElementById('adminProxyExtfmt');
    if (extfmtEl) {
      extfmtEl.addEventListener('change', syncModeFields);
    }

    if (proxyForm) {
      proxyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var uid = document.getElementById('adminProxyUserId').value;
        var fields = {
          action: 'proxy_save',
          user_id: uid,
          id: document.getElementById('adminProxyId').value,
          title: document.getElementById('adminProxyTitle').value,
          mode: document.getElementById('adminProxyMode').value,
          proto: document.getElementById('adminProxyProto').value,
          status: document.getElementById('adminProxyStatus').value,
          host: document.getElementById('adminProxyHost').value,
          port: document.getElementById('adminProxyPort').value,
          username: document.getElementById('adminProxyUser').value,
          extract: document.getElementById('adminProxyExtract').value,
          extfmt: document.getElementById('adminProxyExtfmt').value,
          jsonhost: document.getElementById('adminProxyJsonHost').value,
          jsonport: document.getElementById('adminProxyJsonPort').value,
          ttlmin: document.getElementById('adminProxyTtlmin').value,
          sort: document.getElementById('adminProxySort').value
        };
        var pass = document.getElementById('adminProxyPass').value;
        if (pass !== '') fields.password = pass;
        if (proxySaveBtn) proxySaveBtn.disabled = true;
        postForm(fields).then(function (res) {
          if (!res || Number(res.code) !== 1) {
            toast((res && res.msg) || '保存失败', 'error');
            return;
          }
          toast(res.msg || '已保存', 'success');
          closeOverlay(formOverlay);
          return postForm({ action: 'proxy_list' }).then(function (listRes) {
            if (listRes && Number(listRes.code) === 1) {
              renderProxy(listRes.list || []);
              applySearch();
            }
          });
        }).catch(function () {
          toast('网络错误', 'error');
        }).then(function () {
          if (proxySaveBtn) proxySaveBtn.disabled = false;
        });
      });
    }

    if (testCopyBtn) {
      testCopyBtn.addEventListener('click', function () {
        var text = lastTestPlain || (testLogEl ? testLogEl.innerText : '');
        if (!text) {
          toast('暂无可复制内容', 'info');
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            toast('已复制测试日志', 'success');
          }).catch(function () {
            toast('复制失败', 'error');
          });
          return;
        }
        toast('当前环境不支持一键复制', 'error');
      });
    }

    if (tabs) {
      tabs.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ip-tab]');
        if (!btn || btn.disabled) return;
        setTab(btn.getAttribute('data-ip-tab'));
      });
    }
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        applySearch(true);
      });
    }
    ['allow', 'proxy'].forEach(function (kind) {
      var sizeEl = pageSizeEl(kind);
      if (sizeEl) {
        sizeEl.addEventListener('change', function () {
          pageState[kind] = 1;
          applyView(kind);
        });
      }
      var nav = document.getElementById(kind === 'proxy' ? 'adminIpProxyPagerNav' : 'adminIpAllowPagerNav');
      if (nav) {
        nav.addEventListener('click', function (e) {
          var btn = e.target.closest('.vs-api-pager__nav');
          if (!btn || btn.disabled) return;
          var delta = parseInt(btn.getAttribute('data-p'), 10) || 0;
          if (!delta) return;
          pageState[kind] = (pageState[kind] || 1) + delta;
          applyView(kind);
        });
      }
    });
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function (e) {
        e.preventDefault();
        refreshLists();
      });
    }

    page.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('.vs-admin-ip-remove-allow');
      if (removeBtn) {
        var uid = removeBtn.getAttribute('data-user-id');
        var ip = removeBtn.getAttribute('data-ip') || '';
        confirmAct('确认移除该白名单 IP？', '移除白名单').then(function (ok) {
          if (!ok) return;
          removeBtn.disabled = true;
          postForm({ action: 'remove_allow', user_id: uid, ip: ip }).then(function (res) {
            if (!res || Number(res.code) !== 1) {
              toast((res && res.msg) || '移除失败', 'error');
              removeBtn.disabled = false;
              return;
            }
            toast(res.msg || '已移除', 'success');
            removeAllowNodes(uid, ip);
          }).catch(function () {
            toast('网络错误', 'error');
            removeBtn.disabled = false;
          });
        });
        return;
      }

      var toggleBtn = e.target.closest('.vs-admin-ip-proxy-toggle');
      if (toggleBtn) {
        var tUid = toggleBtn.getAttribute('data-user-id');
        var tPid = toggleBtn.getAttribute('data-proxy-id');
        var tStatus = toggleBtn.getAttribute('data-status');
        toggleBtn.disabled = true;
        postForm({
          action: 'proxy_toggle',
          user_id: tUid,
          proxy_id: tPid,
          status: tStatus
        }).then(function (res) {
          if (!res || Number(res.code) !== 1) {
            toast((res && res.msg) || '操作失败', 'error');
            toggleBtn.disabled = false;
            return;
          }
          toast(res.msg || '已更新', 'success');
          var next = (res.status !== undefined) ? res.status : tStatus;
          patchProxyStatus(tUid, tPid, next);
          toggleBtn.disabled = false;
        }).catch(function () {
          toast('网络错误', 'error');
          toggleBtn.disabled = false;
        });
        return;
      }

      var delBtn = e.target.closest('.vs-admin-ip-proxy-delete');
      if (delBtn) {
        var dUid = delBtn.getAttribute('data-user-id');
        var dPid = delBtn.getAttribute('data-proxy-id');
        confirmAct('确认删除该出口代理？此操作不可恢复。', '删除代理').then(function (ok) {
          if (!ok) return;
          delBtn.disabled = true;
          postForm({ action: 'proxy_delete', user_id: dUid, proxy_id: dPid }).then(function (res) {
            if (!res || Number(res.code) !== 1) {
              toast((res && res.msg) || '删除失败', 'error');
              delBtn.disabled = false;
              return;
            }
            toast(res.msg || '已删除', 'success');
            removeProxyNodes(dUid, dPid);
          }).catch(function () {
            toast('网络错误', 'error');
            delBtn.disabled = false;
          });
        });
        return;
      }

      var editBtn = e.target.closest('.vs-admin-ip-proxy-edit');
      if (editBtn) {
        var eUid = editBtn.getAttribute('data-user-id');
        var ePid = editBtn.getAttribute('data-proxy-id');
        editBtn.disabled = true;
        postForm({ action: 'proxy_get', user_id: eUid, proxy_id: ePid }).then(function (res) {
          if (!res || Number(res.code) !== 1 || !res.row) {
            toast((res && res.msg) || '读取失败', 'error');
            return;
          }
          fillProxyForm(res.row, eUid);
          openOverlay(formOverlay);
        }).catch(function () {
          toast('网络错误', 'error');
        }).then(function () {
          editBtn.disabled = false;
        });
        return;
      }

      var testBtn = e.target.closest('.vs-admin-ip-proxy-test');
      if (testBtn) {
        var xUid = testBtn.getAttribute('data-user-id');
        var xPid = testBtn.getAttribute('data-proxy-id');
        testBtn.disabled = true;
        lastTestPlain = '';
        if (testLogEl) testLogEl.innerHTML = '<span class="vs-admin-ip-test-line is-info">测试中…</span>';
        openOverlay(testOverlay);
        postForm({ action: 'proxy_test', user_id: xUid, proxy_id: xPid }).then(function (res) {
          renderTestLog(res && res.logs, res && res.msg);
          if (!res || Number(res.code) !== 1) {
            toast((res && res.msg) || '测试失败', 'error');
          } else {
            toast(res.msg || '测试完成', 'success');
          }
        }).catch(function () {
          renderTestLog([], '网络错误');
          toast('网络错误', 'error');
        }).then(function () {
          testBtn.disabled = false;
        });
      }
    });

    syncModeFields();
    ['allow', 'proxy'].forEach(function (kind) {
      var sizeEl = pageSizeEl(kind);
      if (sizeEl) {
        if (!sizeEl.value) {
          sizeEl.value = String(defaultPageSize());
        } else if (window.matchMedia('(max-width: 900px)').matches && sizeEl.value === '20') {
          sizeEl.value = '10';
        }
      }
    });
    applySearch(false);
  }

  boot();
})();
