/**
 * 文件：assets/js/api-docs.js
 * 作用：接口文档（目录树折叠 / 搜索 / 选中 / 选项卡 / 复制地址）
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
        var page = document.getElementById('apiDocsPage');
        if (!page) {
            return;
        }

        var tree = document.getElementById('docsTree');
        var treeToggle = document.getElementById('docsTreeToggle');
        var searchInput = document.getElementById('apiDocsSearchInput');
        var searchEmpty = document.getElementById('apiDocsSearchEmpty');
        var content = document.getElementById('docsContent');

        function selectApi(id) {
            var sid = String(id || '');
            if (!sid) {
                return;
            }
            page.querySelectorAll('.docs-tree__item').forEach(function (el) {
                el.classList.toggle('is-active', el.getAttribute('data-docs-item') === sid);
            });
            page.querySelectorAll('.doc-panel[data-docs-panel]').forEach(function (panel) {
                var show = panel.getAttribute('data-docs-panel') === sid;
                panel.hidden = !show;
                if (show) {
                    // reset to first tab
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
                }
            });
            if (tree && window.matchMedia('(max-width: 768px)').matches) {
                tree.classList.remove('is-open');
            }
        }

        function applySearch() {
            var q = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
            var anyVisible = false;
            var firstVisibleId = '';

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
                    selectApi(firstVisibleId);
                }
            } else if (!q) {
                var cur = page.querySelector('.docs-tree__item.is-active');
                if (cur && cur.hidden) {
                    selectApi(page.getAttribute('data-first-id') || firstVisibleId);
                }
            }
        }

        if (treeToggle && tree) {
            treeToggle.addEventListener('click', function () {
                tree.classList.toggle('is-open');
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
                selectApi(item.getAttribute('data-docs-item'));
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

            var copyBtn = e.target.closest('[data-copy-endpoint]');
            if (copyBtn) {
                var text = copyBtn.getAttribute('data-copy') || '';
                if (!text) {
                    return;
                }
                var done = function () {
                    if (window.VS && typeof window.VS.showMessage === 'function') {
                        window.VS.showMessage('已复制接口地址', 'success');
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
        });

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

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                applySearch();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
