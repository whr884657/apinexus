<?php
/**
 * 文件：admin/system/sysapi.php
 * 作用：系统级外部 API 文档浏览器（UI 对齐接口文档；只读；参数视图右上角；代码块可复制；整份可复制为 Markdown）
 */

require_once dirname(__DIR__) . '/init.php';

/**
 * @param string $method
 * @return string
 */
function vs_sysapi_method_class($method)
{
    $m = strtolower((string) $method);
    if ($m === 'post') {
        return 'post';
    }
    if ($m === 'put') {
        return 'put';
    }
    if ($m === 'delete') {
        return 'delete';
    }
    return 'get';
}

/**
 * @param array  $methods
 * @param string $prefix
 * @return string
 */
function vs_sysapi_method_slash_html(array $methods, $prefix = 'method-slash')
{
    $parts = array();
    foreach ($methods as $m) {
        $m = strtoupper(trim((string) $m));
        if ($m === '') {
            continue;
        }
        $cls = vs_sysapi_method_class($m);
        $parts[] = '<span class="' . vs_e($prefix) . '__part ' . vs_e($prefix) . '__part--' . vs_e($cls) . '">'
            . vs_e($m) . '</span>';
    }
    if ($parts === array()) {
        return '';
    }
    $sep = '<span class="' . vs_e($prefix) . '__sep" aria-hidden="true">/</span>';
    return '<span class="' . vs_e($prefix) . '">' . implode($sep, $parts) . '</span>';
}

/**
 * 代码块（带复制按钮）
 *
 * @param string $langLabel
 * @param string $code
 * @param string $syn
 * @return string
 */
function vs_sysapi_code_block($langLabel, $code, $syn = 'json')
{
    $code = (string) $code;
    $syn = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $syn));
    if ($syn === '') {
        $syn = 'json';
    }
    return '<div class="code-block">'
        . '<div class="code-block__head">'
        . '<span class="code-block__lang">' . vs_e($langLabel) . '</span>'
        . '<button type="button" class="code-block__copy" data-copy-code data-copy="' . vs_e($code)
        . '" aria-label="复制" title="复制">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
        . '<span class="code-block__copy-text">复制</span>'
        . '</button></div>'
        . '<pre class="code-block__pre"><code class="language-' . vs_e($syn) . '" data-vs-syn="' . vs_e($syn) . '">'
        . vs_e($code) . '</code></pre></div>';
}

/**
 * 参数说明：表格 / JSON / OpenAPI 3.1（视图按钮右上角）
 *
 * @param array $item
 * @return string
 */
