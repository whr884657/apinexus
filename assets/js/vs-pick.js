/**
 * 文件：assets/js/vs-pick.js
 * 作用：美化原生 <select> 为自定义下拉面板（保留原生 select 做表单提交）
 */
(function (window, document) {
    'use strict';

    var OPEN_CLASS = 'is-open';

    function closeAll(except) {
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
            var willOpen = !wrap.classList.contains(OPEN_CLASS);
            closeAll();
            if (willOpen) {
                rebuildOptions(wrap, select);
                wrap.classList.add(OPEN_CLASS);
                panel.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
            }
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
        refresh: function (select) {
            if (!select) {
                return;
            }
            var wrap = select.closest ? select.closest('.vs-pick') : null;
            if (!wrap) {
                enhance(select);
                return;
            }
            rebuildOptions(wrap, select);
            syncLabel(wrap, select);
        }
    };
})(window, document);
