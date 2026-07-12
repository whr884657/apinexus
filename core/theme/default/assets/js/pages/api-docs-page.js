// 侧边栏（与全站统一：使用 sidebar-overlay / mobile-sidebar）
        function toggleMobile() {
            const overlay = document.getElementById('sidebar-overlay');
            const sidebar = document.getElementById('mobile-sidebar');
            if (!overlay || !sidebar) return;
            overlay.classList.toggle('active');
            sidebar.classList.toggle('open');
        }
        
        // 移动端下拉菜单
        let dropdownOpen = false;
        function toggleDropdown() {
            dropdownOpen = !dropdownOpen;
            const dropdown = document.getElementById('apiDropdown');
            const docContainer = document.querySelector('.doc-container');
            dropdown.classList.toggle('open', dropdownOpen);
            if (dropdownOpen) {
                document.getElementById('dropdownSearch').focus();
                // 展开时隐藏文档容器
                if (docContainer) docContainer.style.display = 'none';
            } else {
                // 关闭时显示文档容器
                if (docContainer) docContainer.style.display = '';
            }
        }
        
        function closeDropdown() {
            dropdownOpen = false;
            const dropdown = document.getElementById('apiDropdown');
            const docContainer = document.querySelector('.doc-container');
            dropdown.classList.remove('open');
            // 关闭时显示文档容器
            if (docContainer) docContainer.style.display = '';
        }
        
        // 点击外部关闭下拉菜单
        document.addEventListener('click', function(e) {
            const selector = document.querySelector('.mobile-api-selector');
            if (selector && !selector.contains(e.target)) {
                closeDropdown();
            }
        });
        
        // 选择API（桌面端） - 使用无问号路径形式 api-docs.php/ID
        function selectApi(id) {
            window.location.href = '/api-docs.php/' + id;
        }
        
        // 选择API（移动端）
        function selectApiMobile(id, name) {
            document.getElementById('selectedApiName').textContent = name;
            closeDropdown();
            window.location.href = '/api-docs.php/' + id;
        }
        
        // 搜索过滤（桌面端）
        const searchInput = document.getElementById('searchInput');
        const apiItems = document.querySelectorAll('.api-list .api-item');
        searchInput.addEventListener('input', function() {
            const keyword = this.value.toLowerCase();
            apiItems.forEach(item => {
                const name = item.getAttribute('data-name');
                item.style.display = name.includes(keyword) ? '' : 'none';
            });
        });
        
        // 搜索过滤（移动端下拉）
        function filterDropdownApis() {
            const keyword = document.getElementById('dropdownSearch').value.toLowerCase();
            const items = document.querySelectorAll('.api-dropdown-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name');
                item.style.display = name.includes(keyword) ? '' : 'none';
            });
        }
        
        // 代码高亮和复制按钮
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof hljs !== 'undefined') {
                document.querySelectorAll('.markdown-body pre code').forEach(block => {
                    hljs.highlightElement(block);
                });
            }
            
            // 为所有代码块添加复制按钮
            document.querySelectorAll('.markdown-body pre').forEach(pre => {
                // 创建复制按钮
                const copyBtn = document.createElement('button');
                copyBtn.className = 'copy-code-btn';
                copyBtn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span>复制</span>
                `;
                
                // 复制功能
                copyBtn.addEventListener('click', async function() {
                    const code = pre.querySelector('code');
                    const text = code ? code.textContent : pre.textContent;
                    
                    try {
                        await navigator.clipboard.writeText(text);
                        copyBtn.classList.add('copied');
                        copyBtn.innerHTML = `
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>已复制</span>
                        `;
                        
                        setTimeout(() => {
                            copyBtn.classList.remove('copied');
                            copyBtn.innerHTML = `
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                <span>复制</span>
                            `;
                        }, 2000);
                    } catch (err) {
                        // 降级方案
                        const textarea = document.createElement('textarea');
                        textarea.value = text;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        try {
                            document.execCommand('copy');
                            copyBtn.classList.add('copied');
                            copyBtn.innerHTML = `
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>已复制</span>
                            `;
                            setTimeout(() => {
                                copyBtn.classList.remove('copied');
                                copyBtn.innerHTML = `
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    <span>复制</span>
                                `;
                            }, 2000);
                        } catch (e) {
                            console.error('复制失败:', e);
                        }
                        document.body.removeChild(textarea);
                    }
                });
                
                pre.appendChild(copyBtn);
            });
        });
        
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
        
        resize(); initParticles(); animate();
        window.addEventListener('resize', () => { resize(); initParticles(); });
