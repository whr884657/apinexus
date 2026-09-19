/**
 * 主题6 · 首页广告位轮播
 * 多条广告时启用：左右平移 + 无缝衔接（首尾克隆回卷）
 * - 自动播放（悬停/聚焦/页面隐藏时暂停）
 * - 左右箭头 / 底部指示点 / 触屏滑动
 * - 单条广告不初始化（标记 is-single 静态展示）
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-th5-ad-carousel]');
    if (!root) {
        return;
    }
    var track = root.querySelector('[data-th5-ad-track]');
    if (!track) {
        return;
    }
    var slides = Array.prototype.slice.call(track.children);
    if (slides.length < 2) {
        root.classList.add('is-single');
        return;
    }

    var prevBtn = root.querySelector('[data-th5-ad-prev]');
    var nextBtn = root.querySelector('[data-th5-ad-next]');
    var dotsWrap = document.querySelector('[data-th5-ad-dots]');

    var interval = parseInt(root.getAttribute('data-th5-ad-interval') || '5000', 10);
    if (!interval || interval < 1000) {
        interval = 5000;
    }
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* 无缝循环：尾部追加第一张的克隆，滑到克隆后瞬间回卷到真实第 1 张 */
    var clone = slides[0].cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    clone.classList.add('is-clone');
    track.appendChild(clone);

    var total = slides.length + 1; /* 含克隆 */
    var index = 0;
    var timer = null;
    var busyTimer = null;
    var busy = false;
    var dotBtns = [];

    function setTransition(on) {
        track.style.transition = on ? '' : 'none';
    }

    function apply() {
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
    }

    function updateDots() {
        var active = index % slides.length;
        dotBtns.forEach(function (b, i) {
            b.classList.toggle('is-active', i === active);
            b.setAttribute('aria-selected', i === active ? 'true' : 'false');
        });
    }

    function releaseBusy() {
        busy = false;
        if (busyTimer) {
            clearTimeout(busyTimer);
            busyTimer = null;
        }
    }

    function lockBusy() {
        busy = true;
        if (busyTimer) {
            clearTimeout(busyTimer);
        }
        busyTimer = setTimeout(releaseBusy, 1100); /* transitionend 兜底 */
    }

    function animateTo(i, user) {
        lockBusy();
        index = i;
        apply();
        updateDots();
        if (user) {
            restart();
        }
    }

    function goTo(i) {
        animateTo(i, true);
    }

    function next(user) {
        if (busy && !user) {
            return; /* 自动播放不与进行中的动画叠加；用户手势可打断 */
        }
        if (index === total - 1) {
            animateTo(0, user); /* 兜底 */
            return;
        }
        animateTo(index + 1, user);
    }

    function prev(user) {
        if (index === 0) {
            /* 从第 1 张向左：先无动画跳到尾部克隆，再动画滑到真正的最后一张 */
            setTransition(false);
            index = total - 1;
            apply();
            void track.offsetWidth; /* 强制回流 */
            setTransition(true);
            lockBusy();
            index = total - 2;
            apply();
            updateDots();
            restart();
            return;
        }
        animateTo(index - 1, user);
    }

    /* 滑到克隆后无缝回卷 */
    track.addEventListener('transitionend', function (e) {
        if (e.target !== track || e.propertyName !== 'transform') {
            return;
        }
        if (index === total - 1) {
            setTransition(false);
            index = 0;
            apply();
            void track.offsetWidth;
            setTransition(true);
            updateDots();
        }
        releaseBusy();
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            prev(true);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            next(true);
        });
    }

    /* 指示点 */
    if (dotsWrap) {
        slides.forEach(function (s, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'th5-ad-carousel__dot';
            b.setAttribute('role', 'tab');
            b.setAttribute('aria-label', '第 ' + (i + 1) + ' 张广告');
            b.addEventListener('click', function () {
                goTo(i);
            });
            dotsWrap.appendChild(b);
            dotBtns.push(b);
        });
    }

    /* 自动播放：悬停/聚焦/拖拽暂停，离开恢复；页面隐藏暂停 */
    function start() {
        if (reducedMotion) {
            return;
        }
        stop();
        timer = setInterval(next, interval);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function restart() {
        start();
    }

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    /* 触屏 / 指针滑动 */
    var startX = null;
    var dragging = false;
    var moved = false;

    root.addEventListener('pointerdown', function (e) {
        if (e.button !== 0 && e.pointerType === 'mouse') {
            return;
        }
        startX = e.clientX;
        dragging = true;
        moved = false;
        stop();
    });

    window.addEventListener('pointermove', function (e) {
        if (!dragging || startX === null) {
            return;
        }
        var dx = e.clientX - startX;
        if (Math.abs(dx) > 10) {
            moved = true;
        }
        if (moved) {
            track.style.transition = 'none';
            track.style.transform = 'translateX(calc(-' + (index * 100) + '% + ' + dx + 'px))';
        }
    });

    function endDrag(e) {
        if (!dragging || startX === null) {
            return;
        }
        var dx = e.clientX - startX;
        dragging = false;
        startX = null;
        track.style.transition = '';
        if (moved) {
            if (dx < -48) {
                next();
            } else if (dx > 48) {
                prev();
            } else {
                apply();
            }
            moved = false;
        }
        if (!reducedMotion) {
            start();
        }
    }

    window.addEventListener('pointerup', endDrag);
    root.addEventListener('pointercancel', function () {
        dragging = false;
        startX = null;
        moved = false;
        track.style.transition = '';
        apply();
        if (!reducedMotion) {
            start();
        }
    });

    /* 拖拽后不触发内部链接跳转 */
    root.addEventListener('click', function (e) {
        if (moved) {
            e.preventDefault();
            e.stopPropagation();
            moved = false;
        }
    }, true);

    updateDots();
    start();
})();
