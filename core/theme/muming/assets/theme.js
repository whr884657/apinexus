
/* Lucide-lite: inline SVG for UI chrome (no CDN) */
(function (global) {
  var P = {
    'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'arrow-up-right': '<path d="M7 17 17 7"/><path d="M7 7h10v10"/>',
    'sun': '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
    'moon': '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
    'menu': '<path d="M4 5h16M4 12h16M4 19h16"/>',
    'x': '<path d="M18 6 6 18M6 6l12 12"/>',
    'search': '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    'sliders-horizontal': '<path d="M10 5H3M21 5h-7M14 5v14M21 12h-7M10 12H3M10 12v7M21 19h-7M10 19H3"/>',
    'terminal': '<path d="m4 17 6-6-6-6"/><path d="M12 19h8"/>',
    'shield-check': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
    'zap': '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
    'globe': '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    'github': '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.4 5.4 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>',
    'twitter': '<path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/>',
    'message-circle': '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
    'play': '<polygon points="6 3 20 12 6 21 6 3"/>',
    'check': '<path d="M20 6 9 17l-5-5"/>',
    'activity': '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    'code-2': '<path d="m18 16 4-4-4-4M6 8l-4 4 4 4M14.5 4l-5 16"/>',
    'book-open': '<path d="M12 7v14M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
    'coins': '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
    'server': '<rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><path d="M6 6h.01M6 18h.01"/>',
    'gauge': '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
    'key-round': '<path d="M2 18v3c0 .6.4 1 1 1h4v-3h3v-3h2l1.4-1.4a6.5 6.5 0 1 0-4-4Z"/><circle cx="16.5" cy="7.5" r=".5"/>',
    'route': '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
    'cloud-sun': '<path d="M12 2v2M4.93 4.93l1.41 1.41M20 12h2M19.07 4.93l-1.41 1.41M15.947 12.65a4 4 0 0 0-5.925-4.128"/><path d="M13 22H7a5 5 0 1 1 4.9-6H13a3 3 0 0 1 0 6Z"/>',
    'map-pin': '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    'smartphone': '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
    'package': '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12M3.29 7 12 12l8.71-5M7.5 4.2l9 5.2"/>',
    'quote': '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21zM15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3z"/>',
    'languages': '<path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/>',
    'qr-code': '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1M21 12v.01M12 21v-1"/>',
    'dollar-sign': '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'scan-text': '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8h8M7 12h10M7 16h6"/>',
    'link': '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    'credit-card': '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
    'calendar': '<path d="M8 2v4M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    'mail': '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    'send': '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
    'image': '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
    'shield': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
    'bot': '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
    'database': '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>',
    'sparkles': '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/><path d="M5 3v4M3 5h4M19 17v4M17 19h4"/>',
    'layers': '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 12.35-9.17 4.16a2 2 0 0 1-1.66 0L2 12.35"/><path d="m22 17.35-9.17 4.16a2 2 0 0 1-1.66 0L2 17.35"/>',
    'rocket': '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
    'heart': '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
    'user-plus': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
    'plus': '<path d="M5 12h14M12 5v14"/>'
  };
  function svg(name) {
    var inner = P[name] || P['activity'];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
  }
  global.fiveIconsRefresh = function (root) {
    root = root || document;
    var nodes = root.querySelectorAll ? root.querySelectorAll('[data-lucide]') : [];
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var name = el.getAttribute('data-lucide') || 'activity';
      var w = el.style.width || '';
      var h = el.style.height || '';
      el.innerHTML = svg(name);
      var s = el.querySelector('svg');
      if (s) {
        if (w) { s.style.width = w; s.setAttribute('width', parseInt(w, 10) || 24); }
        if (h) { s.style.height = h; s.setAttribute('height', parseInt(h, 10) || 24); }
        if (el.style.color) s.style.color = el.style.color;
      }
    }
  };
})(window);