function vs_sysapi_params_html(array $item)
{
    $list = isset($item['params_list']) && is_array($item['params_list']) ? $item['params_list'] : array();
    $paramsJson = isset($item['params_json']) ? (string) $item['params_json'] : '';
    $openapiJson = isset($item['openapi_json']) ? (string) $item['openapi_json'] : '';

    if ($list === array() && $paramsJson === '' && $openapiJson === '') {
        return '<p class="doc-empty-hint">暂无参数说明</p>';
    }

    $html = '<div class="doc-params-modes" data-params-modes>';
    $html .= '<div class="doc-params-modes__toolbar">';
    $html .= '<div class="doc-params-modes__tabs" role="tablist" aria-label="参数视图">';
    if ($list !== array()) {
        $html .= '<button type="button" class="doc-params-modes__btn is-active" data-params-mode="table">表格</button>';
        $html .= '<button type="button" class="doc-params-modes__btn" data-params-mode="json">JSON</button>';
    } elseif ($paramsJson !== '') {
        $html .= '<button type="button" class="doc-params-modes__btn is-active" data-params-mode="json">JSON</button>';
    }
    if ($openapiJson !== '') {
        $html .= '<button type="button" class="doc-params-modes__btn" data-params-mode="openapi">OpenAPI 3.1</button>';
    }
    $html .= '</div></div>';

    if ($list !== array()) {
        $html .= '<div class="doc-params-modes__pane is-active" data-params-pane="table">';
        $html .= '<div class="params-table--wrap"><table class="params-table"><thead><tr>'
            . '<th>参数名</th><th>类型</th><th>必填</th><th>说明</th><th>示例</th></tr></thead><tbody>';
        foreach ($list as $p) {
            if (!is_array($p)) {
                continue;
            }
            $req = !empty($p['required'])
                ? '<span class="params-table__required params-table__required--yes">是</span>'
                : '<span class="params-table__required params-table__required--no">否</span>';
            $html .= '<tr>'
                . '<td><span class="params-table__name">' . vs_e(isset($p['name']) ? $p['name'] : '') . '</span></td>'
                . '<td><span class="params-table__type">' . vs_e(isset($p['type']) ? $p['type'] : '') . '</span></td>'
                . '<td>' . $req . '</td>'
                . '<td class="params-table__desc">' . vs_e(isset($p['description']) ? $p['description'] : '') . '</td>'
                . '<td><span class="params-table__type">' . vs_e(isset($p['example']) ? $p['example'] : '') . '</span></td>'
                . '</tr>';
        }
        $html .= '</tbody></table></div></div>';

        $html .= '<div class="doc-params-modes__pane" data-params-pane="json" hidden>'
            . vs_sysapi_code_block('JSON', $paramsJson, 'json') . '</div>';
    } elseif ($paramsJson !== '') {
        $html .= '<div class="doc-params-modes__pane is-active" data-params-pane="json">'
            . vs_sysapi_code_block('JSON', $paramsJson, 'json') . '</div>';
    }

    if ($openapiJson !== '') {
        $html .= '<div class="doc-params-modes__pane" data-params-pane="openapi" hidden>'
            . vs_sysapi_code_block('OpenAPI 3.1', $openapiJson, 'json') . '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * @param array $item
 * @return string
 */
function vs_sysapi_response_html(array $item)
{
    $samples = isset($item['response_samples']) && is_array($item['response_samples'])
        ? $item['response_samples']
        : array();
    if ($samples === array()) {
        if (empty($item['response_pretty'])) {
            return '<p class="doc-empty-hint">暂无响应示例</p>';
        }
        return vs_sysapi_code_block('JSON', (string) $item['response_pretty'], 'json');
    }

    $html = '<div class="doc-response-samples">';
    foreach ($samples as $sample) {
        if (!is_array($sample) || empty($sample['pretty'])) {
            continue;
        }
        $title = isset($sample['title']) ? trim((string) $sample['title']) : '响应';
        $http = isset($sample['http']) ? (int) $sample['http'] : 0;
        $label = $title;
        if ($http > 0) {
            $label .= ' · HTTP ' . $http;
        }
        $html .= vs_sysapi_code_block($label, (string) $sample['pretty'], 'json');
    }
    $html .= '</div>';
    return $html;
}

/**
 * 整份接口导出为 Markdown（供「复制为 Markdown」）
 *
 * @param array $item
 * @return string
 */
function vs_sysapi_export_markdown(array $item)
{
    $name = isset($item['name']) ? (string) $item['name'] : '';
    $desc = isset($item['desc']) ? (string) $item['desc'] : '';
    $endpoint = isset($item['endpoint']) ? (string) $item['endpoint'] : '';
    $methods = isset($item['methods']) && is_array($item['methods']) ? $item['methods'] : array();
    $methodsStr = implode(' / ', array_map('strtoupper', $methods));
    $lines = array();
    $lines[] = '# ' . $name;
    $lines[] = '';
    if ($desc !== '') {
        $lines[] = $desc;
        $lines[] = '';
    }
    $lines[] = '## 接口地址';
    $lines[] = '';
    $lines[] = '- 方法：`' . $methodsStr . '`';
    $lines[] = '- 地址：`' . $endpoint . '`';
    $lines[] = '';
    $lines[] = '## 参数说明';
    $lines[] = '';
    $list = isset($item['params_list']) && is_array($item['params_list']) ? $item['params_list'] : array();
    if ($list !== array()) {
        $lines[] = '| 参数名 | 类型 | 必填 | 说明 | 示例 |';
        $lines[] = '|--------|------|------|------|------|';
        foreach ($list as $p) {
            if (!is_array($p)) {
                continue;
            }
            $req = !empty($p['required']) ? '是' : '否';
            $lines[] = '| `' . str_replace('|', '\\|', (string) (isset($p['name']) ? $p['name'] : '')) . '` | '
                . str_replace('|', '\\|', (string) (isset($p['type']) ? $p['type'] : '')) . ' | '
                . $req . ' | '
                . str_replace(array('|', "\n", "\r"), array('\\|', ' ', ''), (string) (isset($p['description']) ? $p['description'] : '')) . ' | '
                . str_replace('|', '\\|', (string) (isset($p['example']) ? $p['example'] : '')) . ' |';
        }
        $lines[] = '';
    }
    if (!empty($item['params_json'])) {
        $lines[] = '### 参数 JSON';
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = (string) $item['params_json'];
        $lines[] = '```';
        $lines[] = '';
    }
    if (!empty($item['openapi_json'])) {
        $lines[] = '### OpenAPI 3.1';
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = (string) $item['openapi_json'];
        $lines[] = '```';
        $lines[] = '';
    }
    $lines[] = '## 响应示例';
    $lines[] = '';
    $respSamples = isset($item['response_samples']) && is_array($item['response_samples'])
        ? $item['response_samples']
        : array();
    if ($respSamples !== array()) {
        foreach ($respSamples as $sample) {
            if (!is_array($sample) || empty($sample['pretty'])) {
                continue;
            }
            $title = isset($sample['title']) ? trim((string) $sample['title']) : '响应';
            $http = isset($sample['http']) ? (int) $sample['http'] : 0;
            $heading = $title;
            if ($http > 0) {
                $heading .= '（HTTP ' . $http . '）';
            }
            $lines[] = '### ' . $heading;
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = (string) $sample['pretty'];
            $lines[] = '```';
            $lines[] = '';
        }
    } elseif (!empty($item['response_pretty'])) {
        $lines[] = '```json';
        $lines[] = (string) $item['response_pretty'];
        $lines[] = '```';
    } else {
        $lines[] = '_暂无_';
    }
    $lines[] = '';
    $lines[] = '## 请求示例';
    $lines[] = '';
    $bundle = isset($item['qs_bundle']) && is_array($item['qs_bundle']) ? $item['qs_bundle'] : array();
    $auths = isset($bundle['auths']) && is_array($bundle['auths']) ? $bundle['auths'] : array();
    $byAuth = isset($bundle['byAuth']) && is_array($bundle['byAuth']) ? $bundle['byAuth'] : array();
    $labels = isset($bundle['authLabels']) && is_array($bundle['authLabels']) ? $bundle['authLabels'] : array();
    if ($auths === array()) {
        $lines[] = '_暂无_';
        $lines[] = '';
    } else {
        foreach ($auths as $authId) {
            $lbl = isset($labels[$authId]) ? (string) $labels[$authId] : (string) $authId;
            $lines[] = '### ' . $lbl;
            $lines[] = '';
            $samples = isset($byAuth[$authId]) && is_array($byAuth[$authId]) ? $byAuth[$authId] : array();
            $lastGroup = '';
            foreach ($samples as $qs) {
                if (!is_array($qs)) {
                    continue;
                }
                $ql = isset($qs['label']) ? (string) $qs['label'] : '示例';
                $syn = isset($qs['syn']) ? (string) $qs['syn'] : 'bash';
                $code = isset($qs['code']) ? (string) $qs['code'] : '';
                // 标签形如「查询单张 · cURL」→ 按请求类型分组，一种类型一块
                $group = $ql;
                $langPart = $ql;
                if (preg_match('/^(.+?)\s*·\s*(.+)$/u', $ql, $m)) {
                    $group = trim($m[1]);
                    $langPart = trim($m[2]);
                }
                if ($group !== $lastGroup) {
                    $lines[] = '#### ' . $group;
                    $lines[] = '';
                    $lastGroup = $group;
                }
                $lines[] = '##### ' . $langPart;
                $lines[] = '';
                $lines[] = '```' . $syn;
                $lines[] = $code;
                $lines[] = '```';
                $lines[] = '';
            }
        }
    }
    $lines[] = '## 文档说明';
    $lines[] = '';
    $docMd = isset($item['doc_md']) ? trim((string) $item['doc_md']) : '';
    if ($docMd !== '') {
        $lines[] = $docMd;
    } else {
        $lines[] = '_暂无_';
    }
    $lines[] = '';
    $lines[] = '## 失败码';
    $lines[] = '';
    $errIntro = isset($item['errcode_intro']) ? trim((string) $item['errcode_intro']) : '';
    if ($errIntro !== '') {
        $lines[] = $errIntro;
        $lines[] = '';
    }
    $errList = isset($item['errcode_list']) && is_array($item['errcode_list']) ? $item['errcode_list'] : array();
    if ($errList !== array()) {
        $lines[] = '| errcode | 含义 |';
        $lines[] = '|---------|------|';
        foreach ($errList as $row) {
            if (!is_array($row) || !isset($row['code'])) {
                continue;
            }
            $lines[] = '| `' . (int) $row['code'] . '` | '
                . str_replace(array('|', "\n", "\r"), array('\\|', ' ', ''), (string) (isset($row['msg']) ? $row['msg'] : ''))
                . ' |';
        }
    } else {
        $lines[] = '_暂无_';
    }
    $lines[] = '';
    return implode("\n", $lines);
}

/**
 * @param array $item
 * @return string
 */
function vs_sysapi_doc_html(array $item)
{
    if (empty($item['doc_html'])) {
        return '<p class="doc-empty-hint">暂无文档说明</p>';
    }
    return $item['doc_html'];
}

/**
 * 失败码板块（引用公共 errcode.json）
 *
 * @param array $item
 * @return string
 */
function vs_sysapi_errcode_html(array $item)
{
    $intro = isset($item['errcode_intro']) ? trim((string) $item['errcode_intro']) : '';
    $list = isset($item['errcode_list']) && is_array($item['errcode_list']) ? $item['errcode_list'] : array();
    if ($list === array()) {
        return '<p class="doc-empty-hint">暂无失败码</p>';
    }
    $html = '<div class="doc-errcode">';
    if ($intro !== '') {
        $html .= '<p class="doc-errcode__intro">' . vs_e($intro) . '</p>';
    }
    $html .= '<div class="params-table--wrap"><table class="params-table"><thead><tr>'
        . '<th>errcode</th><th>含义</th></tr></thead><tbody>';
    foreach ($list as $row) {
        if (!is_array($row) || !isset($row['code'])) {
            continue;
        }
        $html .= '<tr>'
            . '<td><span class="params-table__name">' . vs_e((string) (int) $row['code']) . '</span></td>'
            . '<td class="params-table__desc">' . vs_e(isset($row['msg']) ? (string) $row['msg'] : '') . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table></div></div>';
    return $html;
}

/**
 * @param array $item
 * @return string
 */
function vs_sysapi_request_html(array $item)
{
    $bundle = isset($item['qs_bundle']) && is_array($item['qs_bundle']) ? $item['qs_bundle'] : array();
    $auths = isset($bundle['auths']) && is_array($bundle['auths']) ? $bundle['auths'] : array();
    $byAuth = isset($bundle['byAuth']) && is_array($bundle['byAuth']) ? $bundle['byAuth'] : array();
    $labels = isset($bundle['authLabels']) && is_array($bundle['authLabels'])
        ? $bundle['authLabels']
        : (class_exists('ApiQuickstart') ? ApiQuickstart::authLabels() : array());

    if ($auths === array()) {
        return '<p class="doc-empty-hint">暂无请求示例</p>';
    }

    $html = '<div class="docs-qs" data-docs-qs>';
    if (count($auths) > 1) {
        /* 传钥方式按钮靠右上（右手原则；对齐参数说明「表格 / JSON / OpenAPI」） */
        $html .= '<div class="docs-qs__toolbar">';
        $html .= '<div class="docs-qs__auth-tabs" role="tablist" aria-label="传钥方式">';
        foreach ($auths as $ai => $authId) {
            $lbl = isset($labels[$authId]) ? $labels[$authId] : $authId;
            $active = $ai === 0 ? ' is-active' : '';
            $html .= '<button type="button" class="docs-qs__auth-tab' . $active . '" data-qs-auth="'
                . vs_e($authId) . '" role="tab" aria-selected="' . ($ai === 0 ? 'true' : 'false') . '">'
                . vs_e($lbl) . '</button>';
        }
        $html .= '</div></div>';
    }

    foreach ($auths as $ai => $authId) {
        $samples = isset($byAuth[$authId]) && is_array($byAuth[$authId]) ? $byAuth[$authId] : array();
        $paneHidden = $ai === 0 ? '' : ' hidden';
        $paneActive = $ai === 0 ? ' is-active' : '';
        $html .= '<div class="docs-qs__auth-pane' . $paneActive . '" data-qs-auth-pane="'
            . vs_e($authId) . '"' . $paneHidden . '>';
        if ($samples === array()) {
            $html .= '<p class="doc-empty-hint">该鉴权方式暂无示例</p>';
        } else {
            $lastGroup = '';
            foreach ($samples as $qs) {
                if (!is_array($qs)) {
                    continue;
                }
                $ql = isset($qs['label']) ? (string) $qs['label'] : '示例';
                $group = $ql;
                $langPart = $ql;
                if (preg_match('/^(.+?)\s*·\s*(.+)$/u', $ql, $m)) {
                    $group = trim($m[1]);
                    $langPart = trim($m[2]);
                }
                if ($group !== $lastGroup) {
                    $html .= '<div class="docs-qs__variant-title">' . vs_e($group) . '</div>';
                    $lastGroup = $group;
                }
                $html .= vs_sysapi_code_block(
                    $langPart,
                    isset($qs['code']) ? (string) $qs['code'] : '',
                    isset($qs['syn']) ? (string) $qs['syn'] : 'bash'
                );
            }
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

$docsCtx = SystemApiCatalog::docsContexts();
$grouped = array();
foreach ($docsCtx as $ctx) {
    $cat = $ctx['category'];
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = array();
    }
    $grouped[$cat][] = $ctx;
}

$firstId = isset($docsCtx[0]) ? (string) $docsCtx[0]['id'] : '';
$firstName = isset($docsCtx[0]) ? (string) $docsCtx[0]['name'] : '';

vs_admin_layout_start('系统 API', 'sys-api');
?>

<div id="sysApiPage" class="vs-docs-browser" data-first-id="<?php echo vs_e($firstId); ?>"
     data-first-name="<?php echo vs_e($firstName); ?>">
    <?php if (count($docsCtx) === 0): ?>
        <div class="vs-api-list-empty vs-api-list-empty--hero">
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无系统级 API</h3>
                <p class="vs-api-list-empty__desc">后续新增的系统级外部接口将出现在此。</p>
            </div>
        </div>
    <?php else: ?>
        <div class="docs-layout">
            <aside class="docs-tree" id="docsTree">
                <button class="docs-tree-toggle" id="docsTreeToggle" type="button" aria-expanded="false">
                    <span class="docs-tree-toggle__text">
                        接口目录<span class="docs-tree-toggle__suffix" id="docsTreeNameSuffix"<?php echo $firstName !== '' ? '' : ' hidden'; ?>>
                            — <span id="docsTreeSelectedName"><?php echo vs_e($firstName); ?></span>
                        </span>
                    </span>
                    <svg class="docs-tree-toggle__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="docs-tree__search">
                    <div class="vs-search-bar__input-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" class="vs-input vs-search-bar__input" id="sysApiSearchInput"
                               placeholder="搜索接口..." autocomplete="off">
                    </div>
                </div>
                <div class="docs-tree__body" id="docsTreeBody">
                    <?php foreach ($grouped as $catName => $items): ?>
                        <div class="docs-tree__group is-open" data-docs-group>
                            <button class="docs-tree__group-btn" type="button">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span><?php echo vs_e($catName); ?></span>
                                <svg class="docs-tree__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                            <div class="docs-tree__sub">
                                <?php foreach ($items as $item): ?>
                                    <button type="button"
                                            class="docs-tree__item<?php echo ((string) $item['id'] === $firstId) ? ' is-active' : ''; ?>"
                                            data-docs-item="<?php echo vs_e($item['id']); ?>"
                                            data-docs-name="<?php echo vs_e($item['name']); ?>"
                                            data-search="<?php echo vs_e($item['search']); ?>">
                                        <?php echo vs_sysapi_method_slash_html($item['methods'], 'docs-tree__slash'); ?>
                                        <span class="docs-tree__item-text"><?php echo vs_e($item['name']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="docs-content" id="docsContent">
                <div class="vs-api-list-empty vs-api-list-empty--hero" id="sysApiSearchEmpty" hidden>
                    <div class="vs-api-list-empty__card">
                        <h3 class="vs-api-list-empty__title">暂无匹配接口</h3>
                        <p class="vs-api-list-empty__desc">当前搜索下没有接口，可清空关键词重试。</p>
                    </div>
                </div>

                <?php foreach ($docsCtx as $item): ?>
                    <div class="doc-panel" data-docs-panel="<?php echo vs_e($item['id']); ?>"
                        <?php echo ((string) $item['id'] === $firstId) ? '' : ' hidden'; ?>>
                        <div class="doc-panel__head">
                            <div class="doc-panel__title-row">
                                <div class="doc-panel__title">
                                    <span class="doc-panel__name"><?php echo vs_e($item['name']); ?></span>
                                </div>
                                <div class="doc-panel__actions">
                                    <button type="button" class="doc-panel__md-btn" data-copy-markdown
                                            aria-label="复制为 Markdown" title="复制整份接口说明为 Markdown">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                        <span>复制为 Markdown</span>
                                    </button>
                                </div>
                            </div>
                            <?php if ($item['desc'] !== ''): ?>
                                <div class="doc-panel__desc"><?php echo vs_e($item['desc']); ?></div>
                            <?php endif; ?>
                            <textarea class="sysapi-md-export" hidden readonly aria-hidden="true"><?php echo vs_e(vs_sysapi_export_markdown($item)); ?></textarea>
                        </div>
                        <div class="doc-panel__body">
                            <div class="endpoint-block">
                                <?php echo vs_sysapi_method_slash_html($item['methods'], 'method-slash'); ?>
                                <span class="endpoint-block__path" data-endpoint="<?php echo vs_e($item['endpoint']); ?>">
                                    <?php echo vs_e($item['endpoint'] !== '' ? $item['endpoint'] : '—'); ?>
                                </span>
                                <?php if ($item['endpoint'] !== ''): ?>
                                    <button type="button" class="endpoint-block__copy" data-copy-endpoint
                                            data-copy="<?php echo vs_e($item['endpoint']); ?>"
                                            aria-label="复制地址" title="复制">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="doc-tabs" role="tablist">
                                <button type="button" class="doc-tabs__btn is-active" data-docs-tab="params">参数说明</button>
                                <button type="button" class="doc-tabs__btn" data-docs-tab="response">响应示例</button>
                                <button type="button" class="doc-tabs__btn" data-docs-tab="request">请求示例</button>
                                <button type="button" class="doc-tabs__btn" data-docs-tab="doc">文档说明</button>
                                <button type="button" class="doc-tabs__btn" data-docs-tab="errcode">失败码</button>
                            </div>

                            <div class="doc-tab-pane is-active" data-docs-pane="params">
                                <div class="doc-section" data-docs-slot="params">
                                    <?php echo vs_sysapi_params_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="response" hidden>
                                <div class="doc-section" data-docs-slot="response">
                                    <?php echo vs_sysapi_response_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="request" hidden>
                                <div class="doc-section" data-docs-slot="request">
                                    <?php echo vs_sysapi_request_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="doc" hidden>
                                <div class="doc-section doc-md-body" data-docs-slot="doc">
                                    <?php echo vs_sysapi_doc_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="errcode" hidden>
                                <div class="doc-section" data-docs-slot="errcode">
                                    <?php echo vs_sysapi_errcode_html($item); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$mdCss = vs_site_path('/core/markdown/assets/css/markdown-render.css');
echo '<link rel="stylesheet" href="' . vs_e($mdCss) . '?v=' . vs_e(VS_VERSION) . '">' . "\n";
vs_admin_layout_end(array('vs-syntax.js', 'sysapi-docs.js'));
