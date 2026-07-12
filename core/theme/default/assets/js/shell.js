/**
 * 默认主题 · 全局 shell（移动端菜单 + 粒子背景）
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

    var ctx = canvas.getContext('2d');
    var particles = [];
    var width = 0;
    var height = 0;
    var currentParticleColor = 'rgba(107, 114, 128, 0.45)';
    var currentLineColor = 'rgba(107, 114, 128, 0.12)';

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
        var count = Math.floor((width * height) / 12000);
        for (var i = 0; i < count; i++) {
            particles.push(new Particle());
        }
    }

    function connectParticles() {
        for (var i = 0; i < particles.length; i++) {
            for (var j = i + 1; j < particles.length; j++) {
                var dx = particles[i].x - particles[j].x;
                var dy = particles[i].y - particles[j].y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 100) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    var alpha = parseFloat(currentLineColor.match(/,\s*([0-9.]+)\s*\)/)?.[1] || '0.12') * (1 - dist / 100);
                    ctx.strokeStyle = currentLineColor.replace(/,\s*[0-9.]+\s*\)/, ', ' + alpha + ')');
                    ctx.lineWidth = 0.5;
                    ctx.stroke();
                }
            }
        }
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);
        particles.forEach(function (p) {
            p.update();
            p.draw();
        });
        connectParticles();
        requestAnimationFrame(animate);
    }

    resize();
    window.updateParticleColors();
    initParticles();
    animate();
    window.addEventListener('resize', function () {
        resize();
        initParticles();
    });
})();
