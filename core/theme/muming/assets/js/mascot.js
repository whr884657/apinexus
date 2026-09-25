/* ============================================================
   慕名主题 · 看板娘助手（二次元形象）
   互动：开场问候 / 随机小提示 / 点击反应 / 随机推荐接口 / 最小化+召唤 / 可拖动
   数据源：window.TH5_CATALOG_APIS（theme.js 全局写入），
   兜底：window.VS.fetchFrontCatalog
   ============================================================ */
(function () {
  'use strict';

  var root = document.getElementById('th5Mascot');
  if (!root) return;

  var cfg = {
    vsBase: root.getAttribute('data-vs-base') || '',
    idle: root.getAttribute('data-idle') || '',
    happy: root.getAttribute('data-happy') || '',
    siteName: root.getAttribute('data-site-name') || '本站'
  };
  var img = root.querySelector('.th5-mascot__img');
  var bubble = root.querySelector('.th5-mascot__bubble');
  var textEl = root.querySelector('.th5-mascot__bubble-text');
  var linkEl = root.querySelector('.th5-mascot__bubble-link');
  var recommendBtn = root.querySelector('[data-th5-mascot-recommend]');
  var closeBtn = root.querySelector('[data-th5-mascot-close]');
  var recallBtn = document.querySelector('[data-th5-mascot-recall]');
  if (!img || !bubble || !textEl) return;

  if (cfg.idle) img.src = cfg.idle;

  /* ---- 接口目录（随机推荐数据源） ---- */
  var catalog = [];
  var catalogReady = false;

  function acceptList(list) {
    if (Array.isArray(list) && list.length) {
      catalog = list;
      catalogReady = true;
    }
  }
  if (window.TH5_CATALOG_APIS && window.TH5_CATALOG_APIS.length) acceptList(window.TH5_CATALOG_APIS);
  document.addEventListener('TH5:catalog', function (e) {
    if (!catalogReady && e && e.detail) acceptList(e.detail.apis);
  });

  function ensureCatalog(cb) {
    if (catalogReady) { cb(true); return; }
    if (window.VS && typeof VS.fetchFrontCatalog === 'function') {
      VS.fetchFrontCatalog({}).then(function (data) {
        var list = (data && (data.apiData || data.list || data.apis || data.items)) || [];
        acceptList(list);
        cb(catalogReady);
      }).catch(function () { cb(false); });
    } else {
      cb(false);
    }
  }

  function pickRandomApi() {
    var usable = catalog.filter(function (a) {
      if (!a || !a.id) return false;
      var dis = a.disabled === true || a.disabled === 1 || a.disabled === '1';
      var mnt = a.maintenance === true || a.maintenance === 1 || a.maintenance === '1';
      return !dis && !mnt;
    });
    var pool = usable.length ? usable : catalog;
    if (!pool.length) return null;
    return pool[Math.floor(Math.random() * pool.length)];
  }

  /* ---- 文案池 ---- */
  var GREETINGS = [
    '你好呀～我是看板娘小慕，欢迎来到' + cfg.siteName + '！',
    '嗨～需要找接口吗？点我试试，我可以帮你推荐～',
    '欢迎光临！逛累了就来找我聊两句吧～'
  ];
  var BACK_GREETINGS = [
    '我回来啦～想我了吗？',
    '嘿嘿，我又回来陪你逛啦～',
    '召唤成功！需要推荐个接口吗？'
  ];
  var TIPS = [
    '每个接口都支持在线测试，可以先试用再接入～',
    '在「全部接口」页可以按分类快速浏览～',
    '积分永久有效、用多少扣多少，不用担心过期～',
    '创建 API Key 后就能正式调用接口啦～',
    '调用日志可以在开发者控制台随时查看～',
    '接口文档里都有示例代码，照着调就行～',
    '有问题可以在详情页提交反馈，我们会尽快处理～'
  ];
  var REACTIONS = [
    '嘿嘿，点我一下是想和我聊天吗？',
    '想找接口的话，点下面的「推荐接口」按钮就行～',
    '我每天都在这里等你哦～',
    '好耶！再点一下会有好运～'
  ];
  var REC_INTRO = [
    '给你推荐这个接口，看看合不合心意～',
    '这个接口超实用，推荐给你！',
    '试试这个吧，说不定正好需要它～',
    '为你找到了一个不错的接口～'
  ];

  function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---- 气泡 ---- */
  var bubbleTimer = null;
  var bubbleKind = '';
  function showBubble(text, linkHref, holdMs, kind) {
    if (bubbleTimer) { clearTimeout(bubbleTimer); bubbleTimer = null; }
    bubbleKind = kind || '';
    if (linkEl) {
      if (linkHref) {
        linkEl.href = linkHref;
        linkEl.hidden = false;
      } else {
        linkEl.hidden = true;
      }
    }
    if (textEl) textEl.textContent = text;
    bubble.classList.add('is-show');
    reClamp(); /* 气泡出现后容器变宽，重新收敛到视口内 */
    if (holdMs > 0) {
      bubbleTimer = setTimeout(function () {
        bubble.classList.remove('is-show');
      }, holdMs);
    }
  }
  function hideBubble() {
    if (bubbleTimer) { clearTimeout(bubbleTimer); bubbleTimer = null; }
    bubble.classList.remove('is-show');
    reClamp();
  }

  /* ---- 角色表情 ---- */
  var happyTimer = null;
  function setHappy(mode) {
    if (happyTimer) { clearTimeout(happyTimer); happyTimer = null; }
    if (mode && cfg.happy) {
      img.src = cfg.happy;
      img.classList.add('is-happy');
      happyTimer = setTimeout(function () {
        img.classList.remove('is-happy');
        if (cfg.idle) img.src = cfg.idle;
      }, 2600);
    } else {
      img.classList.remove('is-happy');
      if (cfg.idle) img.src = cfg.idle;
    }
  }

  /* ---- 随机推荐接口 ---- */
  function doRecommend() {
    if (recommendBtn) recommendBtn.setAttribute('aria-busy', 'true');
    ensureCatalog(function (ok) {
      var api = ok ? pickRandomApi() : null;
      if (!api) {
        if (recommendBtn) recommendBtn.removeAttribute('aria-busy');
        showBubble('接口目录还在加载中，稍后再点一次试试～', '', 3200);
        setHappy(true);
        return;
      }
      var name = String(api.name || ('接口 #' + api.id));
      var desc = String(api.desc || api.description || '');
      if (desc.length > 42) desc = desc.slice(0, 42) + '…';
      var href = cfg.vsBase + '/detail/' + encodeURIComponent(String(api.id));
      textEl.textContent = '';
      textEl.innerHTML = pick(REC_INTRO) + '「' + escapeHtml(name) + '」' + (desc ? '——' + desc : '');
      if (linkEl) { linkEl.href = href; linkEl.hidden = false; }
      bubble.classList.add('is-show');
      reClamp();
      bubbleKind = 'recommend';
      setHappy(true);
      if (recommendBtn) recommendBtn.removeAttribute('aria-busy');
      if (bubbleTimer) { clearTimeout(bubbleTimer); bubbleTimer = null; }
      bubbleTimer = setTimeout(function () {
        bubble.classList.remove('is-show');
      }, 8000);
    });
  }

  /* ---- 位置管理（会话内记忆，容器与召唤按钮同步） ---- */
  var lastPos = null; /* 最近一次应用的位置，用于气泡显隐后重新收敛 */

  /* 气泡文字会改变容器宽度：显隐后先双帧重收敛，随后做约 1.3 秒越界守护
     （右缘一旦超出视口即重新收敛，收敛后自动停止，成本可忽略） */
  function reClamp() {
    if (!lastPos) return;
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        applyPos(lastPos);
        var guard = 0;
        (function tick() {
          if (guard++ > 80) return;
          var rr = root.getBoundingClientRect();
          if (rr.right > window.innerWidth - 0.5) {
            applyPos(lastPos);
            requestAnimationFrame(tick);
          }
        })();
      });
    });
  }
  var DRAG_THRESHOLD = 6; /* px，超过视为拖动 */
  var suppressClick = false;

  function clampPos(left, bottom, width, height) {
    var minEdge = 8;
    var maxLeft = Math.max(minEdge, window.innerWidth - width - minEdge);
    var maxBottom = Math.max(minEdge, window.innerHeight - height - minEdge);
    return {
      left: Math.max(minEdge, Math.min(left, maxLeft)),
      bottom: Math.max(minEdge, Math.min(bottom, maxBottom))
    };
  }

  function applyPos(pos) {
    if (!pos || typeof pos.left !== 'number') return;
    /* 容器按自身尺寸收敛（左边缘不越过右侧边界），召唤按钮为 56px 锚点直接落在目标位置，
       避免召唤按钮被容器的宽度钳制而拉回容器左边缘 */
    var rr = root.getBoundingClientRect();
    var rootPos = clampPos(pos.left, pos.bottom, rr.width, rr.height);
    root.style.left = rootPos.left + 'px';
    root.style.top = 'auto';
    root.style.bottom = rootPos.bottom + 'px';
    if (recallBtn) {
      var r = clampPos(pos.left, pos.bottom, 56, 56);
      recallBtn.style.left = r.left + 'px';
      recallBtn.style.top = 'auto';
      recallBtn.style.bottom = r.bottom + 'px';
    }
    lastPos = pos;
    try { sessionStorage.setItem('th5-mascot-pos', JSON.stringify(pos)); } catch (err) { /* ignore */ }
  }

  /* ---- 拖动（按住角色/气泡/召唤按钮移动；拖动与点击区分） ---- */
  /* move/up/cancel 绑定在 window 上：即使指针途经更高层级的元素
     （如返回顶部按钮 z-950），拖动也不会被中断；pointerId 用于区分多指针。 */
  var dragState = null;

  function makeDraggable(el) {
    el.addEventListener('pointerdown', function (e) {
      if (e.button !== undefined && e.button !== 0) return; /* 仅左键 */
      if (el === root && e.target.closest('button, a')) return; /* 按钮/链接不触发拖动 */
      dragState = {
        el: el,
        pointerId: e.pointerId,
        startX: e.clientX,
        startY: e.clientY,
        startLeft: el.offsetLeft,
        startTop: el.offsetTop,
        moved: false
      };
      root.classList.add('is-dragging');
      /* 不调用 setPointerCapture：真实点击时捕获会把 click 派发给容器，导致角色点击无互动；
         move/up 已挂在 window 上，指针移出元素也能持续驱动拖动 */
    });
  }
  window.addEventListener('pointermove', function (e) {
    if (!dragState || (dragState.pointerId !== undefined && e.pointerId !== dragState.pointerId)) return;
    var el = dragState.el;
    var dx = e.clientX - dragState.startX;
    var dy = e.clientY - dragState.startY;
    if (!dragState.moved && Math.abs(dx) < DRAG_THRESHOLD && Math.abs(dy) < DRAG_THRESHOLD) return;
    dragState.moved = true;
    var rect = el.getBoundingClientRect();
    var maxLeft = Math.max(8, window.innerWidth - rect.width - 8);
    var maxTop = Math.max(8, window.innerHeight - rect.height - 8);
    el.style.left = Math.max(8, Math.min(dragState.startLeft + dx, maxLeft)) + 'px';
    el.style.top = Math.max(8, Math.min(dragState.startTop + dy, maxTop)) + 'px';
    el.style.bottom = 'auto';
  });
  function endDrag(e) {
    if (!dragState || (dragState.pointerId !== undefined && e && e.pointerId !== dragState.pointerId)) return;
    var wasMoved = dragState.moved;
    var movedEl = dragState.el;
    dragState = null;
    root.classList.remove('is-dragging');
    if (wasMoved) {
      suppressClick = true;
      setTimeout(function () { suppressClick = false; }, 60);
      var rect = movedEl.getBoundingClientRect();
      /* 锚点位置 = 被拖元素最终位置（按 56px 锚点收敛），随后 applyPos 同步双方 */
      var pos = clampPos(rect.left, window.innerHeight - rect.bottom, 56, 56);
      movedEl.style.left = pos.left + 'px';
      movedEl.style.top = 'auto';
      movedEl.style.bottom = pos.bottom + 'px';
      applyPos(pos);
    }
  }
  window.addEventListener('pointerup', endDrag);
  window.addEventListener('pointercancel', endDrag);
  makeDraggable(root);
  if (recallBtn) makeDraggable(recallBtn);

  /* ---- 位置记忆（会话内保持拖放位置；显示状态默认打开，刷新后不记忆最小化） ---- */
  try {
    var savedPos = JSON.parse(sessionStorage.getItem('th5-mascot-pos') || 'null');
    if (savedPos) applyPos(savedPos);
  } catch (err) { /* ignore */ }

  /* ---- 事件 ---- */
  img.addEventListener('click', function () {
    if (suppressClick) return;
    showBubble(pick(REACTIONS), '', 3200);
    setHappy(true);
  });
  if (recommendBtn) {
    recommendBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      doRecommend();
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      hideBubble();
      root.classList.add('is-hidden');
    });
  }
  if (recallBtn) {
    recallBtn.addEventListener('click', function (e) {
      if (suppressClick) return;
      e.stopPropagation();
      root.classList.remove('is-hidden');
      showBubble(pick(BACK_GREETINGS), '', 5000);
      setHappy(true);
    });
  }

  /* ---- 开场问候 + 周期小提示 ---- */
  setTimeout(function () {
    showBubble(pick(GREETINGS), '', 6000, 'greet');
  }, 1800);

  function scheduleTip() {
    var delay = 45000 + Math.random() * 45000; /* 45~90 秒 */
    setTimeout(function () {
      if (!root.classList.contains('is-hidden') && bubbleKind !== 'recommend') {
        showBubble(pick(TIPS), '', 5500, 'tip');
      }
      scheduleTip();
    }, delay);
  }
  scheduleTip();
})();
