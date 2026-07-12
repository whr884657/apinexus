/**
 * front-theme.js
 * 来源: includes/site-header.php
 * 前台全局主题管理脚本
 */

// === from: includes/site-header.php ===
/**
         * 全局主题管理 - 立即执行，不等待 DOMContentLoaded
         * 功能说明：管理网站主题切换，默认白色主题，点击切换黑色主题
         */
        (function() {
            /**
             * 更新主题图标显示状态
             * 参数说明：theme - 当前主题名称（'light' 或 'dark'）
             */
            function updateThemeIcons(theme) {
                const isLight = theme === 'light';
                document.querySelectorAll('.sun-icon').forEach(el => el.classList.toggle('hidden', !isLight));
                document.querySelectorAll('.moon-icon').forEach(el => el.classList.toggle('hidden', isLight));
            }

            /**
             * 应用主题到页面
             * 参数说明：theme - 要应用的主题名称（'light' 或 'dark'）
             */
            function applyTheme(theme) {
                const next = theme === 'dark' ? 'dark' : 'light';
                // 设置 html 元素的 data-theme 属性
                document.documentElement.setAttribute('data-theme', next);
                // 兼容部分页面使用 body[data-theme="light"] 的旧逻辑：无属性 = 深色
                if (document.body) {
                    if (next === 'light') {
                        document.body.setAttribute('data-theme', 'light');
                    } else {
                        document.body.removeAttribute('data-theme');
                    }
                }
                updateThemeIcons(next);
                // 更新粒子颜色（如果存在）
                if (typeof updateParticleColors === 'function') {
                    updateParticleColors();
                }
            }

            /**
             * 切换主题函数 - 暴露到全局
             * 功能说明：切换当前主题并保存到 localStorage
             */
            window.toggleTheme = function() {
                const current = (document.documentElement.getAttribute('data-theme') || 'light');
                const next = current === 'dark' ? 'light' : 'dark';
                try { localStorage.setItem('theme', next); } catch (e) {}
                applyTheme(next);
            };

            /**
             * 设置主题函数 - 暴露到全局
             * 参数说明：theme - 要设置的主题名称
             */
            window.setTheme = function(theme) {
                try { localStorage.setItem('theme', theme); } catch (e) {}
                applyTheme(theme);
            };

            /**
             * 更新图标函数 - 暴露到全局
             */
            window.updateIcons = function() {
                const theme = document.documentElement.getAttribute('data-theme') || 'light';
                updateThemeIcons(theme);
            };

            // 立即初始化主题（不等待 DOMContentLoaded）
            let saved = null;
            try { saved = localStorage.getItem('theme'); } catch (e) {}
            // 默认白色主题
            if (saved !== 'dark' && saved !== 'light') saved = 'light';
            applyTheme(saved);

            // DOM 加载完成后再次确保主题正确应用
            document.addEventListener('DOMContentLoaded', function() {
                let currentSaved = null;
                try { currentSaved = localStorage.getItem('theme'); } catch (e) {}
                if (currentSaved !== 'dark' && currentSaved !== 'light') currentSaved = 'light';
                applyTheme(currentSaved);
            });
        })();

