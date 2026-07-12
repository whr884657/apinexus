function toggleMobile() {
            document.getElementById('sidebar-overlay').classList.toggle('active');
            document.getElementById('mobile-sidebar').classList.toggle('open');
        }
        
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
        function initParticles() { particles = []; const count = Math.floor((width * height) / 12000); for (let i = 0; i < count; i++) particles.push(new Particle()); }
        function connectParticles() {
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x, dy = particles[i].y - particles[j].y, dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 100) { ctx.beginPath(); ctx.moveTo(particles[i].x, particles[i].y); ctx.lineTo(particles[j].x, particles[j].y); ctx.strokeStyle = `rgba(0, 255, 157, ${0.15 * (1 - dist / 100)})`; ctx.lineWidth = 0.5; ctx.stroke(); }
                }
            }
        }
        function animate() { ctx.clearRect(0, 0, width, height); particles.forEach(p => { p.update(); p.draw(); }); connectParticles(); requestAnimationFrame(animate); }
        
        resize(); initParticles(); animate();
        window.addEventListener('resize', () => { resize(); initParticles(); });
        
        // 手机运动传感器头像摇摆动效（参考老版本友链晃动检测逻辑）
        (function() {
            const avatars = document.querySelectorAll('.contributor-avatar, .avatar-img, .avatar-preview, .link-avatar');
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

            const MOTION_CONFIG = { shakeThreshold: 8, cooldownPeriod: 500, minShakeCount: 1 };
            let lastShakeTime = 0, shakeCount = 0, lastAcceleration = { x: 0, y: 0, z: 0 };
            var motionListenerAttached = false;

            function getAcc(event) {
                var a = event.accelerationIncludingGravity || event.acceleration;
                if (a && (a.x != null || a.y != null || a.z != null))
                    return { x: a.x || 0, y: a.y || 0, z: a.z || 0 };
                return null;
            }

            function calculateAccelerationDelta(acc) {
                if (!acc) return 0;
                const deltaX = Math.abs(acc.x - lastAcceleration.x);
                const deltaY = Math.abs(acc.y - lastAcceleration.y);
                const deltaZ = Math.abs(acc.z - lastAcceleration.z);
                return Math.sqrt(deltaX * deltaX + deltaY * deltaY + deltaZ * deltaZ);
            }

            function handleDeviceMotion(event) {
                const acc = getAcc(event);
                if (!acc) return;
                const delta = calculateAccelerationDelta(acc);
                const now = Date.now();
                if (delta > MOTION_CONFIG.shakeThreshold) {
                    shakeCount++;
                    if (shakeCount >= MOTION_CONFIG.minShakeCount && now - lastShakeTime > MOTION_CONFIG.cooldownPeriod) {
                        triggerSwing();
                        lastShakeTime = now;
                        shakeCount = 0;
                    }
                } else {
                    if (shakeCount > 0 && delta < MOTION_CONFIG.shakeThreshold * 0.5) {
                        shakeCount = Math.max(0, shakeCount - 1);
                    }
                }
                lastAcceleration = { x: acc.x || 0, y: acc.y || 0, z: acc.z || 0 };
            }

            function attachMotionListener() {
                if (motionListenerAttached) return;
                motionListenerAttached = true;
                window.addEventListener('devicemotion', handleDeviceMotion, { passive: true });
            }

            function requestMotionPermission() {
                if (typeof DeviceMotionEvent === 'undefined') return;
                if (typeof DeviceMotionEvent.requestPermission === 'function') {
                    DeviceMotionEvent.requestPermission()
                        .then(function(permissionState) {
                            if (permissionState === 'granted') attachMotionListener();
                        })
                        .catch(function(err) { console.warn('[Avatar Motion] 权限请求失败', err); });
                } else {
                    attachMotionListener();
                }
            }

            var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            if (isMobile) {
                var permissionTrigger = function() {
                    requestMotionPermission();
                    document.removeEventListener('click', permissionTrigger);
                    document.removeEventListener('touchstart', permissionTrigger);
                };
                document.addEventListener('click', permissionTrigger, { passive: true });
                document.addEventListener('touchstart', permissionTrigger, { passive: true });
                if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission !== 'function') {
                    attachMotionListener();
                }
            }
        })();
