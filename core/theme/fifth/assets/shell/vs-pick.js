/**
 * 文件：assets/js/vs-pick.js
 * 作用：美化原生 <select> 为自定义面板（dropdown / sheet 弹层）
 */
(function (window, document) {
    'use strict';

    var OPEN_CLASS = 'is-open';
    var sheetHost = null;

    function pickMode(select) {
        var raw = select ? String(select.getAttribute('data-vs-pick') || '').trim().toLowerCase() : '';
        if (raw === 'sheet' || raw === 'portal' || raw === 'overlay') {
            return 'sheet';
        }
        // 底栏「每页」默认走弹层，避免贴底被裁切或变成原生下拉观感
        if (select && select.closest && select.closest('.vs-api-list-pagesize')) {
            return 'sheet';
        }
        return 'dropdown';
    }

    function ensureSheet() {
        if (sheetHost && sheetHost.parentNode) {
            return sheetHost;
        }
        sheetHost = document.getElementById('vsPickSheet');
        if (!sheetHost) {
            sheetHost = document.createElement('div');
            sheetHost.id = 'vsPickSheet';
            sheetHost.className = 'vs-pick-sheet';
            sheetHost.hidden = true;
            sheetHost.innerHTML = ''
                + '<div class="vs-pick-sheet__backdrop" data-vs-pick-sheet-close="1"></div>'
                + '<div class="vs-pick-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="vsPickSheetTitle">'
                + '<div class="vs-pick-sheet__handle" aria-hidden="true"></div>'
                + '<h3 class="vs-pick-sheet__title" id="vsPickSheetTitle">请选择</h3>'
                + '<div class="vs-pick-sheet__options" id="vsPickSheetOptions"></div>'
                + '</div>';
            document.body.appendChild(sheetHost);
            sheetHost.addEventListener('click', function (e) {
                if (e.target && e.target.getAttribute && e.target.getAttribute('data-vs-pick-sheet-close') === '1') {
                    closeSheet();
                }
            });
            var panelEl = sheetHost.querySelector('.vs-pick-sheet__panel');
            if (panelEl) {
                panelEl.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }
        }
        return sheetHost;
    }

    function closeSheet() {
        if (!sheetHost) {
            return;
        }
        sheetHost.hidden = true;
        sheetHost.removeAttribute('data-active-wrap');
        var nodes = document.querySelectorAll('.vs-pick.' + OPEN_CLASS);
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].classList.remove(OPEN_CLASS);
            var btn = nodes[i].querySelector('.vs-pick__btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        }
    }

    function closeAll(except) {
        closeSheet();
        var nodes = document.querySelectorAll('.vs-pick.' + OPEN_CLASS);
        for (var i = 0; i < nodes.length; i++) {
            if (except && nodes[i] === except) {
                continue;
            }
            nodes[i].classList.remove(OPEN_CLASS);
            var panel = nodes[i].querySelector('.vs-pick__panel');
            if (panel) {
                panel.hidden = true;
            }
            var btn = nodes[i].querySelector('.vs-pick__btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        }
    }

    function selectedLabel(select) {
        if (!select || select.selectedIndex < 0) {
            return '请选择';
        }
        var opt = select.options[select.selectedIndex];
        return opt ? String(opt.textContent || '').trim() : '请选择';
    }

    function sheetTitleFor(select) {
        if (select && select.closest && select.closest('.vs-api-list-pagesize')) {
            return '每页条数';
        }
        var id = select && select.id ? String(select.id) : '';
        var label = id ? document.querySelector('label[for="' + id.replace(/"/g, '') + '"]') : null;
        if (label) {
            var t = String(label.textContent || '').replace(/\s+/g, ' ').trim();
            if (t) {
                return t;
            }
        }
        return '请选择';
    }

    function rebuildOptions(wrap, select) {
        var panel = wrap.querySelector('.vs-pick__panel');
        if (!panel) {
            return;
        }
        panel.innerHTML = '';
        for (var i = 0; i < select.options.length; i++) {
            (function (idx) {
                var opt = select.options[idx];
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'vs-pick__option' + (opt.selected ? ' is-selected' : '');
                item.setAttribute('role', 'option');
                item.setAttribute('data-index', String(idx));
                item.textContent = String(opt.textContent || '').trim();
                if (opt.disabled) {
                    item.disabled = true;
                }
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    select.selectedIndex = idx;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel(wrap, select);
                    closeAll();
                });
                panel.appendChild(item);
            })(i);
        }
    }

    function openSheet(wrap, select) {
        var host = ensureSheet();
        var titleEl = host.querySelector('#vsPickSheetTitle');
        var optsEl = host.querySelector('#vsPickSheetOptions');
        if (titleEl) {
            titleEl.textContent = sheetTitleFor(select);
        }
        if (optsEl) {
            optsEl.innerHTML = '';
            for (var i = 0; i < select.options.length; i++) {
                (function (idx) {
                    var opt = select.options[idx];
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'vs-pick-sheet__option' + (opt.selected ? ' is-selected' : '');
                    item.textContent = String(opt.textContent || '').trim();
                    if (opt.disabled) {
                        item.disabled = true;
                    }
                    item.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        select.selectedIndex = idx;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        syncLabel(wrap, select);
                        closeAll();
                    });
                    optsEl.appendChild(item);
                })(i);
            }
        }
        wrap.classList.add(OPEN_CLASS);
        var btn = wrap.querySelector('.vs-pick__btn');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        host.hidden = false;
    }

    function syncLabel(wrap, select) {
        var btn = wrap.querySelector('.vs-pick__btn');
        if (btn) {
            btn.textContent = selectedLabel(select);
        }
        var opts = wrap.querySelectorAll('.vs-pick__option');
        for (var i = 0; i < opts.length; i++) {
            opts[i].classList.toggle('is-selected', i === select.selectedIndex);
        }
    }

    function enhance(select) {
        if (!select || select.getAttribute('data-vs-pick-ready') === '1') {
            return;
        }
        if (select.multiple) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'vs-pick';
        wrap.setAttribute('data-vs-pick-mode', pickMode(select));
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('vs-pick__native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vs-pick__btn vs-input';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.textContent = selectedLabel(select);
        wrap.insertBefore(btn, select);

        var panel = document.createElement('div');
        panel.className = 'vs-pick__panel';
        panel.setAttribute('role', 'listbox');
        panel.hidden = true;
        wrap.appendChild(panel);

        rebuildOptions(wrap, select);
        select.setAttribute('data-vs-pick-ready', '1');

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var mode = pickMode(select);
            wrap.setAttribute('data-vs-pick-mode', mode);
            var willOpen = !wrap.classList.contains(OPEN_CLASS);
            closeAll();
            if (!willOpen) {
                return;
            }
            if (mode === 'sheet') {
                openSheet(wrap, select);
                return;
            }
            rebuildOptions(wrap, select);
            wrap.classList.add(OPEN_CLASS);
            panel.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
        });

        select.addEventListener('change', function () {
            syncLabel(wrap, select);
        });
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll('select[data-vs-pick]');
        for (var i = 0; i < nodes.length; i++) {
            enhance(nodes[i]);
        }
    }

    document.addEventListener('click', function () {
        closeAll();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }

    window.VSPick = {
        init: init,
        enhance: enhance,
        close: closeAll,
        refresh: function (select) {
            if (!select) {
                return;
            }
            var wrap = select.closest ? select.closest('.vs-pick') : null;
            if (!wrap) {
                enhance(select);
                return;
            }
            wrap.setAttribute('data-vs-pick-mode', pickMode(select));
            rebuildOptions(wrap, select);
            syncLabel(wrap, select);
        }
    };
})(window, document);
