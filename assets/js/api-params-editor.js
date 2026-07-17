/**
 * 接口请求参数编辑器：默认表格；可切换 JSON 并自动互转
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

    function escapeHtml(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function normalizeType(raw) {
        var key = String(raw || 'string').trim().toLowerCase();
        if (TYPE_ALIASES[key]) {
            return TYPE_ALIASES[key];
        }
        for (var i = 0; i < PARAM_TYPES.length; i++) {
            if (PARAM_TYPES[i].value === key) {
                return PARAM_TYPES[i].value;
            }
        }
        return 'string';
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

    function typeOptionsHtml(selected) {
        var html = '';
        for (var i = 0; i < PARAM_TYPES.length; i++) {
            var t = PARAM_TYPES[i];
            html += '<option value="' + escapeHtml(t.value) + '"'
                + (t.value === selected ? ' selected' : '') + '>'
                + escapeHtml(t.value + ' · ' + t.label) + '</option>';
        }
        return html;
    }

    function buildRowHtml(row) {
        row = row || { name: '', type: 'string', required: false, description: '', example: '' };
        return ''
            + '<tr class="vs-params-editor__row">'
            + '<td><input type="text" class="vs-input vs-params-editor__name" value="' + escapeHtml(row.name) + '" placeholder="参数名" maxlength="64"></td>'
            + '<td><select class="vs-input vs-select vs-params-editor__type" data-vs-pick>' + typeOptionsHtml(row.type || 'string') + '</select></td>'
            + '<td class="vs-params-editor__req-cell"><label class="vs-check"><input type="checkbox" class="vs-params-editor__required"' + (row.required ? ' checked' : '') + '> 必填</label></td>'
            + '<td><input type="text" class="vs-input vs-params-editor__desc" value="' + escapeHtml(row.description) + '" placeholder="描述" maxlength="500"></td>'
            + '<td><input type="text" class="vs-input vs-params-editor__example" value="' + escapeHtml(row.example) + '" placeholder="示例" maxlength="200"></td>'
            + '<td class="vs-params-editor__actions"><button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-params-editor__remove" title="删除">删</button></td>'
            + '</tr>';
    }

    function enhanceTypePicks(scope) {
        if (!window.VSPick) {
            return;
        }
        var root = scope || document;
        var nodes = root.querySelectorAll ? root.querySelectorAll('select.vs-params-editor__type[data-vs-pick]') : [];
        for (var i = 0; i < nodes.length; i++) {
            window.VSPick.refresh(nodes[i]);
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
        enhanceTypePicks(body);
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
                    var last = body.querySelector('.vs-params-editor__row:last-child');
                    if (last) {
                        enhanceTypePicks(last);
                    }
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
                            } else {
                                inp.value = '';
                            }
                        });
                        var sel = tr.querySelector('select');
                        if (sel) {
                            sel.value = 'string';
                        }
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
        stringify: rowsToJson
    };
})(window);
