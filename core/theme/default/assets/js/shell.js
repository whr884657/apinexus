/**
 * 默认主题 · 全局 shell（移动端菜单 + 粒子背景）
 * 粒子延后到 idle / load 后再启动，减轻首屏主线程压力（E183）
 */
(function () {
    'use strict';

    window.toggleMobile = function () {
        var overlay = document.getElementById('sidebar-overlay');
        var sidebar = document.getElementById('mobile-sidebar');
        if (overlay) {
            overlay.classList.toggle('active');
        }
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    };

    var canvas = document.getElementById('shader-canvas');
    if (!canvas || !canvas.getContext) {
        return;
    }

    var reducedMotion = false;
    try {
        reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
        reducedMotion = false;
    }
    if (reducedMotion) {
        return;
    }

    var ctx = canvas.getContext('2d');
    var particles = [];
    var width = 0;
    var height = 0;
    var drawLines = true;
    var currentParticleColor = 'rgba(107, 114, 128, 0.45)';
    var currentLineColor = 'rgba(107, 114, 128, 0.12)';
    var started = false;

    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }

    window.updateParticleColors = function () {
        var style = getComputedStyle(document.documentElement);
        var rgb = style.getPropertyValue('--particle-color').trim();
        var alpha = style.getPropertyValue('--line-color-alpha').trim() || '0.08';
        if (rgb) {
            currentParticleColor = 'rgba(' + rgb + ', 0.45)';
            currentLineColor = 'rgba(' + rgb + ', ' + alpha + ')';
        }
    };

    function Particle() {
        this.reset();
    }

    Particle.prototype.reset = function () {
        this.x = Math.random() * width;
        this.y = Math.random() * height;
        this.vx = (Math.random() - 0.5) * 0.5;
        this.vy = (Math.random() - 0.5) * 0.5;
        this.radius = Math.random() * 1.5;
    };

    Particle.prototype.update = function () {
        this.x += this.vx;
        this.y += this.vy;
        if (this.x < 0 || this.x > width) {
            this.vx *= -1;
        }
        if (this.y < 0 || this.y > height) {
            this.vy *= -1;
        }
    };

    Particle.prototype.draw = function () {
        ctx.beginPath();
        ctx.arc(this.x, this.y, Math.max(0.1, this.radius), 0, Math.PI * 2);
        ctx.fillStyle = currentParticleColor;
        ctx.fill();
    };

    function initParticles() {
        particles = [];
        // 密度下调；小屏 / 省电模式进一步减量，避免 O(n²) 连线拖垮帧率
        var density = 22000;
        if (width < 768) {
            density = 32000;
        }
        var count = Math.min(90, Math.floor((width * height) / density));
        drawLines = count <= 55 && width >= 768;
        for (var i = 0; i < count; i++) {
            particles.push(new Particle());
        }
    }

    function connectParticles() {
        if (!drawLines) {
            return;
        }
        for (var i = 0; i < particles.length; i++) {
            for (var j = i + 1; j < particles.length; j++) {
                var dx = particles[i].x - particles[j].x;
                var dy = particles[i].y - particles[j].y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 100) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    var m = currentLineColor.match(/,\s*([0-9.]+)\s*\)/);
                    var baseAlpha = m && m[1] ? parseFloat(m[1]) : 0.12;
                    var alpha = baseAlpha * (1 - dist / 100);
                    ctx.strokeStyle = currentLineColor.replace(/,\s*[0-9.]+\s*\)/, ', ' + alpha + ')');
                    ctx.lineWidth = 0.5;
                    ctx.stroke();
                }
            }
        }
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);
        for (var i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }
        connectParticles();
        requestAnimationFrame(animate);
    }

    function startParticles() {
        if (started) {
            return;
        }
        started = true;
        resize();
        window.updateParticleColors();
        initParticles();
        animate();
        window.addEventListener('resize', function () {
            resize();
            initParticles();
        });
    }

    function scheduleStart() {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(function () {
                startParticles();
            }, { timeout: 1800 });
            return;
        }
        if (document.readyState === 'complete') {
            setTimeout(startParticles, 120);
        } else {
            window.addEventListener('load', function () {
                setTimeout(startParticles, 120);
            });
        }
    }

    scheduleStart();
})();
