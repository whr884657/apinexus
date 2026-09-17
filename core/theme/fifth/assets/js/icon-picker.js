/**
 * 文件：assets/js/icon-picker.js
 * 作用：后台内置 SVG 图标选择器（搜索 / 懒加载 / 轻动画）
 */
(function (global) {
    'use strict';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function iconKeyFromUrl(url) {
        var s = String(url || '');
        var m = s.match(/\/([^\/]+)\.svg(?:\?|$)/i);
        return m ? m[1].toLowerCase() : s.toLowerCase();
    }

    /**
     * @param {HTMLElement} pickerEl
     * @param {string[]} iconUrls
     * @param {{searchInput?: HTMLInputElement|null, onSelect?: Function}} options
     */
    function mountIconPicker(pickerEl, iconUrls, options) {
        options = options || {};
        if (!pickerEl || !Array.isArray(iconUrls)) {
            return {
                getSelected: function () { return ''; },
                setSelected: function () {},
                filter: function () {}
            };
        }

        var searchInput = options.searchInput || null;
        var onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
        var selectedUrl = '';
        var fragment = document.createDocumentFragment();

        pickerEl.classList.add('vs-icon-picker');
        pickerEl.innerHTML = '';

        iconUrls.forEach(function (url, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-api-cat-icon-pick vs-icon-picker__item';
            btn.setAttribute('data-icon-url', url);
            btn.setAttribute('data-icon-key', iconKeyFromUrl(url));
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.style.setProperty('--icon-i', String(index % 24));
            btn.innerHTML = '<img alt="" width="40" height="40" loading="lazy" decoding="async" src="' + escapeHtml(url) + '">';
            btn.addEventListener('click', function () {
                setSelected(url);
                if (onSelect) {
                    onSelect(url);
                }
            });
            fragment.appendChild(btn);
        });
        pickerEl.appendChild(fragment);

        function setSelected(url) {
            selectedUrl = String(url || '').trim();
            var items = pickerEl.querySelectorAll('.vs-icon-picker__item');
            items.forEach(function (btn) {
                var on = (btn.getAttribute('data-icon-url') || '') === selectedUrl;
                btn.classList.toggle('is-selected', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }

        function getSelected() {
            var sel = pickerEl.querySelector('.vs-icon-picker__item.is-selected');
            if (sel) {
                return sel.getAttribute('data-icon-url') || selectedUrl || '';
            }
            return selectedUrl || '';
        }

        function filter(query) {
            var q = String(query || '').trim().toLowerCase();
            var visible = 0;
            pickerEl.querySelectorAll('.vs-icon-picker__item').forEach(function (btn) {
                var key = btn.getAttribute('data-icon-key') || '';
                var url = (btn.getAttribute('data-icon-url') || '').toLowerCase();
                var show = !q || key.indexOf(q) !== -1 || url.indexOf(q) !== -1;
                btn.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });
            pickerEl.classList.toggle('is-empty-filter', visible === 0);
            return visible;
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filter(searchInput.value);
            });
        }

        return {
            getSelected: getSelected,
            setSelected: setSelected,
            filter: filter
        };
    }

    global.VsIconPicker = {
        mount: mountIconPicker,
        iconKeyFromUrl: iconKeyFromUrl
    };
})(window);
