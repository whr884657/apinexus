/**
 * 文件：assets/js/api-docs.js
 * 作用：接口文档（目录树折叠 / 搜索 / 选中 / 选项卡 / 复制 / 文档编辑弹窗）
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
        var nameSuffix = document.getElementById('docsTreeNameSuffix');
        var selectedNameEl = document.getElementById('docsTreeSelectedName');
        var editOverlay = document.getElementById('apiDocsEditOverlay');
        var editForm = document.getElementById('apiDocsEditForm');
        var editId = document.getElementById('apiDocsEditId');
        var editTitle = document.getElementById('apiDocsEditTitle');
        var editParams = document.getElementById('apiDocsEditParams');
        var editResponse = document.getElementById('apiDocsEditResponse');
        var editAidoc = document.getElementById('apiDocsEditAidoc');
        var editDoc = document.getElementById('apiDocsEditDoc');
        var paramsEditor = document.getElementById('apiDocsParamsEditor');
        var lastFocus = null;
        var saving = false;

        if (window.VsParamsEditor && paramsEditor) {
            window.VsParamsEditor.mount(paramsEditor, { hiddenId: 'apiDocsEditParams' });
        }

        function setSelectedTitle(name) {
            var n = String(name || '').trim();
            if (selectedNameEl) {
                selectedNameEl.textContent = n;
            }
            if (nameSuffix) {
                nameSuffix.hidden = !n;
            }
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
                }
            });
            if (tree && window.matchMedia('(max-width: 768px)').matches) {
                tree.classList.remove('is-open');
                if (treeToggle) {
                    treeToggle.setAttribute('aria-expanded', 'false');
                }
            }
            highlightPanel(page.querySelector('.doc-panel[data-docs-panel="' + sid + '"]'));
        }

        function highlightPanel(panel) {
            if (!panel || !window.VsSyntax || typeof window.VsSyntax.highlightAll !== 'function') {
                return;
            }
            window.VsSyntax.highlightAll(panel);
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

        function openOverlay() {
            if (!editOverlay) {
                return;
            }
            lastFocus = document.activeElement;
            editOverlay.hidden = false;
            editOverlay.setAttribute('aria-hidden', 'false');
            editOverlay.classList.add('is-open');
            document.body.classList.add('is-overlay-open');
            var focusEl = editResponse || editForm;
            if (focusEl && typeof focusEl.focus === 'function') {
                setTimeout(function () {
                    focusEl.focus();
                }, 30);
            }
        }

        function closeOverlay() {
            if (!editOverlay) {
                return;
            }
            editOverlay.hidden = true;
            editOverlay.setAttribute('aria-hidden', 'true');
            editOverlay.classList.remove('is-open');
            document.body.classList.remove('is-overlay-open');
            if (window.VsParamsEditor && typeof window.VsParamsEditor.closeTypePicker === 'function') {
                window.VsParamsEditor.closeTypePicker();
            }
            if (lastFocus && typeof lastFocus.focus === 'function') {
                try {
                    lastFocus.focus();
                } catch (err) {
                    // ignore
                }
            }
        }

        function openEdit(apiId) {
            var id = String(apiId || '');
            if (!id || !window.VS || typeof window.VS.postForm !== 'function') {
                return;
            }
            if (typeof window.VS.setLoading === 'function') {
                window.VS.setLoading(true);
            }
            var fd = new FormData();
            fd.append('action', 'get_docs');
            fd.append('api_id', id);
            window.VS.postForm(fd, window.location.href).then(function (res) {
                if (typeof window.VS.setLoading === 'function') {
                    window.VS.setLoading(false);
                }
                if (!res || res.code !== 1 || !res.data) {
                    if (window.VS.showMessage) {
                        window.VS.showMessage((res && res.msg) || '加载失败', 'error');
                    }
                    return;
                }
                var d = res.data;
                if (editId) {
                    editId.value = String(d.api_id || id);
                }
                if (editTitle) {
                    editTitle.textContent = d.name ? ('编辑文档 — ' + d.name) : '编辑文档';
                }
                if (window.VsParamsEditor && paramsEditor) {
                    window.VsParamsEditor.setValue(paramsEditor, d.params || '');
                } else if (editParams) {
                    editParams.value = d.params || '';
                }
                if (editResponse) {
                    editResponse.value = d.response || '';
                }
                if (editAidoc) {
                    editAidoc.value = d.aidoc || '';
                }
                if (editDoc) {
                    editDoc.value = d.doc || '';
                }
                openOverlay();
            }).catch(function () {
                if (typeof window.VS.setLoading === 'function') {
                    window.VS.setLoading(false);
                }
                if (window.VS.showMessage) {
                    window.VS.showMessage('加载失败', 'error');
                }
            });
        }

        function applySavedSlots(apiId, data) {
            var panel = page.querySelector('.doc-panel[data-docs-panel="' + String(apiId) + '"]');
            if (!panel || !data) {
                return;
            }
            var map = {
                params: data.params_html,
                response: data.response_html,
                request: data.request_html,
                doc: data.doc_html
            };
            Object.keys(map).forEach(function (key) {
                var slot = panel.querySelector('[data-docs-slot="' + key + '"]');
                if (slot && typeof map[key] === 'string') {
                    slot.innerHTML = map[key];
                }
            });
            highlightPanel(panel);
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

            var editBtn = e.target.closest('[data-docs-edit]');
            if (editBtn) {
                openEdit(editBtn.getAttribute('data-docs-edit'));
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

        if (editOverlay) {
            editOverlay.querySelectorAll('[data-overlay-close]').forEach(function (el) {
                el.addEventListener('click', function () {
                    if (!saving) {
                        closeOverlay();
                    }
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && editOverlay.classList.contains('is-open') && !saving) {
                    closeOverlay();
                }
            });
        }

        if (editForm) {
            editForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (saving || !window.VS || typeof window.VS.postForm !== 'function') {
                    return;
                }
                if (window.VsParamsEditor && paramsEditor) {
                    var got = window.VsParamsEditor.getValue(paramsEditor);
                    if (got && typeof got === 'object' && got.error) {
                        if (window.VS.showMessage) {
                            window.VS.showMessage(got.error, 'error');
                        }
                        return;
                    }
                    if (editParams) {
                        editParams.value = typeof got === 'string' ? got : '';
                    }
                }
                var payload = {
                    action: 'save_docs',
                    api_id: editId ? editId.value : '',
                    params: editParams ? editParams.value : '',
                    response: editResponse ? editResponse.value : '',
                    aidoc: editAidoc ? editAidoc.value : '',
                    doc: editDoc ? editDoc.value : ''
                };
                if (window.VS.encodeTransportFields) {
                    window.VS.encodeTransportFields(payload, ['doc', 'aidoc', 'response', 'params']);
                }
                var fd = new FormData();
                Object.keys(payload).forEach(function (k) {
                    fd.append(k, payload[k] == null ? '' : String(payload[k]));
                });
                saving = true;
                if (typeof window.VS.setLoading === 'function') {
                    window.VS.setLoading(true);
                }
                window.VS.postForm(fd, window.location.href).then(function (res) {
                    saving = false;
                    if (typeof window.VS.setLoading === 'function') {
                        window.VS.setLoading(false);
                    }
                    if (!res || res.code !== 1) {
                        if (window.VS.showMessage) {
                            window.VS.showMessage((res && res.msg) || '保存失败', 'error');
                        }
                        return;
                    }
                    applySavedSlots(payload.api_id, res.data || {});
                    closeOverlay();
                    if (window.VS.showMessage) {
                        window.VS.showMessage(res.msg || '文档已保存', 'success');
                    }
                }).catch(function () {
                    saving = false;
                    if (typeof window.VS.setLoading === 'function') {
                        window.VS.setLoading(false);
                    }
                    if (window.VS.showMessage) {
                        window.VS.showMessage('保存失败', 'error');
                    }
                });
            });
        }

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
