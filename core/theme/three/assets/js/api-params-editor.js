/**
 * 接口请求参数编辑器：默认表格；可切换 JSON 并自动互转
 * 参数类型：嵌套选择器（弹窗中的弹窗 / 抽屉中的抽屉）+ 预设网格 + 自定义输入
 */
(function (global) {
    'use strict';

    var PARAM_TYPES = [
        { value: 'string', label: '字符串' },
        { value: 'text', label: '长文本' },
        { value: 'char', label: '字符' },
        { value: 'integer', label: '整数' },
        { value: 'long', label: '长整数' },
        { value: 'short', label: '短整数' },
        { value: 'byte', label: '字节' },
        { value: 'float', label: '浮点数' },
        { value: 'double', label: '双精度' },
        { value: 'boolean', label: '布尔值' },
        { value: 'boolean[]', label: '布尔数组' },
        { value: 'array', label: '列表' },
        { value: 'object', label: '对象' },
        { value: 'json', label: 'JSON' },
        { value: 'file', label: '文件' },
        { value: 'blob', label: '二进制 Blob' },
        { value: 'datetime', label: '日期时间' },
        { value: 'timestamp', label: '时间戳' },
        { value: 'email', label: '邮箱' },
        { value: 'url', label: '链接 URL' },
        { value: 'phone', label: '手机号' },
        { value: 'ip', label: 'IP' },
        { value: 'password', label: '密码' },
        { value: 'uuid', label: 'UUID' },
        { value: 'enum', label: '枚举' }
    ];

    var TYPE_ALIASES = {
        str: 'string',
        string: 'string',
        text: 'text',
        char: 'char',
        int: 'integer',
        integer: 'integer',
        number: 'integer',
        long: 'long',
        short: 'short',
        byte: 'byte',
        float: 'float',
        double: 'double',
        bool: 'boolean',
        boolean: 'boolean',
        'boolean[]': 'boolean[]',
        list: 'array',
        array: 'array',
        object: 'object',
        obj: 'object',
        json: 'json',
        file: 'file',
        blob: 'blob',
        datetime: 'datetime',
        date: 'datetime',
        timestamp: 'timestamp',
        time: 'timestamp',
        email: 'email',
        url: 'url',
        link: 'url',
        phone: 'phone',
        mobile: 'phone',
        ip: 'ip',
        password: 'password',
        pass: 'password',
        uuid: 'uuid',
        enum: 'enum'
    };

    var typePickerState = {
        el: null,
        targetInput: null,
        targetBtn: null,
        selected: 'string'
    };

    function escapeHtml(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function isCustomTypeToken(token) {
        return /^[A-Za-z][A-Za-z0-9_\[\]\.\-]{0,63}$/.test(token);
    }

    function normalizeType(raw) {
        var key = String(raw == null ? '' : raw).trim();
        if (!key) {
            return 'string';
        }
        var lower = key.toLowerCase();
        if (TYPE_ALIASES[lower]) {
            return TYPE_ALIASES[lower];
        }
        for (var i = 0; i < PARAM_TYPES.length; i++) {
            if (PARAM_TYPES[i].value.toLowerCase() === lower) {
                return PARAM_TYPES[i].value;
            }
        }
        if (isCustomTypeToken(key)) {
            return key;
        }
        return 'string';
    }

    function typeLabel(value) {
        var v = normalizeType(value);
        for (var i = 0; i < PARAM_TYPES.length; i++) {
            if (PARAM_TYPES[i].value === v) {
                return v + ' · ' + PARAM_TYPES[i].label;
            }
        }
        return v + ' · 自定义';
    }

    function normalizeRow(item) {
        if (!item || typeof item !== 'object') {
            return null;
        }
        var name = String(item.name != null ? item.name : (item.key != null ? item.key : '')).trim();
        if (!name) {
            return null;
        }
        var required = item.required === true || item.required === 1 || item.required === '1' || item.required === 'true';
        return {
            name: name,
            type: normalizeType(item.type),
            required: required,
            description: String(item.description != null ? item.description : (item.desc != null ? item.desc : '')),
            example: String(item.example != null ? item.example : '')
        };
    }

    function parseParamsJson(text) {
        var raw = String(text || '').trim();
        if (!raw) {
            return { ok: true, rows: [] };
        }
        try {
            var data = JSON.parse(raw);
            if (!Array.isArray(data)) {
                return { ok: false, error: '请求参数须为 JSON 数组' };
            }
            var rows = [];
            for (var i = 0; i < data.length; i++) {
                var row = normalizeRow(data[i]);
                if (row) {
                    rows.push(row);
                }
            }
            return { ok: true, rows: rows };
        } catch (e) {
            return { ok: false, error: '请求参数 JSON 格式无效' };
        }
    }

    function rowsToJson(rows) {
        var out = [];
        (rows || []).forEach(function (row) {
            if (!row || !row.name) {
                return;
            }
            var item = {
                name: row.name,
                type: row.type || 'string',
                required: !!row.required,
                description: row.description || ''
            };
            if (row.example) {
                item.example = row.example;
            }
            out.push(item);
        });
        return out.length ? JSON.stringify(out, null, 4) : '';
    }

    function buildRowHtml(row) {
        row = row || { name: '', type: 'string', required: false, description: '', example: '' };
        var typeVal = normalizeType(row.type || 'string');
        return ''
            + '<tr class="vs-params-editor__row">'
            + '<td><input type="text" class="vs-input vs-params-editor__name" value="' + escapeHtml(row.name) + '" placeholder="参数名" maxlength="64"></td>'
            + '<td class="vs-params-editor__type-cell">'
            + '<input type="hidden" class="vs-params-editor__type" value="' + escapeHtml(typeVal) + '">'
            + '<button type="button" class="vs-input vs-params-editor__type-btn" data-params-type-open aria-haspopup="dialog">'
            + escapeHtml(typeLabel(typeVal))
            + '</button>'
            + '</td>'
            + '<td class="vs-params-editor__req-cell"><label class="vs-check"><input type="checkbox" class="vs-params-editor__required"' + (row.required ? ' checked' : '') + '> 必填</label></td>'
            + '<td><input type="text" class="vs-input vs-params-editor__desc" value="' + escapeHtml(row.description) + '" placeholder="描述" maxlength="500"></td>'
            + '<td><input type="text" class="vs-input vs-params-editor__example" value="' + escapeHtml(row.example) + '" placeholder="示例" maxlength="200"></td>'
            + '<td class="vs-params-editor__actions"><button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-params-editor__remove" title="删除">删</button></td>'
            + '</tr>';
    }

    function setRowType(tr, value) {
        if (!tr) {
            return;
        }
        var typeVal = normalizeType(value);
        var input = tr.querySelector('.vs-params-editor__type');
        var btn = tr.querySelector('.vs-params-editor__type-btn');
        if (input) {
            input.value = typeVal;
        }
        if (btn) {
            btn.textContent = typeLabel(typeVal);
        }
    }

    function collectRows(root) {
        var rows = [];
        root.querySelectorAll('.vs-params-editor__row').forEach(function (tr) {
            var nameEl = tr.querySelector('.vs-params-editor__name');
            var typeEl = tr.querySelector('.vs-params-editor__type');
            var reqEl = tr.querySelector('.vs-params-editor__required');
            var descEl = tr.querySelector('.vs-params-editor__desc');
            var exEl = tr.querySelector('.vs-params-editor__example');
            var name = nameEl ? String(nameEl.value || '').trim() : '';
            if (!name) {
                return;
            }
            rows.push({
                name: name,
                type: normalizeType(typeEl ? typeEl.value : 'string'),
                required: !!(reqEl && reqEl.checked),
                description: descEl ? String(descEl.value || '').trim() : '',
                example: exEl ? String(exEl.value || '').trim() : ''
            });
        });
        return rows;
    }

    function syncHidden(root) {
        var hidden = root._paramsHidden;
        if (!hidden) {
            return;
        }
        hidden.value = rowsToJson(collectRows(root));
    }

    function findHostPanel(fromEl) {
        if (!fromEl || !fromEl.closest) {
            return null;
        }
        return fromEl.closest('.vs-overlay__panel') || null;
    }

    function ensureTypePicker() {
        if (typePickerState.el) {
            return typePickerState.el;
        }
        var el = document.createElement('div');
        el.className = 'vs-nested-picker vs-nested-picker--type';
        el.id = 'vsParamTypePicker';
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = ''
            + '<div class="vs-nested-picker__backdrop" data-type-picker-close></div>'
            + '<div class="vs-nested-picker__panel" role="dialog" aria-modal="true" aria-labelledby="vsParamTypePickerTitle">'
            + '<div class="vs-nested-picker__handle" aria-hidden="true"></div>'
            + '<header class="vs-nested-picker__head">'
            + '<h3 class="vs-nested-picker__title" id="vsParamTypePickerTitle">选择参数类型</h3>'
            + '<button type="button" class="vs-nested-picker__close" data-type-picker-close aria-label="关闭">&times;</button>'
            + '</header>'
            + '<div class="vs-nested-picker__body">'
            + '<div class="vs-nested-picker__grid" data-type-picker-grid></div>'
            + '<div class="vs-nested-picker__custom">'
            + '<label class="vs-label" for="vsParamTypeCustom">自定义类型</label>'
            + '<div class="vs-nested-picker__custom-row">'
            + '<input type="text" class="vs-input" id="vsParamTypeCustom" data-type-picker-custom maxlength="64" placeholder="如 decimal、int64、string[]">'
            + '<button type="button" class="vs-btn vs-btn--outline" data-type-picker-apply-custom>使用</button>'
            + '</div>'
            + '<p class="vs-form-hint">可点选上方预设，也可输入自定义类型名（字母开头，可含数字、_、[]、.、-）。</p>'
            + '</div>'
            + '</div>'
            + '<footer class="vs-nested-picker__foot">'
            + '<button type="button" class="vs-btn vs-btn--outline" data-type-picker-close>取消</button>'
            + '<button type="button" class="vs-btn vs-btn--primary" data-type-picker-confirm>确定</button>'
            + '</footer>'
            + '</div>';

        var grid = el.querySelector('[data-type-picker-grid]');
        PARAM_TYPES.forEach(function (t) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'vs-nested-picker__chip';
            btn.setAttribute('data-type-value', t.value);
            btn.innerHTML = '<span class="vs-nested-picker__chip-en">' + escapeHtml(t.value) + '</span>'
                + '<span class="vs-nested-picker__chip-zh">' + escapeHtml(t.label) + '</span>';
            grid.appendChild(btn);
        });

        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-type-picker-close]')) {
                closeTypePicker();
                return;
            }
            var chip = e.target.closest('[data-type-value]');
            if (chip && el.contains(chip)) {
                selectTypeInPicker(chip.getAttribute('data-type-value'));
                return;
            }
            if (e.target.closest('[data-type-picker-apply-custom]')) {
                var custom = el.querySelector('[data-type-picker-custom]');
                var raw = custom ? String(custom.value || '').trim() : '';
                if (!raw) {
                    if (global.VS && global.VS.showMessage) {
                        global.VS.showMessage('请输入自定义类型', 'error');
                    }
                    return;
                }
                if (!isCustomTypeToken(raw) && !TYPE_ALIASES[raw.toLowerCase()]) {
                    if (global.VS && global.VS.showMessage) {
                        global.VS.showMessage('自定义类型格式无效', 'error');
                    }
                    return;
                }
                selectTypeInPicker(normalizeType(raw));
                return;
            }
            if (e.target.closest('[data-type-picker-confirm]')) {
                confirmTypePicker();
            }
        });

        typePickerState.el = el;
        return el;
    }

    function selectTypeInPicker(value) {
        var el = ensureTypePicker();
        var typeVal = normalizeType(value);
        typePickerState.selected = typeVal;
        var chips = el.querySelectorAll('[data-type-value]');
        for (var i = 0; i < chips.length; i++) {
            chips[i].classList.toggle('is-selected', chips[i].getAttribute('data-type-value') === typeVal);
        }
        var custom = el.querySelector('[data-type-picker-custom]');
        if (custom) {
            var preset = false;
            for (var j = 0; j < PARAM_TYPES.length; j++) {
                if (PARAM_TYPES[j].value === typeVal) {
                    preset = true;
                    break;
                }
            }
            custom.value = preset ? '' : typeVal;
        }
    }

    function confirmTypePicker() {
        var el = ensureTypePicker();
        var custom = el.querySelector('[data-type-picker-custom]');
        var customRaw = custom ? String(custom.value || '').trim() : '';
        var finalType = typePickerState.selected || 'string';
        if (customRaw) {
            if (!isCustomTypeToken(customRaw) && !TYPE_ALIASES[customRaw.toLowerCase()]) {
                if (global.VS && global.VS.showMessage) {
                    global.VS.showMessage('自定义类型格式无效', 'error');
                }
                return;
            }
            finalType = normalizeType(customRaw);
        }
        if (typePickerState.targetInput) {
            var tr = typePickerState.targetInput.closest('tr');
            setRowType(tr, finalType);
            var editor = typePickerState.targetInput.closest('.vs-params-editor');
            if (editor) {
                syncHidden(editor);
            }
        }
        closeTypePicker();
    }

    function openTypePicker(triggerBtn) {
        var el = ensureTypePicker();
        var tr = triggerBtn.closest('tr');
        var input = tr ? tr.querySelector('.vs-params-editor__type') : null;
        var current = input ? normalizeType(input.value) : 'string';
        typePickerState.targetInput = input;
        typePickerState.targetBtn = triggerBtn;
        typePickerState.selected = current;

        var host = findHostPanel(triggerBtn);
        if (host) {
            if (el.parentNode !== host) {
                host.appendChild(el);
            }
            el.classList.add('vs-nested-picker--hosted');
            el.classList.remove('vs-nested-picker--viewport');
        } else {
            if (el.parentNode !== document.body) {
                document.body.appendChild(el);
            }
            el.classList.add('vs-nested-picker--viewport');
            el.classList.remove('vs-nested-picker--hosted');
        }

        selectTypeInPicker(current);
        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('is-open');
        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });

        var focusEl = el.querySelector('[data-type-value].is-selected')
            || el.querySelector('[data-type-picker-custom]')
            || el.querySelector('[data-type-picker-confirm]');
        if (focusEl && focusEl.focus) {
            focusEl.focus();
        }
    }

    function closeTypePicker() {
        var el = typePickerState.el;
        if (!el) {
            return;
        }
        el.classList.remove('is-visible');
        el.classList.remove('is-open');
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        var returnBtn = typePickerState.targetBtn;
        typePickerState.targetInput = null;
        typePickerState.targetBtn = null;
        if (returnBtn && returnBtn.focus) {
            returnBtn.focus();
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        var el = typePickerState.el;
        if (el && el.classList.contains('is-open')) {
            e.stopPropagation();
            closeTypePicker();
        }
    }, true);

    function renderTable(root, rows) {
        var body = root.querySelector('[data-params-tbody]');
        if (!body) {
            return;
        }
        if (!rows || !rows.length) {
            body.innerHTML = buildRowHtml(null);
        } else {
            body.innerHTML = rows.map(buildRowHtml).join('');
        }
        syncHidden(root);
    }

    function setMode(root, mode) {
        var isJson = mode === 'json';
        root.setAttribute('data-mode', isJson ? 'json' : 'table');
        var tableWrap = root.querySelector('[data-params-table]');
        var jsonWrap = root.querySelector('[data-params-json]');
        var tabTable = root.querySelector('[data-params-mode="table"]');
        var tabJson = root.querySelector('[data-params-mode="json"]');
        if (tableWrap) {
            tableWrap.hidden = isJson;
        }
        if (jsonWrap) {
            jsonWrap.hidden = !isJson;
        }
        if (tabTable) {
            tabTable.classList.toggle('is-active', !isJson);
        }
        if (tabJson) {
            tabJson.classList.toggle('is-active', isJson);
        }
        if (isJson) {
            var ta = root.querySelector('[data-params-json-input]');
            if (ta) {
                ta.value = rowsToJson(collectRows(root)) || '[]';
            }
        } else {
            var parsed = parseParamsJson((root.querySelector('[data-params-json-input]') || {}).value || '');
            if (parsed.ok) {
                renderTable(root, parsed.rows);
            }
        }
    }

    function applyJsonText(root, text, showError) {
        var parsed = parseParamsJson(text);
        if (!parsed.ok) {
            if (showError && global.VS && global.VS.showMessage) {
                global.VS.showMessage(parsed.error || 'JSON 无效', 'error');
            }
            return false;
        }
        renderTable(root, parsed.rows);
        var ta = root.querySelector('[data-params-json-input]');
        if (ta) {
            ta.value = rowsToJson(parsed.rows) || '[]';
        }
        return true;
    }

    function mount(root, options) {
        if (!root || root.getAttribute('data-params-ready') === '1') {
            return root;
        }
        options = options || {};
        var hiddenId = options.hiddenId || root.getAttribute('data-hidden-id') || '';
        var hidden = hiddenId ? document.getElementById(hiddenId) : root.querySelector('textarea[name="params"]');
        root._paramsHidden = hidden;

        root.innerHTML = ''
            + '<div class="vs-params-editor__bar">'
            + '<div class="vs-params-editor__tabs" role="tablist">'
            + '<button type="button" class="vs-params-editor__tab is-active" data-params-mode="table">表格填写</button>'
            + '<button type="button" class="vs-params-editor__tab" data-params-mode="json">JSON 数组</button>'
            + '</div>'
            + '<button type="button" class="vs-btn vs-btn--outline vs-params-editor__add" data-params-add>添加参数</button>'
            + '</div>'
            + '<div class="vs-params-editor__table-wrap" data-params-table>'
            + '<div class="vs-params-editor__scroll">'
            + '<table class="vs-params-editor__table">'
            + '<thead><tr>'
            + '<th>参数名</th><th>类型</th><th>必填</th><th>描述</th><th>示例</th><th></th>'
            + '</tr></thead>'
            + '<tbody data-params-tbody></tbody>'
            + '</table></div>'
            + '<p class="vs-form-hint">默认用表格填写；切换到 JSON 可粘贴数组，系统会自动识别参数名、类型、是否必填、描述与示例。</p>'
            + '</div>'
            + '<div class="vs-params-editor__json-wrap" data-params-json hidden>'
            + '<textarea class="vs-input vs-textarea vs-api-list-code" data-params-json-input rows="10" spellcheck="false"'
            + ' placeholder=\'[{"name":"key","type":"string","required":false,"description":"…","example":"…"}]\'></textarea>'
            + '<p class="vs-form-hint">粘贴或编辑后失焦将自动同步到表格；提交时以表格/JSON 互转结果为准。</p>'
            + '</div>';

        var initial = hidden ? String(hidden.value || '') : '';
        var parsed = parseParamsJson(initial);
        renderTable(root, parsed.ok ? parsed.rows : []);
        var ta = root.querySelector('[data-params-json-input]');
        if (ta) {
            ta.value = initial.trim() ? initial : '[]';
        }
        setMode(root, 'table');
        root.setAttribute('data-params-ready', '1');

        root.addEventListener('click', function (e) {
            var typeOpen = e.target.closest('[data-params-type-open]');
            if (typeOpen && root.contains(typeOpen)) {
                e.preventDefault();
                openTypePicker(typeOpen);
                return;
            }
            var modeBtn = e.target.closest('[data-params-mode]');
            if (modeBtn && root.contains(modeBtn)) {
                if (modeBtn.getAttribute('data-params-mode') === 'table') {
                    var jsonTa = root.querySelector('[data-params-json-input]');
                    if (jsonTa && !applyJsonText(root, jsonTa.value, true)) {
                        return;
                    }
                }
                setMode(root, modeBtn.getAttribute('data-params-mode'));
                return;
            }
            if (e.target.closest('[data-params-add]') && root.contains(e.target)) {
                var body = root.querySelector('[data-params-tbody]');
                if (body) {
                    body.insertAdjacentHTML('beforeend', buildRowHtml(null));
                }
                syncHidden(root);
                return;
            }
            var removeBtn = e.target.closest('.vs-params-editor__remove');
            if (removeBtn && root.contains(removeBtn)) {
                var tr = removeBtn.closest('tr');
                var tbody = root.querySelector('[data-params-tbody]');
                if (tr && tbody) {
                    if (tbody.querySelectorAll('.vs-params-editor__row').length <= 1) {
                        tr.querySelectorAll('input').forEach(function (inp) {
                            if (inp.type === 'checkbox') {
                                inp.checked = false;
                            } else if (inp.type === 'hidden' && inp.classList.contains('vs-params-editor__type')) {
                                inp.value = 'string';
                            } else if (inp.type !== 'hidden') {
                                inp.value = '';
                            }
                        });
                        setRowType(tr, 'string');
                    } else {
                        tr.parentNode.removeChild(tr);
                    }
                }
                syncHidden(root);
            }
        });

        root.addEventListener('input', function () {
            if (root.getAttribute('data-mode') === 'table') {
                syncHidden(root);
            }
        });
        root.addEventListener('change', function () {
            if (root.getAttribute('data-mode') === 'table') {
                syncHidden(root);
            }
        });

        if (ta) {
            ta.addEventListener('blur', function () {
                applyJsonText(root, ta.value, true);
            });
        }

        return root;
    }

    function getValue(root) {
        if (!root) {
            return '';
        }
        if (root.getAttribute('data-mode') === 'json') {
            var ta = root.querySelector('[data-params-json-input]');
            var parsed = parseParamsJson(ta ? ta.value : '');
            if (!parsed.ok) {
                return { error: parsed.error || 'JSON 无效' };
            }
            syncHidden(root);
            return rowsToJson(parsed.rows);
        }
        syncHidden(root);
        return rowsToJson(collectRows(root));
    }

    function setValue(root, text) {
        if (!root) {
            return;
        }
        applyJsonText(root, text || '', false);
        setMode(root, 'table');
    }

    global.VsParamsEditor = {
        types: PARAM_TYPES,
        mount: mount,
        getValue: getValue,
        setValue: setValue,
        parse: parseParamsJson,
        stringify: rowsToJson,
        closeTypePicker: closeTypePicker
    };
})(window);
