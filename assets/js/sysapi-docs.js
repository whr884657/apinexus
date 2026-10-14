/**
 * 文件：assets/js/sysapi-docs.js
 * 作用：系统 API 文档浏览器（目录树 / 搜索 / 选中 / 选项卡 / 鉴权示例 / 参数视图；无编辑）
 */
(function () {
    'use strict';

    function boot() {
        if (!window.VS) {
            setTimeout(boot, 30);
            return;
        }
        init();
    }

    function init() {
        var page = document.getElementById('sysApiPage');
        if (!page) {
            return;
        }

        var tree = document.getElementById('docsTree');
        var treeToggle = document.getElementById('docsTreeToggle');
        var searchInput = document.getElementById('sysApiSearchInput');
        var searchEmpty = document.getElementById('sysApiSearchEmpty');
        var content = document.getElementById('docsContent');
        var nameSuffix = document.getElementById('docsTreeNameSuffix');
        var selectedNameEl = document.getElementById('docsTreeSelectedName');

        function setSelectedTitle(name) {
            var n = String(name || '').trim();
            if (selectedNameEl) {
                selectedNameEl.textContent = n;
            }
            if (nameSuffix) {
                nameSuffix.hidden = !n;
            }
        }

        function highlightPanel(panel) {
            if (!panel || !window.VsSyntax || typeof window.VsSyntax.highlightAll !== 'function') {
                return;
            }
            window.VsSyntax.highlightAll(panel);
        }

        function selectApi(id, nameHint) {
            var sid = String(id || '');
            if (!sid) {
                return;
            }
            var activeName = String(nameHint || '');
            page.querySelectorAll('.docs-tree__item').forEach(function (el) {
                var on = el.getAttribute('data-docs-item') === sid;
                el.classList.toggle('is-active', on);
                if (on) {
                    activeName = el.getAttribute('data-docs-name') || activeName;
                    var group = el.closest('[data-docs-group]');
                    if (group) {
                        group.classList.add('is-open');
                    }
                }
            });
            setSelectedTitle(activeName);
            page.querySelectorAll('.doc-panel[data-docs-panel]').forEach(function (panel) {
                var show = panel.getAttribute('data-docs-panel') === sid;
                panel.hidden = !show;
                if (show) {
                    var tabs = panel.querySelectorAll('.doc-tabs__btn');
                    var panes = panel.querySelectorAll('.doc-tab-pane');
                    tabs.forEach(function (btn, idx) {
                        btn.classList.toggle('is-active', idx === 0);
                    });
                    panes.forEach(function (pane, idx) {
                        var active = idx === 0;
                        pane.classList.toggle('is-active', active);
                        pane.hidden = !active;
                    });
                    highlightPanel(panel);
                }
            });
            if (tree && window.matchMedia('(max-width: 768px)').matches) {
                tree.classList.remove('is-open');
                if (treeToggle) {
                    treeToggle.setAttribute('aria-expanded', 'false');
                }
            }
        }

        function applySearch() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var anyVisible = false;
            var firstVisibleId = '';
            var firstVisibleName = '';

            page.querySelectorAll('[data-docs-group]').forEach(function (group) {
                var items = group.querySelectorAll('.docs-tree__item');
                var groupVisible = false;
                items.forEach(function (item) {
                    var hay = (item.getAttribute('data-search') || '').toLowerCase();
                    var show = !q || hay.indexOf(q) !== -1;
                    item.hidden = !show;
                    if (show) {
                        groupVisible = true;
                        anyVisible = true;
                        if (!firstVisibleId) {
                            firstVisibleId = item.getAttribute('data-docs-item') || '';
                            firstVisibleName = item.getAttribute('data-docs-name') || '';
                        }
                    }
                });
                group.hidden = !groupVisible;
                if (groupVisible && q) {
                    group.classList.add('is-open');
                }
            });

            if (searchEmpty) {
                searchEmpty.hidden = anyVisible || !q;
            }
            if (content) {
                content.querySelectorAll('.doc-panel[data-docs-panel]').forEach(function (panel) {
                    if (!anyVisible && q) {
                        panel.hidden = true;
                    }
                });
            }

            if (q && firstVisibleId) {
                var active = page.querySelector('.docs-tree__item.is-active:not([hidden])');
                if (!active) {
                    selectApi(firstVisibleId, firstVisibleName);
                }
            } else if (!q) {
                var cur = page.querySelector('.docs-tree__item.is-active');
                if (cur && cur.hidden) {
                    selectApi(page.getAttribute('data-first-id') || firstVisibleId, page.getAttribute('data-first-name') || '');
                }
            }
        }

        function fallbackCopy(text) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                // ignore
            }
            document.body.removeChild(ta);
        }

        if (treeToggle && tree) {
            treeToggle.addEventListener('click', function () {
                if (!window.matchMedia('(max-width: 768px)').matches) {
                    return;
                }
                var open = tree.classList.toggle('is-open');
                treeToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        page.addEventListener('click', function (e) {
            var groupBtn = e.target.closest('.docs-tree__group-btn');
            if (groupBtn) {
                var group = groupBtn.closest('[data-docs-group]');
                if (group) {
                    group.classList.toggle('is-open');
                }
                return;
            }

            var item = e.target.closest('.docs-tree__item[data-docs-item]');
            if (item) {
                selectApi(item.getAttribute('data-docs-item'), item.getAttribute('data-docs-name'));
                return;
            }

            var qsAuth = e.target.closest('.docs-qs__auth-tab[data-qs-auth]');
            if (qsAuth) {
                var qsRoot = qsAuth.closest('[data-docs-qs]');
                if (!qsRoot) {
                    return;
                }
                var auth = qsAuth.getAttribute('data-qs-auth');
                qsRoot.querySelectorAll('.docs-qs__auth-tab').forEach(function (btn) {
                    var on = btn === qsAuth;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                qsRoot.querySelectorAll('[data-qs-auth-pane]').forEach(function (pane) {
                    var show = pane.getAttribute('data-qs-auth-pane') === auth;
                    pane.classList.toggle('is-active', show);
                    pane.hidden = !show;
                });
                return;
            }

            var modeBtn = e.target.closest('[data-params-mode]');
            if (modeBtn) {
                var modesRoot = modeBtn.closest('[data-params-modes]');
                if (!modesRoot) {
                    return;
                }
                var mode = modeBtn.getAttribute('data-params-mode') || 'table';
                modesRoot.querySelectorAll('.doc-params-modes__btn').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === modeBtn);
                });
                modesRoot.querySelectorAll('[data-params-pane]').forEach(function (pane) {
                    var show = pane.getAttribute('data-params-pane') === mode;
                    pane.classList.toggle('is-active', show);
                    pane.hidden = !show;
                });
                return;
            }

            var tabBtn = e.target.closest('.doc-tabs__btn[data-docs-tab]');
            if (tabBtn) {
                var panel = tabBtn.closest('.doc-panel');
                if (!panel) {
                    return;
                }
                var tab = tabBtn.getAttribute('data-docs-tab');
                panel.querySelectorAll('.doc-tabs__btn').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === tabBtn);
                });
                panel.querySelectorAll('.doc-tab-pane').forEach(function (pane) {
                    var show = pane.getAttribute('data-docs-pane') === tab;
                    pane.classList.toggle('is-active', show);
                    pane.hidden = !show;
                });
                return;
            }

            function copyText(text, okMsg) {
                text = String(text || '');
                if (!text) {
                    return;
                }
                var done = function () {
                    if (window.VS && typeof window.VS.showMessage === 'function') {
                        window.VS.showMessage(okMsg || '已复制', 'success');
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        fallbackCopy(text);
                        done();
                    });
                } else {
                    fallbackCopy(text);
                    done();
                }
            }

            var copyBtn = e.target.closest('[data-copy-endpoint]');
            if (copyBtn) {
                copyText(copyBtn.getAttribute('data-copy') || '', '已复制接口地址');
                return;
            }

            var codeCopy = e.target.closest('[data-copy-code]');
            if (codeCopy) {
                copyText(codeCopy.getAttribute('data-copy') || '', '已复制');
                return;
            }

            var mdCopy = e.target.closest('[data-copy-markdown]');
            if (mdCopy) {
                var panel = mdCopy.closest('.doc-panel');
                var ta = panel ? panel.querySelector('textarea.sysapi-md-export') : null;
                var md = ta ? String(ta.value || '') : '';
                if (!md) {
                    if (window.VS && typeof window.VS.showMessage === 'function') {
                        window.VS.showMessage('暂无可复制内容', 'error');
                    }
                    return;
                }
                copyText(md, '已复制为 Markdown');
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                applySearch();
            });
        }

        var firstActive = page.querySelector('.docs-tree__item.is-active');
        if (firstActive) {
            setSelectedTitle(firstActive.getAttribute('data-docs-name') || page.getAttribute('data-first-name') || '');
            var firstGroup = firstActive.closest('[data-docs-group]');
            if (firstGroup) {
                firstGroup.classList.add('is-open');
            }
        }

        if (window.VsSyntax && typeof window.VsSyntax.highlightAll === 'function') {
            window.VsSyntax.highlightAll(page);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
