// 解析Markdown
        document.addEventListener('DOMContentLoaded', function() {
            const contentEl = document.getElementById('page-content');
            if (contentEl) {
                const contentType = contentEl.getAttribute('data-type') || 'html';
                // 使用 innerHTML 获取原始内容，然后解码 HTML 实体
                const rawContent = contentEl.innerHTML;
                // 解码 HTML 实体，恢复原始 Markdown 语法
                const content = decodeHtmlEntities(rawContent);
                
                if (contentType === 'markdown' && typeof feerMarkdownParse === 'function') {
                    contentEl.innerHTML = feerMarkdownParse(content, { fromInnerHtml: false });
                } else if (contentType === 'markdown' && typeof marked !== 'undefined') {
                    if (typeof feerMarkdownConfigure === 'function') feerMarkdownConfigure();
                    contentEl.innerHTML = marked.parse(content);
                } else {
                    // HTML内容直接显示
                    contentEl.innerHTML = content;
                }
                // 代码语法高亮
                if (typeof hljs !== 'undefined') {
                    hljs.highlightAll();
                }
            }
        });
        
        /**
         * 解码 HTML 实体，恢复原始字符
         * @param {string} html - 包含 HTML 实体的字符串
         * @returns {string} 解码后的原始字符串
         */
        function decodeHtmlEntities(html) {
            if (!html) return '';
            var textarea = document.createElement('textarea');
            textarea.innerHTML = html;
            return textarea.value;
        }
        
        // 侧边栏切换
        function toggleMobile() {
            document.getElementById('sidebar-overlay').classList.toggle('active');
            document.getElementById('mobile-sidebar').classList.toggle('open');
        }
        
        // Shader Background
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
