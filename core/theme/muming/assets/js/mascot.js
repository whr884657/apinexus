/* ============================================================
   慕名主题 · 看板娘助手（二次元形象）
   互动：开场问候 / 随机小提示 / 点击反应 / 随机推荐接口
   拖动：仅限人物形象区域触发（避免误触）
   最小化：滑动到屏幕一侧探头（靠左贴左、靠右贴右），可拖动，
           点击弹跳回上一记录位置
   多人物：每次刷新随机出现不同角色（data-mascots）
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
    mini: root.getAttribute('data-mini') || '',
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

  /* ---- 多人物：每次刷新随机选一套 ---- */
  var sets = [];
  try {
    var raw = root.getAttribute('data-mascots');
    if (raw) {
      var parsed = JSON.parse(raw);
      if (Array.isArray(parsed) && parsed.length) sets = parsed;
    }
  } catch (err) { /* ignore */ }
  if (!sets.length) sets = [{ idle: cfg.idle, happy: cfg.happy, mini: cfg.mini }];
  var chosen = sets[Math.floor(Math.random() * sets.length)];
  if (chosen && chosen.idle) cfg.idle = chosen.idle;
  if (chosen && chosen.happy) cfg.happy = chosen.happy;
  if (chosen && chosen.mini) cfg.mini = chosen.mini;

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
        hideBubble();
      }, holdMs);
    }
  }
  function hideBubble() {
    if (bubbleTimer) { clearTimeout(bubbleTimer); bubbleTimer = null; }
    bubble.classList.remove('is-show');
    bubbleKind = ''; /* 气泡关闭即复位类型：避免「推荐」后永久拦截周期提示 */
    reClamp();
  }

  /* ---- 角色表情 ---- */
  var happyTimer = null;
  function setHappy(mode) {
    if (isMini) return; /* 最小化探头态不切换表情/图，避免自动切回站立图 */
    if (happyTimer) { clearTimeout(happyTimer); happyTimer = null; }
    if (mode && cfg.happy) {
      img.src = cfg.happy;
      img.classList.add('is-happy');
      happyTimer = setTimeout(function () {
        img.classList.remove('is-happy');
        if (isMini) { if (cfg.mini) img.src = cfg.mini; }
        else if (cfg.idle) img.src = cfg.idle;
      }, 2600);
    } else {
      img.classList.remove('is-happy');
      if (cfg.idle) img.src = cfg.idle;
    }
  }

  /* ---- 随机推荐接口 ---- */
  function doRecommend() {
    if (isMini) return; /* 最小化探头态不触发推荐/表情切换 */
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
        hideBubble();
      }, 8000);
    });
  }

  /* ---- 位置管理 ----
     restorePos：完整状态下的位置（最小化前的最后位置，用于点击弹出恢复）
     dockPos：最小化贴边探头时的位置
     isMini：是否处于最小化探头状态（刷新后不记忆，默认展开） */
  var restorePos = null;
  var dockPos = null;
  var isMini = false;
  var suppressClick = false;

  var DRAG_THRESHOLD = 6; /* px，超过视为拖动 */

  function clampPos(left, bottom, width, height) {
    var maxLeft = Math.max(0, window.innerWidth - width);
    var maxBottom = Math.max(0, window.innerHeight - height);
    return {
      left: Math.max(0, Math.min(left, maxLeft)),
      bottom: Math.max(0, Math.min(bottom, maxBottom))
    };
  }

  function applyPos(pos) {
    if (!pos || typeof pos.left !== 'number') return;
    /* 容器按自身尺寸收敛，召唤按钮为 56px 锚点直接落在目标位置 */
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
    restorePos = pos;
    try { sessionStorage.setItem('th5-mascot-pos', JSON.stringify(pos)); } catch (err) { /* ignore */ }
  }

  /* 气泡文字会改变容器宽度：显隐后先双帧重收敛，随后做约 1.3 秒越界守护。
     气泡为 absolute 浮动（不占位，不影响拖动/贴边宽度），
     自身超出右缘时向左回缩，保持完全在视口内。 */
  function reClamp() {
    if (isMini) {
      requestAnimationFrame(function () {
        requestAnimationFrame(function () { applyDock(); });
      });
      return;
    }
    if (!restorePos) return;
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        var br = bubble.getBoundingClientRect();
        if (br.width && br.right > window.innerWidth - 4) {
          bubble.style.left = (window.innerWidth - br.right - 8) + 'px';
        } else if (br.width && br.left < 4) {
          bubble.style.left = '0px';
        }
        applyPos(restorePos);
        var guard = 0;
        (function tick() {
          if (guard++ > 80) return;
          var rr = root.getBoundingClientRect();
          if (rr.right > window.innerWidth - 0.5) {
            applyPos(restorePos);
            requestAnimationFrame(tick);
          }
        })();
      });
    });
  }

  /* ---- 最小化：贴边探头（扶边图整张贴边：双手扶屏幕边框、仅头+手可见） ---- */

  function dockPosFor(side, nearBottom) {
    var w = root.getBoundingClientRect().width || 112;
    var bottom = parseFloat(root.style.bottom);
    if (isNaN(bottom)) bottom = 24;
    if (nearBottom) bottom = 6; /* 探头态贴底：双手扒的横杆下缘≈屏幕底框 */
    var left = (side === 'left') ? 0 : (window.innerWidth - w);
    left = Math.max(0, Math.min(left, Math.max(0, window.innerWidth - w)));
    return {
      left: left,
      bottom: Math.max(0, Math.min(bottom, window.innerHeight - 60))
    };
  }

  function applyDock() {
    if (!dockPos) return;
    root.style.left = dockPos.left + 'px';
    root.style.top = 'auto';
    root.style.bottom = dockPos.bottom + 'px';
  }

  function dockToNearestEdge() {
    var rect = root.getBoundingClientRect();
    var side = (rect.left + rect.width / 2) < window.innerWidth / 2 ? 'left' : 'right';
    dockPos = dockPosFor(side, isMini);
    if (isMini) img.classList.toggle('is-mirrored', side === 'right');
    applyDock();
  }

  var miniRestoreSrc = '';
  function minimize() {
    if (isMini) return;
    /* 清除表情恢复定时器：避免最小化后定时器把图切回站立姿态 */
    if (happyTimer) { clearTimeout(happyTimer); happyTimer = null; }
    /* 记录恢复位置 = 最小化前的完整位置 */
    var rect = root.getBoundingClientRect();
    restorePos = clampPos(rect.left, window.innerHeight - rect.bottom, rect.width, rect.height);
    var side = (rect.left + rect.width / 2) < window.innerWidth / 2 ? 'left' : 'right';
    dockPos = dockPosFor(side, true);
    isMini = true;
    root.classList.add('is-mini');
    /* 切换为扶边探头图：人物双手扶着屏幕边框、仅头+手可见；贴右时水平镜像 */
    miniRestoreSrc = img.src;
    if (cfg.mini && img.src !== cfg.mini) img.src = cfg.mini;
    img.classList.toggle('is-mirrored', side === 'right');
    hideBubble();
    applyDock(); /* CSS transition 平滑滑出 */
  }

  function restoreFromMini() {
    if (!isMini) return;
    var target = restorePos || { left: 22, bottom: 24 };
    isMini = false;
    root.classList.remove('is-mini');
    if (miniRestoreSrc) img.src = miniRestoreSrc;
    img.classList.remove('is-mirrored');
    applyPos(target);
    /* 弹出动作：弹性回弹 + 开心表情 + 气泡 */
    root.classList.add('is-pop');
    setTimeout(function () { root.classList.remove('is-pop'); }, 650);
    setHappy(true);
    showBubble(pick(BACK_GREETINGS), '', 5000);
  }

  /* ---- 拖动（仅人物形象区域触发；最小化探头时也可拖动） ---- */
  /* move/up/cancel 绑定在 window 上，pointerId 区分多指针。
     拖动句柄为角色图（.th5-mascot__img），移动对象为整个容器，
     避免在气泡 / 按钮 / 空白处误触拖动。 */
  var dragState = null;

  function makeDraggable(handle, moveEl) {
    handle.addEventListener('pointerdown', function (e) {
      if (e.button !== undefined && e.button !== 0) return; /* 仅左键 */
      if (e.target.closest('button, a')) return;
      dragState = {
        el: moveEl,
        pointerId: e.pointerId,
        startX: e.clientX,
        startY: e.clientY,
        startLeft: moveEl.offsetLeft,
        startTop: moveEl.offsetTop,
        moved: false,
        startedMini: isMini
      };
      root.classList.add('is-dragging');
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
    var maxLeft = Math.max(0, window.innerWidth - rect.width);
    var maxTop = Math.max(0, window.innerHeight - rect.height);
    el.style.left = Math.max(0, Math.min(dragState.startLeft + dx, maxLeft)) + 'px';
    el.style.top = Math.max(0, Math.min(dragState.startTop + dy, maxTop)) + 'px';
    el.style.bottom = 'auto';
  });
  function endDrag(e) {
    if (!dragState || (dragState.pointerId !== undefined && e && e.pointerId !== dragState.pointerId)) return;
    var wasMoved = dragState.moved;
    var movedEl = dragState.el;
    var startedMini = dragState.startedMini;
    dragState = null;
    root.classList.remove('is-dragging');
    if (wasMoved) {
      suppressClick = true;
      setTimeout(function () { suppressClick = false; }, 60);
      if (startedMini) {
        /* 最小化状态下拖动：松手后回到最近一侧贴边探头 */
        dockToNearestEdge();
        return;
      }
      var rect = movedEl.getBoundingClientRect();
      var pos = clampPos(rect.left, window.innerHeight - rect.bottom, rect.width, rect.height);
      movedEl.style.left = pos.left + 'px';
      movedEl.style.top = 'auto';
      movedEl.style.bottom = pos.bottom + 'px';
      applyPos(pos);
    }
  }
  window.addEventListener('pointerup', endDrag);
  window.addEventListener('pointercancel', endDrag);
  makeDraggable(img, root);
  if (recallBtn) makeDraggable(recallBtn, recallBtn);

  /* ---- 位置记忆（会话内保持拖放位置；显示状态默认打开，刷新后不记忆最小化） ---- */
  try {
    var savedPos = JSON.parse(sessionStorage.getItem('th5-mascot-pos') || 'null');
    if (savedPos) applyPos(savedPos);
  } catch (err) { /* ignore */ }

  /* ---- 事件 ---- */
  img.addEventListener('click', function () {
    if (suppressClick) return;
    if (isMini) {
      restoreFromMini();
      return;
    }
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
      minimize();
    });
  }
  if (recallBtn) {
    recallBtn.addEventListener('click', function (e) {
      if (suppressClick) return;
      e.stopPropagation();
      if (isMini) {
        restoreFromMini();
        return;
      }
      showBubble(pick(BACK_GREETINGS), '', 5000);
      setHappy(true);
    });
  }

  /* ---- 开场问候 + 周期小提示 ---- */
  setTimeout(function () {
    if (isMini) return;
    showBubble(pick(GREETINGS), '', 6000, 'greet');
  }, 1800);

  function scheduleTip() {
    var delay = 45000 + Math.random() * 45000; /* 45~90 秒 */
    setTimeout(function () {
      /* 气泡空闲（当前无任何气泡展示）时才弹出周期提示，避免打断用户操作 */
      if (!isMini && !bubble.classList.contains('is-show')) {
        showBubble(pick(TIPS), '', 5500, 'tip');
      }
      scheduleTip();
    }, delay);
  }
  scheduleTip();
})();