(function () {
  'use strict';
  var cfg = window.TH5_HOME || {};
  var pageMode = cfg.page || 'home';
  var previewLimit = (pageMode === 'apis') ? 99999 : (parseInt(cfg.previewLimit, 10) || 12);
  var apiSort = (cfg.apiSort === 'time' || cfg.apiSort === 'calls') ? cfg.apiSort : 'default';
  var newDays = parseInt(cfg.newDays, 10);
  if (!newDays || newDays < 1) newDays = 7;
  var vsBase = cfg.vsBase || '';
  var catalogApis = [];
  var catalogLoadFailed = false;
  var activeCat = 'all';
  var searchQ = '';

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function $(id) { return document.getElementById(id); }

  /* ---- theme toggle ---- */
  var rootHtml = document.documentElement;
  function refreshThemeToggles(mode) {
    document.querySelectorAll('.js-th5-theme-toggle [data-lucide], #themeToggle [data-lucide]').forEach(function (knob) {
      knob.setAttribute('data-lucide', mode === 'dark' ? 'moon' : 'sun');
      window.fiveIconsRefresh(knob.parentElement || knob);
    });
  }
  function applyTheme(mode) {
    var isDark = mode === 'dark';
    rootHtml.classList.toggle('dark', isDark);
    rootHtml.setAttribute('data-theme', mode);
    rootHtml.style.colorScheme = mode;
    try {
      localStorage.setItem('th5-theme', mode);
      localStorage.setItem('theme', mode);
    } catch (e) {}
    refreshThemeToggles(mode);
  }
  try {
    var saved = localStorage.getItem('th5-theme');
    if (saved !== 'dark' && saved !== 'light') {
      saved = localStorage.getItem('theme');
    }
    if (saved === 'dark' || saved === 'light') applyTheme(saved);
    else applyTheme('light');
  } catch (e) { applyTheme('light'); }
  document.querySelectorAll('.js-th5-theme-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      applyTheme(rootHtml.classList.contains('dark') ? 'light' : 'dark');
    });
  });

  /* ---- sysbar clock (Cyber Console 状态条) ---- */
  var sysbarClock = $('sysbarClock');
  if (sysbarClock) {
    function pad2(n) { return String(n).padStart(2, '0'); }
    function tickClock() {
      var d = new Date();
      sysbarClock.textContent = pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
    }
    tickClock();
    setInterval(tickClock, 1000);
  }

  /* ---- mobile menu ---- */
  var menuToggle = $('menuToggle');
  var mobileMenu = $('mobileMenu');
  var menuBackdrop = $('menuBackdrop');
  function setMenuOpen(open) {
    document.body.classList.toggle('menu-open', !!open);
    document.body.style.overflow = open ? 'hidden' : '';
    if (mobileMenu) {
      mobileMenu.classList.toggle('open', !!open);
      mobileMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (menuBackdrop) {
      menuBackdrop.classList.toggle('show', !!open);
      menuBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuToggle.setAttribute('aria-label', open ? '关闭菜单' : '打开菜单');
      var ic = menuToggle.querySelector('[data-lucide]');
      if (ic) ic.setAttribute('data-lucide', open ? 'x' : 'menu');
      window.fiveIconsRefresh(menuToggle);
    }
  }
  if (menuToggle) menuToggle.addEventListener('click', function () {
    setMenuOpen(!document.body.classList.contains('menu-open'));
  });
  if (menuBackdrop) menuBackdrop.addEventListener('click', function () { setMenuOpen(false); });
  document.querySelectorAll('#mobileMenu a').forEach(function (a) {
    a.addEventListener('click', function () { setMenuOpen(false); });
  });

  /* ---- nav glass on scroll ---- */
  var nav = $('nav');
  function onScrollNav() {
    if (!nav) return;
    if (window.scrollY > 8) nav.style.borderBottomColor = 'var(--border)';
    else nav.style.borderBottomColor = 'transparent';
  }
  window.addEventListener('scroll', onScrollNav, { passive: true });
  onScrollNav();

  /* ---- reveal ---- */
  function revealAllFallback() {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('visible'); });
  }
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('visible');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    revealAllFallback();
  }

  /* ---- hero terminal (Cyber Terminal) ---- */
  var terminalEl = $('heroTerminal');
  var terminalJson = $('terminalJson');
  var terminalStat = $('terminalStat');
  if (terminalEl) {
    var heroLines = parseInt(cfg.heroTerminalLines, 10) || 6;
    function buildWeatherSample() {
      var lines = ['{\n  "code": 0,\n  "data": {', '    "city": "宁波",', '    "temp": "26°C",', '    "humidity": "62%",', '    "wind": "东南风 3级"'];
      if (heroLines >= 7) lines.push('    "uv": "3 中等",');
      if (heroLines >= 8) lines.push('    "aqi": "42 优"');
      else lines[lines.length - 1] = lines[lines.length - 1].replace(/,$/, '');
      lines.push('  }\n}');
      return lines.join('\n');
    }
    function buildIpSample() {
      var lines = ['{\n  "code": 0,\n  "data": {', '    "ip": "116.40.39.90",', '    "country": "中国",', '    "province": "浙江省",', '    "isp": "电信"'];
      if (heroLines >= 7) lines.push('    "city": "宁波",');
      if (heroLines >= 8) lines.push('    "type": "电信宽带"');
      else lines[lines.length - 1] = lines[lines.length - 1].replace(/,$/, '');
      lines.push('  }\n}');
      return lines.join('\n');
    }
    function buildTransSample() {
      var lines = ['{\n  "code": 0,\n  "data": {', '    "translated": "Hello, World!",', '    "from": "zh",', '    "to": "en"'];
      if (heroLines >= 7) lines.push('    "engine": "v3"');
      else lines[lines.length - 1] = lines[lines.length - 1].replace(/,$/, '');
      lines.push('  }\n}');
      return lines.join('\n');
    }
    var TERMINAL_JSON_SAMPLES = [buildWeatherSample(), buildIpSample(), buildTransSample()];
    var TERMINAL_TITLES = ['api-console — zsh', 'api-console — fetch', 'api-console — curl'];
    var ti = 0, charIdx = 0, jsonIdx = 0;
    var typing = false;
    var jsonEl = terminalJson;
    var titleEl = terminalEl.querySelector('.th5-terminal__title');
    function terminalTick() {
      if (!jsonEl) return;
      var sample = TERMINAL_JSON_SAMPLES[jsonIdx];
      charIdx += 1;
      jsonEl.textContent = sample.slice(0, charIdx);
      if (charIdx >= sample.length) {
        charIdx = 0;
        jsonIdx = (jsonIdx + 1) % TERMINAL_JSON_SAMPLES.length;
        ti += 1;
        if (titleEl) titleEl.textContent = TERMINAL_TITLES[jsonIdx % TERMINAL_TITLES.length];
        if (terminalStat) terminalStat.textContent = '● live · ' + (ti) + ' req';
        setTimeout(terminalTick, 2200);
        return;
      }
      setTimeout(terminalTick, 26 + Math.random() * 30);
    }
    setTimeout(terminalTick, 900);
  }

  /* ---- marquee ---- */
  var marqueeEl = $('callStreamMarquee');
  var marqueeOffset = 0, marqueeHalf = 0, marqueeLast = 0;
  var MARQUEE_SPEED = 42;
  function measureMarquee() {
    if (!marqueeEl) return;
    marqueeHalf = marqueeEl.scrollWidth / 2;
  }
  function marqueeLoop(now) {
    if (!marqueeEl) return;
    if (!marqueeLast) marqueeLast = now;
    var dt = Math.min(0.05, (now - marqueeLast) / 1000);
    marqueeLast = now;
    if (!document.hidden && marqueeHalf > 0) {
      marqueeOffset -= MARQUEE_SPEED * dt;
      if (-marqueeOffset >= marqueeHalf) marqueeOffset += marqueeHalf;
      marqueeEl.style.transform = 'translate3d(' + marqueeOffset + 'px,0,0)';
    }
    requestAnimationFrame(marqueeLoop);
  }
  function rebuildMarquee(list) {
    if (!marqueeEl) return;
    var items = (list || []).slice(0, 12);
    if (!items.length) {
      measureMarquee();
      return;
    }
    function chip(a, tone) {
      var name = escapeHtml(a.name || 'api');
      var calls = escapeHtml(formatCallsLabel(a.calls));
      var iconUrl = (a.icon || '').trim();
      var iconHtml = iconUrl
        ? '<img class="api-icon-img" src="' + escapeHtml(iconUrl) + '" alt="" loading="lazy" referrerpolicy="no-referrer" data-ext-icon="1">'
        : '<i data-lucide="activity"></i>';
      return '<div class="api-chip ' + tone + '">' + iconHtml
        + '<span class="api-chip-name">' + name + '</span>'
        + '<span class="api-chip-sep">·</span>'
        + '<span class="api-chip-calls">' + calls + '</span></div>';
    }
    var tones = ['', 's', 't'];
    var html = '';
    for (var round = 0; round < 2; round++) {
      items.forEach(function (a, i) { html += chip(a, tones[i % 3]); });
    }
    marqueeEl.innerHTML = html;
    window.fiveIconsRefresh(marqueeEl);
    measureMarquee();
  }
  if (marqueeEl) {
    window.addEventListener('resize', measureMarquee);
    measureMarquee();
    requestAnimationFrame(marqueeLoop);
  }

  /* ---- stats ---- */
  function formatCallsLabel(n) {
    n = Math.max(0, Number(n) || 0);
    var pack = formatCompact(n);
    var num = pack.decimals > 0 ? pack.v.toFixed(pack.decimals) : String(Math.round(pack.v));
    if (pack.decimals > 0) {
      num = num.replace(/\.0$/, '');
    }
    return num + pack.suffix + ' 调用';
  }
  function formatCompact(n) {
    n = Math.max(0, Number(n) || 0);
    if (n >= 1e8) return { v: (n / 1e8), suffix: '亿', decimals: 1 };
    if (n >= 1e4) return { v: (n / 1e4), suffix: 'W', decimals: n >= 1e5 ? 0 : 1 };
    if (n >= 1e3) return { v: (n / 1e3), suffix: 'K', decimals: 1 };
    return { v: n, suffix: '', decimals: 0 };
  }
  function animateCount(el, target, decimals) {
    var start = 0;
    var t0 = null;
    var dur = 1200;
    function frame(t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      var cur = start + (target - start) * eased;
      el.textContent = decimals > 0 ? cur.toFixed(decimals) : String(Math.round(cur));
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }
  function startStatCounts() {
    document.querySelectorAll('.stat-num [data-count]').forEach(function (el) {
      var raw = el.getAttribute('data-count');
      var format = el.getAttribute('data-format') || '';
      var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10) || 0;
      var target = parseFloat(raw);
      if (isNaN(target)) target = 0;
      var suffixEl = el.parentElement ? el.parentElement.querySelector('.stat-suffix') : null;
      if (format === 'compact' || format === 'full') {
        if (format === 'compact') {
          var pack = formatCompact(target);
          target = pack.v;
          decimals = pack.decimals;
          if (suffixEl) suffixEl.textContent = pack.suffix;
        } else if (suffixEl) {
          suffixEl.textContent = '';
        }
      }
      animateCount(el, target, decimals);
    });
  }
  if ('IntersectionObserver' in window) {
    var stats = $('stats');
    if (stats) {
      var once = false;
      var sio = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting && !once) {
            once = true;
            startStatCounts();
            sio.disconnect();
          }
        });
      }, { threshold: 0.2 });
      sio.observe(stats);
    } else startStatCounts();
  } else startStatCounts();

  /* ---- API cards from catalog ---- */
  function isApiFlagOn(val) {
    return val === true || val === 1 || val === '1';
  }
  function cardChipsHtml(a) {
    var disabled = isApiFlagOn(a.disabled);
    var maintenance = isApiFlagOn(a.maintenance);
    var chips = [];
    if (disabled) {
      chips.push('<span class="tag disabled">已禁用</span>');
    } else if (maintenance) {
      chips.push('<span class="tag hot">维护中</span>');
    } else {
      var points = parseFloat(a.points != null ? a.points : a.price) || 0;
      var label = String(a.billing_label || '').trim();
      if (!label) {
        var charge = parseInt(a.charge, 10) === 1;
        label = (charge && points > 0) ? (String(points) + '积分/次') : '免费';
      }
      var paid = points > 0 || (label !== '免费' && label.toLowerCase() !== 'free');
      if (paid) {
        chips.push('<span class="tag points">' + escapeHtml(label) + '</span>');
      } else {
        chips.push('<span class="tag free">免费</span>');
      }
      var keyMode = parseInt(a.needkey, 10) || 0;
      if (keyMode === 1) {
        chips.push('<span class="tag key">KEY必填</span>');
      } else if (keyMode === 2) {
        chips.push('<span class="tag key">KEY可选</span>');
      } else if (a.needkey_label === 'KEY') {
        chips.push('<span class="tag key">KEY</span>');
      }
    }
    if (!chips.length) return '';
    return '<span class="api-card-chips" aria-label="接口标签">' + chips.join('') + '</span>';
  }
  function colorClass(i) {
    return ['', 'green', 'yellow'][i % 3];
  }
  function methodBadge(a) {
    var m = String(a.method || '').trim().toUpperCase();
    var tone = 'get';
    if (m === 'POST') tone = 'post';
    else if (m === 'PUT' || m === 'PATCH') tone = 'put';
    else if (m === 'DELETE') tone = 'del';
    else if (m === '') { m = 'API'; tone = 'api'; }
    return '<span class="th5-method th5-method--' + tone + '">' + escapeHtml(m) + '</span>';
  }
  function apiPath(a) {
    var p = String(a.call_path || a.endpoint || '').trim();
    if (p) return p;
    return '/api/' + encodeURIComponent(String(a.id || ''));
  }
  function cardHtml(a, i) {
    var href = vsBase + '/detail/' + encodeURIComponent(a.id);
    var iconUrl = String(a.icon || '').trim();
    var iconInner = iconUrl
      ? '<img class="api-icon-img" src="' + escapeHtml(iconUrl) + '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">'
      : '<span class="api-icon-fallback">' + escapeHtml(String(a.name || 'API').charAt(0)) + '</span>';
    var callsLabel = formatCallsLabel(a.calls);
    var chips = cardChipsHtml(a);
    var m = String(a.method || '').trim().toUpperCase();
    var tone = 'get';
    if (m === 'POST') tone = 'post';
    else if (m === 'PUT' || m === 'PATCH') tone = 'put';
    else if (m === 'DELETE') tone = 'del';
    else if (m === '') m = 'API';
    var disabled = isApiFlagOn(a.disabled);
    var maintenance = isApiFlagOn(a.maintenance);
    var stateCls = disabled ? ' is-disabled' : (maintenance ? ' is-maintenance' : '');
    var pathText = apiPath(a);
    var catLabel = '';
    if (a.category_name) catLabel = escapeHtml(String(a.category_name));
    return '<a class="th5-api-card-q reveal visible' + stateCls + '" href="' + escapeHtml(href) + '" style="transition-delay:' + (i * 0.04) + 's;text-decoration:none;color:inherit;display:flex;">'
      + '<div class="th5-api-card-q__head">'
      + '<span class="th5-api-card-q__tag' + (isNewApi(a) ? ' th5-api-card-q__tag--new' : '') + '">' + (isNewApi(a) ? '<i data-lucide="sparkles" style="width:11px;height:11px;"></i>上新' : (catLabel !== '' ? escapeHtml(catLabel) : '接口')) + '</span>'
      + '<span class="th5-api-card-q__method m-' + tone + '">' + escapeHtml(m) + '</span>'
      + '</div>'
      + '<div class="th5-api-card-q__name">' + iconInner + escapeHtml(a.name || ('接口 #' + a.id)) + '</div>'
      + '<p class="th5-api-card-q__desc">' + escapeHtml(a.desc || a.description || '') + '</p>'
      + '<div class="th5-api-card-q__foot">'
      + '<span class="th5-api-card-q__calls">' + escapeHtml(callsLabel) + '</span>'
      + chips
      + '<i data-lucide="arrow-up-right" class="th5-api-card-q__arrow" style="width:15px;height:15px;color:var(--muted);"></i>'
      + '</div></a>';
  }
  function parseApiTime(raw) {
    var s = String(raw || '').trim();
    if (!s) return null;
    var v = new Date(s.indexOf('-') >= 0 ? s.replace(/-/g, '/') : s);
    return isNaN(v.getTime()) ? null : v;
  }
  function isNewApi(a) {
    var d = parseApiTime(a && a.createtime);
    if (!d) return false;
    var age = Date.now() - d.getTime();
    return age >= 0 && age <= newDays * 86400000;
  }
  function catOf(a) {
    if (a.category_id != null && a.category_id !== '') return String(a.category_id);
    if (a.category != null && a.category !== '') return String(a.category);
    return '';
  }
  function shuffleCopy(arr) {
    var list = (arr || []).slice();
    for (var i = list.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = list[i];
      list[i] = list[j];
      list[j] = t;
    }
    return list;
  }
  function renderApiCards() {
    var grid = $('apiGrid');
    if (!grid) return;
    if (catalogLoadFailed && (!catalogApis || !catalogApis.length)) {
      grid.innerHTML = '<p class="text-muted col-span-full" style="padding:24px;text-align:center;">目录加载失败，请刷新重试</p>';
      return;
    }
    var filtered = catalogApis.filter(function (a) {
      if (activeCat === 'new') return isNewApi(a);
      if (activeCat !== 'all' && catOf(a) !== activeCat) return false;
      if (!searchQ) return true;
      var q = searchQ.toLowerCase();
      var blob = [a.name, a.desc, a.call_path, a.endpoint, a.method].join(' ').toLowerCase();
      return blob.indexOf(q) >= 0;
    });
    // 首页精选：有序取前 N；随机再洗一次后取 N（apis 页 previewLimit 极大，等同全量）
    var source = (apiSort === 'default' && pageMode !== 'apis' && Number(window.VS_APIORDER) !== 1)
      ? shuffleCopy(filtered)
      : filtered;
    var show = source.slice(0, previewLimit);
    if (!show.length) {
      grid.innerHTML = '<p class="text-muted col-span-full" style="padding:24px;text-align:center;">暂无匹配的接口</p>';
      return;
    }
    grid.innerHTML = show.map(cardHtml).join('');
    window.fiveIconsRefresh(grid);
  }

  document.querySelectorAll('.cat-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.cat-tab').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      activeCat = btn.getAttribute('data-cat') || 'all';
      renderApiCards();
    });
  });
  var apiSearch = $('apiSearch');
  if (apiSearch) {
    var t = null;
    apiSearch.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () {
        searchQ = String(apiSearch.value || '').trim();
        renderApiCards();
      }, 80);
    });
  }

  /* ---- load catalog ---- */
  function bootCatalog(waitTry) {
    waitTry = Number(waitTry) || 0;
    var grid = $('apiGrid');
    function apply(list) {
      catalogLoadFailed = false;
      catalogApis = list || [];
      if (apiSort === 'time' || apiSort === 'calls') {
        catalogApis = catalogApis.slice().sort(function (x, y) {
          if (apiSort === 'time') {
            var tx = parseApiTime(x && x.createtime);
            var ty = parseApiTime(y && y.createtime);
            return (ty ? ty.getTime() : 0) - (tx ? tx.getTime() : 0);
          }
          return (Number(y && y.calls) || 0) - (Number(x && x.calls) || 0);
        });
      }
      var totalLabel = $('TH5ApiTotalLabel');
      if (totalLabel) totalLabel.textContent = String(cfg.apiCount || catalogApis.length);
      if (apiSearch && apiSearch.getAttribute('data-ph-tpl')) {
        apiSearch.placeholder = apiSearch.getAttribute('data-ph-tpl').replace('{n}', String(cfg.apiCount || catalogApis.length));
      }
      renderApiCards();
      if (pageMode !== 'apis') {
        rebuildMarquee(catalogApis);
      }
      window.TH5_CATALOG_APIS = catalogApis;
      try {
        document.dispatchEvent(new CustomEvent('TH5:catalog', { detail: { apis: catalogApis } }));
      } catch (e) { /* ignore */ }
    }
    function failCatalog() {
      catalogLoadFailed = true;
      catalogApis = [];
      if (grid) {
        grid.innerHTML = '<p class="text-muted col-span-full" style="padding:24px;text-align:center;">目录加载失败，请刷新重试</p>';
      }
    }
    if (grid && window.VS && typeof VS.setLoading === 'function') {
      VS.setLoading(grid, '正在加载接口');
    }
    if (!window.VS || typeof VS.fetchFrontCatalog !== 'function') {
      if (waitTry < 50) {
        setTimeout(function () { bootCatalog(waitTry + 1); }, 40);
        return;
      }
      failCatalog();
      return;
    }
    VS.fetchFrontCatalog({}).then(function (data) {
      window.VS_APIORDER = Number(data && data.apiorder) === 1 ? 1 : 0;
      var list = (data && (data.apiData || data.list || data.apis || data.items)) || [];
      if (!Array.isArray(list)) list = [];
      apply(list);
    }).catch(function () {
      failCatalog();
    });
  }

  /* ---- FAQ 手风琴 ---- */
  document.querySelectorAll('.th5-faq-item').forEach(function (item) {
    var q = item.querySelector('.th5-faq-item__q');
    if (!q) return;
    q.addEventListener('click', function () {
      var isOpen = item.classList.contains('is-open');
      document.querySelectorAll('.th5-faq-item.is-open').forEach(function (other) {
        if (other !== item) {
          other.classList.remove('is-open');
          var a = other.querySelector('.th5-faq-item__a');
          if (a) a.style.maxHeight = '0px';
        }
      });
      item.classList.toggle('is-open', !isOpen);
      var body = item.querySelector('.th5-faq-item__a');
      if (body) body.style.maxHeight = isOpen ? '0px' : (body.scrollHeight + 40) + 'px';
    });
  });

  window.fiveIconsRefresh(document);
  bootCatalog();
})();


