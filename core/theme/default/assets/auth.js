/**
 * 默认主题 · 认证页动效（anime.js v3）
 * - 背景粒子
 * - 小人入场 / 呼吸（独立 motion 层，避免与角色 skew 冲突）
 * - 表单卡片与字段错落入场
 */
(function () {
    'use strict';

    if (!document.querySelector('.page')) {
        return;
    }

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var animeApi = typeof window.anime === 'function' ? window.anime : null;

    function ensureFxLayer() {
        if (document.querySelector('.auth-fx')) {
            return;
        }
        var fx = document.createElement('div');
        fx.className = 'auth-fx';
        fx.setAttribute('aria-hidden', 'true');
        fx.innerHTML = '<div class="auth-fx__grid"></div><canvas id="authParticleCanvas"></canvas>';
        document.body.insertBefore(fx, document.body.firstChild);
    }

    function ensureStageBadge() {
        var left = document.querySelector('.left');
        if (!left || left.querySelector('.auth-stage-badge')) {
            return;
        }
        var badge = document.createElement('div');
        badge.className = 'auth-stage-badge';
        badge.innerHTML = '<span class="auth-stage-badge__dot" aria-hidden="true"></span><span>LIVE AUTH · MISC-API</span>';
        left.insertBefore(badge, left.firstChild);
    }

    /**
     * 包一层 motion 容器：anime 只改这一层 transform，
     * 避免覆盖 .characters 的手机端 scale 与 .char 的 skew。
     */
    function ensureMotionWrap() {
        var chars = document.querySelector('.characters');
        var wrap = document.querySelector('.characters-wrap');
        if (!chars || !wrap) {
            return null;
        }
        if (chars.parentElement && chars.parentElement.classList.contains('characters-motion')) {
            return chars.parentElement;
        }
        var motion = document.createElement('div');
        motion.className = 'characters-motion';
        wrap.insertBefore(motion, chars);
        motion.appendChild(chars);
        return motion;
    }

    function revealStatic() {
        var formBox = document.querySelector('.form-box');
        var motion = document.querySelector('.characters-motion') || document.querySelector('.characters');
        var badge = document.querySelector('.auth-stage-badge');
        var animated = document.querySelectorAll(
            '.field, .row, .hover-btn, .oauth-section, .divider, .auth-home-link'
        );
        if (formBox) {
            formBox.style.opacity = '1';
            formBox.style.transform = 'none';
        }
        if (motion) {
            motion.style.opacity = '1';
            motion.style.transform = 'none';
        }
        if (badge) {
            badge.style.opacity = '1';
            badge.style.transform = 'none';
        }
        animated.forEach(function (el) {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
    }

    function initParticles() {
        var canvas = document.getElementById('authParticleCanvas');
        if (!canvas || reduceMotion) {
            return;
        }
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var particles = [];
        var raf = 0;
        var w = 0;
        var h = 0;
        var running = true;

        function resize() {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
        }

        function spawn() {
            particles = [];
            var count = Math.max(24, Math.min(64, Math.floor((w * h) / 30000)));
            for (var i = 0; i < count; i++) {
                particles.push({
                    x: Math.random() * w,
                    y: Math.random() * h,
                    r: Math.random() * 1.6 + 0.4,
                    vx: (Math.random() - 0.5) * 0.32,
                    vy: (Math.random() - 0.5) * 0.32,
                    a: Math.random() * 0.4 + 0.12
                });
            }
        }

        function tick() {
            if (!running) {
                return;
            }
            ctx.clearRect(0, 0, w, h);
            for (var i = 0; i < particles.length; i++) {
                var p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                if (p.x < 0) p.x = w;
                if (p.x > w) p.x = 0;
                if (p.y < 0) p.y = h;
                if (p.y > h) p.y = 0;
                ctx.beginPath();
                ctx.fillStyle = 'rgba(0, 255, 157,' + p.a + ')';
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fill();
            }
            raf = window.requestAnimationFrame(tick);
        }

        resize();
        spawn();
        tick();
        window.addEventListener('resize', function () {
            resize();
            spawn();
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                running = false;
                window.cancelAnimationFrame(raf);
            } else {
                running = true;
                tick();
            }
        });
    }

    function playEntrance(motion) {
        var formBox = document.querySelector('.form-box');
        var fields = document.querySelectorAll(
            '.field, .row, .hover-btn, .oauth-section, .divider, .auth-home-link'
        );

        if (reduceMotion || !animeApi) {
            revealStatic();
            return;
        }

        if (motion) {
            animeApi.timeline({ easing: 'easeOutCubic' })
                .add({
                    targets: motion,
                    translateY: [40, 0],
                    opacity: [0, 1],
                    duration: 880
                })
                .add({
                    targets: motion,
                    translateY: [0, -6],
                    direction: 'alternate',
                    loop: true,
                    duration: 2600,
                    easing: 'easeInOutSine'
                });
        }

        if (formBox) {
            animeApi({
                targets: formBox,
                opacity: [0, 1],
                translateY: [24, 0],
                duration: 700,
                delay: 140,
                easing: 'easeOutCubic'
            });
        }

        if (fields.length) {
            animeApi({
                targets: fields,
                opacity: [0, 1],
                translateY: [12, 0],
                delay: animeApi.stagger(50, { start: 280 }),
                duration: 520,
                easing: 'easeOutQuad'
            });
        }

        var badge = document.querySelector('.auth-stage-badge');
        if (badge) {
            animeApi({
                targets: badge,
                opacity: [0, 1],
                translateX: [-14, 0],
                duration: 560,
                delay: 60,
                easing: 'easeOutCubic'
            });
        }
    }

    function bindFocusPulse() {
        if (!animeApi || reduceMotion) {
            return;
        }
        document.querySelectorAll('.field input').forEach(function (input) {
            input.addEventListener('focus', function () {
                animeApi({
                    targets: input,
                    scale: [1, 1.012, 1],
                    duration: 340,
                    easing: 'easeOutQuad'
                });
            });
        });
    }

    ensureFxLayer();
    ensureStageBadge();
    var motion = ensureMotionWrap();
    initParticles();
    playEntrance(motion);
    bindFocusPulse();
})();
