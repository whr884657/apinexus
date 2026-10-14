/**
 * 文件：assets/js/theme-settings.js
 * 作用：后台主题设置：二级导航、主题切换、主题配置面板
 */
(function () {
    'use strict';

    var page = document.getElementById('themeSettingsPage');
    var form = document.getElementById('themeSettingsForm');
    var configForm = document.getElementById('themeConfigForm');
    var configBody = document.getElementById('themeConfigFormBody');
    var configName = document.getElementById('themeConfigActiveName');
    var configSaveBtn = document.getElementById('themeConfigSaveBtn');
    var switchPanel = document.getElementById('themeSwitchPanel');
    var configPanel = document.getElementById('themeConfigPanel');
    var tabButtons = document.querySelectorAll('.vs-product-tabs__btn');

    function getActiveThemeId() {
        return page ? (page.getAttribute('data-active-theme') || '') : '';
    }

    function setActiveThemeMeta(themeId, themeName) {
        if (page) {
            page.setAttribute('data-active-theme', themeId);
            page.setAttribute('data-active-name', themeName || themeId);
        }
        if (configName) {
            configName.textContent = themeName || themeId;
        }
    }

    /** AjaxResponse::success 将字段合并到响应顶层 */
    function pickPayload(data) {
        if (!data) {
            return {};
        }
        if (data.data && typeof data.data === 'object') {
            return data.data;
        }
        return data;
    }

    function refreshActiveBadges() {
        if (!form) {
            return;
        }
        var activeId = getActiveThemeId();

        form.querySelectorAll('.vs-theme-card').forEach(function (card) {
            var radio = card.querySelector('input[name="frontend_theme"]');
            var themeId = card.getAttribute('data-theme-id') || (radio ? radio.value : '');
            var isSavedActive = themeId === activeId;
            card.classList.toggle('is-active', isSavedActive);
            if (radio) {
                radio.checked = isSavedActive;
            }

            // 徽章挂在卡片根上（电脑预览区右上 / 手机整卡右上），勿挂进 preview（overflow 会裁切）
            var status = card.querySelector('.vs-theme-card__status');
            if (isSavedActive) {
                if (!status) {
                    status = document.createElement('span');
                    status.className = 'vs-theme-card__status';
                    status.textContent = '当前使用';
                    card.appendChild(status);
                } else if (status.parentNode !== card) {
                    card.appendChild(status);
                }
            } else if (status) {
                status.remove();
            }
        });
    }

    function switchTab(tabId) {
        tabButtons.forEach(function (btn) {
            var on = btn.getAttribute('data-tab') === tabId;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        if (switchPanel) {
            switchPanel.classList.toggle('is-active', tabId === 'switch');
            switchPanel.hidden = tabId !== 'switch';
        }
        if (configPanel) {
            configPanel.classList.toggle('is-active', tabId === 'config');
            configPanel.hidden = tabId !== 'config';
        }

        // 首屏已由 PHP 渲染配置表单；仅在切换主题后标记需刷新时再 AJAX
        if (tabId === 'config' && configBody && configBody.getAttribute('data-needs-reload') === '1') {
            reloadConfigPanel();
        }
    }

    function reloadConfigPanel() {
        if (!configBody) {
            return;
        }
        if (window.VS && VS.setLoading) {
            VS.setLoading(configBody, '正在加载主题配置', true);
        } else {
            configBody.innerHTML = '<p class="vs-theme-config-empty">正在加载</p>';
        }
        if (configSaveBtn) {
            configSaveBtn.disabled = true;
        }

        var fd = new FormData();
        fd.append('action', 'load_theme_settings');
        fd.append('csrf_token', window.VS_CSRF_TOKEN || '');

        window.VS.postForm(fd, window.location.href)
            .then(function (data) {
                if (!data || Number(data.code) !== 1) {
                    window.VS.showMessage((data && data.msg) || '加载失败', 'error');
                    return;
                }
                var payload = pickPayload(data);
                if (payload.theme_name) {
                    setActiveThemeMeta(payload.theme_id || getActiveThemeId(), payload.theme_name);
                }
                configBody.innerHTML = payload.html || '<p class="vs-theme-config-empty">当前主题暂无可调整的项目</p>';
                configBody.removeAttribute('data-needs-reload');
                // E327：整块容器须 VSPick.init；refresh 仅接受单个 <select>
                if (window.VSPick && typeof window.VSPick.init === 'function') {
                    window.VSPick.init(configBody);
                }
                if (configSaveBtn) {
                    configSaveBtn.disabled = !payload.has_schema;
                }
            })
            .catch(function (err) {
                var msg = '网络异常，请稍后重试';
                if (err && err.name === 'TypeError') {
                    msg = '页面脚本异常，请刷新后重试';
                } else if (err && err.message === 'invalid_json') {
                    msg = '响应解析失败，请刷新页面后重试';
                }
                window.VS.showMessage(msg, 'error');
            });
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchTab(btn.getAttribute('data-tab') || 'switch');
        });
    });

    if (form) {
        var gallery = form.querySelector('.vs-theme-gallery');
        var cardSave = document.getElementById('themeCardSave')
            || document.getElementById('themeMobileSave');
        var hoveredCell = null;

        function isDesktopThemeUi() {
            return window.matchMedia && window.matchMedia('(min-width: 641px)').matches;
        }

        function cellThemeId(cell) {
            if (!cell) {
                return '';
            }
            var r = cell.querySelector('input[name="frontend_theme"]');
            return cell.getAttribute('data-theme-id') || (r ? r.value : '');
        }

        function findPendingCell() {
            var activeId = getActiveThemeId();
            var found = null;
            form.querySelectorAll('.vs-theme-card-cell').forEach(function (cell) {
                var r = cell.querySelector('input[name="frontend_theme"]');
                var themeId = cellThemeId(cell);
                if (r && r.checked && themeId !== activeId) {
                    found = cell;
                }
            });
            return found;
        }

        /** 勾选「启用」所在单元格的主题，避免悬停未点选时提交错主题（E430） */
        function ensureCardSaveTargetChecked() {
            if (!cardSave) {
                return;
            }
            var cell = cardSave.closest('.vs-theme-card-cell');
            if (!cell) {
                return;
            }
            var radio = cell.querySelector('input[name="frontend_theme"]');
            if (radio && !radio.checked) {
                radio.checked = true;
            }
        }

        /**
         * 卡下「启用」：点选待切换主题，或电脑悬停非当前主题时，插到该单元格下方（E430）。
         * 仅该格向下延伸，网格其它列对齐起点（align-items:start）。
         */
        function syncCardSaveSlot() {
            if (!cardSave || !gallery) {
                return;
            }
            var activeId = getActiveThemeId();
            var target = null;
            if (hoveredCell && isDesktopThemeUi() && cellThemeId(hoveredCell) !== activeId) {
                target = hoveredCell;
            }
            if (!target) {
                target = findPendingCell();
            }

            gallery.querySelectorAll('.vs-theme-card-cell.is-save-open').forEach(function (cell) {
                cell.classList.remove('is-save-open');
            });

            if (target) {
                if (cardSave.parentNode !== target) {
                    target.appendChild(cardSave);
                }
                target.classList.add('is-save-open');
                cardSave.hidden = false;
                cardSave.classList.add('is-open');
            } else {
                cardSave.classList.remove('is-open');
                cardSave.hidden = true;
                if (cardSave.parentNode !== gallery) {
                    gallery.appendChild(cardSave);
                }
            }
        }

        function refreshSelectionState() {
            var activeId = getActiveThemeId();
            form.querySelectorAll('.vs-theme-card').forEach(function (card) {
                var r = card.querySelector('input[name="frontend_theme"]');
                var themeId = card.getAttribute('data-theme-id') || (r ? r.value : '');
                var isPending = !!(r && r.checked && themeId !== activeId);
                card.classList.toggle('is-selected', isPending);
            });
            syncCardSaveSlot();
        }

        form.querySelectorAll('input[name="frontend_theme"]').forEach(function (radio) {
            radio.addEventListener('change', refreshSelectionState);
        });

        if (gallery) {
            gallery.querySelectorAll('.vs-theme-card-cell').forEach(function (cell) {
                cell.addEventListener('mouseenter', function () {
                    if (!isDesktopThemeUi()) {
                        return;
                    }
                    hoveredCell = cell;
                    syncCardSaveSlot();
                });
                cell.addEventListener('mouseleave', function (ev) {
                    if (!isDesktopThemeUi()) {
                        return;
                    }
                    var next = ev.relatedTarget;
                    if (next && cell.contains(next)) {
                        return;
                    }
                    if (hoveredCell === cell) {
                        hoveredCell = null;
                    }
                    syncCardSaveSlot();
                });
            });
        }

        if (cardSave) {
            var saveBtn = cardSave.querySelector('[type="submit"]');
            if (saveBtn) {
                saveBtn.addEventListener('click', ensureCardSaveTargetChecked);
            }
        }

        if (window.matchMedia) {
            var mq = window.matchMedia('(min-width: 641px)');
            var onMq = function () {
                if (!isDesktopThemeUi()) {
                    hoveredCell = null;
                }
                syncCardSaveSlot();
            };
            if (typeof mq.addEventListener === 'function') {
                mq.addEventListener('change', onMq);
            } else if (typeof mq.addListener === 'function') {
                mq.addListener(onMq);
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            ensureCardSaveTargetChecked();
            refreshSelectionState();
            var submitBtns = form.querySelectorAll('[type="submit"]');
            submitBtns.forEach(function (btn) {
                btn.disabled = true;
            });

            window.VS.postForm(form)
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '保存失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已保存', 'success');
                    var payload = pickPayload(data);
                    if (payload.theme_id) {
                        setActiveThemeMeta(payload.theme_id, payload.theme_name || payload.theme_id);
                        if (configBody) {
                            configBody.setAttribute('data-needs-reload', '1');
                        }
                    }
                    hoveredCell = null;
                    refreshActiveBadges();
                    refreshSelectionState();
                })
                .catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    submitBtns.forEach(function (btn) {
                        btn.disabled = false;
                    });
                });
        });

        refreshActiveBadges();
        refreshSelectionState();
    }

    if (configForm) {
        configForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (configSaveBtn) {
                configSaveBtn.disabled = true;
            }
            window.VS.postForm(configForm)
                .then(function (data) {
                    if (data.code !== 1) {
                        window.VS.showMessage(data.msg || '保存失败', 'error');
                        return;
                    }
                    window.VS.showMessage(data.msg || '已保存', 'success');
                })
                .catch(function () {
                    window.VS.showMessage('网络异常，请稍后重试', 'error');
                })
                .finally(function () {
                    if (configSaveBtn) {
                        configSaveBtn.disabled = false;
                    }
                });
        });
    }
})();