/* ============================================================
   主题6 · 特效增强（借鉴 Heo 式交互语言，独立实现）
   1. 顶部加载进度条  2. Loading 进场页  3. Hero 悬浮粒子
   4. 返回顶部按钮    5. 分类横条联动    6. 控制台光标
   7. 按钮指针跟随光晕
   ============================================================ */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function prefersReduced() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function isFront() {
    return !!document.querySelector('.th5-root');
  }

  ready(function () {
    if (!isFront()) {
      return;
    }

    /* 1. 顶部加载进度条（页面资源加载期间匀速推进，load 后收尾） */
    (function () {
      var bar = document.createElement('div');
      bar.id = 'th5-top-progress';
      bar.setAttribute('aria-hidden', 'true');
      document.body.appendChild(bar);
      var progress = 0;
      var done = false;
      var timer = null;
      function step() {
        if (done) return;
        progress += Math.random() * 9 + 4;
        if (progress > 92) progress = 92;
        bar.style.width = progress + '%';
        timer = setTimeout(step, 200 + Math.random() * 180);
      }
      function finish() {
        if (done) return;
        done = true;
        clearTimeout(timer);
        bar.style.width = '100%';
        setTimeout(function () {
          bar.classList.remove('is-running');
          setTimeout(function () { bar.style.width = '0%'; }, 380);
        }, 280);
      }
      bar.classList.add('is-running');
      step();
      if (document.readyState === 'complete') {
        finish();
      } else {
        window.addEventListener('load', finish);
      }
      setTimeout(finish, 5000);
    })();

    /* 2. Loading 进场页（品牌徽标呼吸 + 进度条滑条，加载完成后淡出移除） */
    (function () {
      var el = document.createElement('div');
      el.id = 'th5-loading';
      var mark = document.createElement('div');
      mark.id = 'th5-loading__mark';
      var logo = document.querySelector('.th5-brand-logo img, .th5-brand-img');
      if (logo && logo.getAttribute('src')) {
        var img = document.createElement('img');
        img.src = logo.getAttribute('src');
        img.alt = '';
        mark.appendChild(img);
      } else {
        var nameEl = document.querySelector('.th5-header__name');
        mark.textContent = ((nameEl && nameEl.textContent ? nameEl.textContent.trim() : '') || 'API').charAt(0).toUpperCase() || 'A';
      }
      var bar = document.createElement('div');
      bar.id = 'th5-loading__bar';
      bar.innerHTML = '<i></i>';
      el.appendChild(mark);
      el.appendChild(bar);
      document.body.insertBefore(el, document.body.firstChild);
      var hidden = false;
      function hide() {
        if (hidden) return;
        hidden = true;
        el.classList.add('is-done');
        setTimeout(function () {
          if (el.parentNode) el.parentNode.removeChild(el);
        }, 600);
      }
      window.addEventListener('load', function () { setTimeout(hide, 420); });
      setTimeout(hide, 3200);
    })();

    /* 3. Hero 悬浮粒子（轻量 canvas 连接网络，无外部依赖） */
    (function () {
      if (prefersReduced()) return;
      var hero = document.querySelector('.th5-hero-q');
      if (!hero || !window.requestAnimationFrame) return;
      var canvas = document.createElement('canvas');
      canvas.className = 'th5-hero-q__canvas';
      canvas.setAttribute('aria-hidden', 'true');
      var inner = hero.querySelector('.th5-hero-q__inner');
      if (inner) hero.insertBefore(canvas, inner);
      else hero.appendChild(canvas);
      var ctx = canvas.getContext('2d');
      if (!ctx) return;
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      var w = 0;
      var h = 0;
      var pts = [];
      var raf = 0;
      var running = true;
      var accent = '#1B6BFF';
      try {
        var c = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
        if (c) accent = c;
      } catch (e) { /* ignore */ }
      function hexRgba(hex, a) {
        var m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
        if (!m) return 'rgba(27,107,255,' + a + ')';
        var n = parseInt(m[1], 16);
        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
      }
      var dotColor = hexRgba(accent, 0.5);
      var lineColor = hexRgba(accent, 0.1);
      function resize() {
        var rect = hero.getBoundingClientRect();
        w = Math.max(1, rect.width);
        h = Math.max(1, rect.height);
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        var count = Math.min(64, Math.max(22, Math.round(w / 20)));
        pts = [];
        for (var i = 0; i < count; i++) {
          pts.push({
            x: Math.random() * w,
            y: Math.random() * h,
            vx: (Math.random() - 0.5) * 0.32,
            vy: (Math.random() - 0.5) * 0.32,
            r: Math.random() * 1.6 + 0.7
          });
        }
      }
      function tick() {
        if (!running) return;
        ctx.clearRect(0, 0, w, h);
        var i, j, p;
        for (i = 0; i < pts.length; i++) {
          p = pts[i];
          p.x += p.vx;
          p.y += p.vy;
          if (p.x < -10) p.x = w + 10; else if (p.x > w + 10) p.x = -10;
          if (p.y < -10) p.y = h + 10; else if (p.y > h + 10) p.y = -10;
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = dotColor;
          ctx.fill();
        }
        for (i = 0; i < pts.length; i++) {
          for (j = i + 1; j < pts.length; j++) {
            var dx = pts[i].x - pts[j].x;
            var dy = pts[i].y - pts[j].y;
            var d2 = dx * dx + dy * dy;
            if (d2 < 120 * 120) {
              ctx.beginPath();
              ctx.moveTo(pts[i].x, pts[i].y);
              ctx.lineTo(pts[j].x, pts[j].y);
              ctx.strokeStyle = lineColor;
              ctx.lineWidth = 1;
              ctx.stroke();
            }
          }
        }
        raf = requestAnimationFrame(tick);
      }
      var rsT = null;
      window.addEventListener('resize', function () {
        clearTimeout(rsT);
        rsT = setTimeout(resize, 180);
      }, { passive: true });
      resize();
      tick();
    })();

    /* 4. 返回顶部按钮（滚动超过一屏出现） */
    (function () {
      var btn = document.createElement('button');
      btn.id = 'th5-to-top';
      btn.type = 'button';
      btn.setAttribute('aria-label', '回到顶部');
      btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>';
      document.body.appendChild(btn);
      function onScroll() {
        btn.classList.toggle('show', (window.scrollY || window.pageYOffset) > 480);
      }
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
      btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: prefersReduced() ? 'auto' : 'smooth' });
      });
    })();

    /* 5. 分类横条：箭头滚动 + 与侧栏分类联动 */
    (function () {
      var bar = document.querySelector('[data-th5-catbar]');
      if (!bar) return;
      var scrollEl = bar.querySelector('[data-th5-catbar-scroll]');
      var prev = bar.querySelector('[data-th5-catbar-prev]');
      var next = bar.querySelector('[data-th5-catbar-next]');
      if (!scrollEl) return;
      function updateArrows() {
        if (prev) prev.classList.toggle('is-hidden', scrollEl.scrollLeft <= 2);
        if (next) next.classList.toggle('is-hidden', scrollEl.scrollLeft + scrollEl.clientWidth >= scrollEl.scrollWidth - 2);
      }
      if (prev) {
        prev.addEventListener('click', function () {
          scrollEl.scrollBy({ left: -260, behavior: prefersReduced() ? 'auto' : 'smooth' });
        });
      }
      if (next) {
        next.addEventListener('click', function () {
          scrollEl.scrollBy({ left: 260, behavior: prefersReduced() ? 'auto' : 'smooth' });
        });
      }
      scrollEl.addEventListener('scroll', updateArrows, { passive: true });
      window.addEventListener('resize', updateArrows, { passive: true });
      updateArrows();

      function findTab(cat) {
        var tabs = document.querySelectorAll('.cat-tab');
        for (var i = 0; i < tabs.length; i++) {
          if (String(tabs[i].getAttribute('data-cat') || '') === String(cat)) return tabs[i];
        }
        return null;
      }
      function syncChips(cat) {
        var chips = bar.querySelectorAll('.th5-catbar__chip');
        var activeChip = null;
        for (var i = 0; i < chips.length; i++) {
          var on = String(chips[i].getAttribute('data-cat') || '') === String(cat);
          chips[i].classList.toggle('is-active', on);
          if (on && !activeChip) activeChip = chips[i];
        }
        if (activeChip && activeChip.scrollIntoView) {
          try {
            activeChip.scrollIntoView({ behavior: prefersReduced() ? 'auto' : 'smooth', inline: 'center', block: 'nearest' });
          } catch (e) {
            activeChip.scrollIntoView(true);
          }
        }
      }
      var chips = bar.querySelectorAll('.th5-catbar__chip');
      for (var c = 0; c < chips.length; c++) {
        (function (chip) {
          chip.addEventListener('click', function () {
            var cat = chip.getAttribute('data-cat') || 'all';
            syncChips(cat);
            var tab = findTab(cat);
            if (tab) tab.click();
          });
        })(chips[c]);
      }
      var tabs = document.querySelectorAll('.cat-tab');
      for (var t = 0; t < tabs.length; t++) {
        (function (tab) {
          tab.addEventListener('click', function () {
            syncChips(tab.getAttribute('data-cat') || 'all');
          });
        })(tabs[t]);
      }
    })();

    /* 6. Hero 控制台光标（闪烁方块） */
    (function () {
      var code = document.querySelector('.th5-hero-console__code');
      if (code) {
        var cur = document.createElement('span');
        cur.className = 'th5-hero-console__cursor';
        cur.setAttribute('aria-hidden', 'true');
        code.appendChild(cur);
      }
    })();

    /* 7. 按钮指针跟随光晕（补全 CSS 变量 --mx/--my） */
    (function () {
      var targets = document.querySelectorAll('.btn-primary, .btn-ghost');
      for (var i = 0; i < targets.length; i++) {
        (function (el) {
          el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect();
            if (!r.width || !r.height) return;
            el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
            el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
          });
        })(targets[i]);
      }
    })();
  });
})();
