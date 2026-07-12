// 粒子背景
        const canvas = document.getElementById('shader-canvas');
        const ctx = canvas.getContext('2d');
        let particles = [], width, height;
        function resize() { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; }
        class Particle {
            constructor() { this.reset(); }
            reset() { this.x = Math.random() * width; this.y = Math.random() * height; this.vx = (Math.random() - 0.5) * 0.5; this.vy = (Math.random() - 0.5) * 0.5; this.radius = Math.random() * 1.5; }
            update() { this.x += this.vx; this.y += this.vy; if (this.x < 0 || this.x > width) this.vx *= -1; if (this.y < 0 || this.y > height) this.vy *= -1; }
            draw() { ctx.beginPath(); ctx.arc(this.x, this.y, Math.max(0.1, this.radius), 0, Math.PI * 2); ctx.fillStyle = 'rgba(0, 255, 157, 0.5)'; ctx.fill(); }
        }
        function initParticles() { particles = []; const count = Math.floor((width * height) / 15000); for (let i = 0; i < count; i++) particles.push(new Particle()); }
        function connectParticles() {
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x, dy = particles[i].y - particles[j].y, dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 100) { ctx.beginPath(); ctx.moveTo(particles[i].x, particles[i].y); ctx.lineTo(particles[j].x, particles[j].y); ctx.strokeStyle = `rgba(0, 255, 157, ${0.15 * (1 - dist / 100)})`; ctx.lineWidth = 0.5; ctx.stroke(); }
                }
            }
        }
        function animate() { ctx.clearRect(0, 0, width, height); particles.forEach(p => { p.update(); p.draw(); }); connectParticles(); requestAnimationFrame(animate); }
        
        // 侧边栏（与全站统一：使用 sidebar-overlay / mobile-sidebar）
        function toggleMobile() {
            const overlay = document.getElementById('sidebar-overlay');
            const sidebar = document.getElementById('mobile-sidebar');
            if (!overlay || !sidebar) return;
            overlay.classList.toggle('active');
            sidebar.classList.toggle('open');
        }
        
        resize(); initParticles(); animate();
        window.addEventListener('resize', () => { resize(); initParticles(); });
        
        // 手机运动传感器头像摇摆动效
        (function() {
            const avatars = document.querySelectorAll('.link-avatar, .avatar-img, .friend-avatar');
            if (avatars.length === 0) return;

            const style = document.createElement('style');
            style.textContent = `
                @keyframes swing {
                    0% { transform: rotate(0deg); }
                    15% { transform: rotate(-20deg); }
                    30% { transform: rotate(15deg); }
                    45% { transform: rotate(-10deg); }
                    60% { transform: rotate(5deg); }
                    75% { transform: rotate(-3deg); }
                    100% { transform: rotate(0deg); }
                }
                .avatar-swing {
                    animation: swing 1.2s ease-in-out;
                    transform-origin: center center;
                }
            `;
            document.head.appendChild(style);

            function triggerSwing() {
                avatars.forEach(avatar => {
                    avatar.classList.remove('avatar-swing');
                    void avatar.offsetWidth;
                    avatar.classList.add('avatar-swing');
                });
            }

            avatars.forEach(el => { el.style.cursor = 'pointer'; el.addEventListener('click', function() { triggerSwing(); }); });
        })();
