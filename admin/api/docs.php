<?php
/**
 * 文件：admin/api/docs.php
 * 作用：接口文档浏览器（左侧目录树 + 右侧文档面板）
 */

require_once dirname(__DIR__) . '/init.php';

$tableReady = ApiManager::tableReady();
$apis = $tableReady ? ApiManager::listPublic() : array();

/**
 * 将响应示例美化为可读文本
 *
 * @param string $raw
 * @return string
 */
function vs_api_docs_pretty_response($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    return $raw;
}

/**
 * Markdown 渲染；无 Markdown 类时退回转义纯文本
 *
 * @param string $raw
 * @return string HTML
 */
function vs_api_docs_render_md($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (class_exists('Markdown')) {
        return Markdown::render($raw);
    }
    return '<pre class="doc-md-fallback">' . vs_e($raw) . '</pre>';
}

/**
 * 生成简单请求示例（cURL / JavaScript）
 *
 * @param string $endpoint
 * @param array  $methods
 * @param array  $paramsList
 * @return array{curl:string,js:string}
 */
function vs_api_docs_request_examples($endpoint, array $methods, array $paramsList)
{
    $endpoint = trim((string) $endpoint);
    $method = isset($methods[0]) ? strtoupper((string) $methods[0]) : 'GET';
    if ($method !== 'POST') {
        $method = 'GET';
    }

    $query = array();
    $jsonBody = array();
    foreach ($paramsList as $p) {
        if (!is_array($p)) {
            continue;
        }
        $name = isset($p['name']) ? (string) $p['name'] : '';
        if ($name === '') {
            continue;
        }
        $ex = isset($p['example']) ? (string) $p['example'] : '';
        if ($ex === '') {
            $ex = 'value';
        }
        if ($method === 'GET') {
            $query[$name] = $ex;
        } else {
            $jsonBody[$name] = $ex;
        }
    }

    $url = $endpoint;
    if ($method === 'GET' && $query !== array()) {
        $url .= (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query($query);
    }

    $curl = 'curl -X ' . $method . ' "' . $url . '"';
    if ($method === 'POST') {
        $curl .= " \\\n  -H \"Content-Type: application/json\"";
        if ($jsonBody !== array()) {
            $bodyJson = (string) json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $curl .= " \\\n  -d '" . $bodyJson . "'";
        }
    }

    if ($method === 'GET') {
        $js = "const res = await fetch('" . addslashes($url) . "');\n"
            . "const data = await res.json();\n"
            . 'console.log(data);';
    } else {
        $bodyJson = (string) json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $js = "const res = await fetch('" . addslashes($endpoint) . "', {\n"
            . "  method: 'POST',\n"
            . "  headers: { 'Content-Type': 'application/json' },\n"
            . '  body: JSON.stringify(' . ($jsonBody === array() ? '{}' : $bodyJson) . ")\n"
            . "});\n"
            . "const data = await res.json();\n"
            . 'console.log(data);';
    }

    return array('curl' => $curl, 'js' => $js);
}

/**
 * @param array $row
 * @return array
 */
function vs_api_docs_ctx(array $row)
{
    $id = (int) (isset($row['id']) ? $row['id'] : 0);
    $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
    $desc = trim((string) (isset($row['description']) ? $row['description'] : ''));
    $cat = trim((string) (isset($row['category']) ? $row['category'] : ''));
    if ($cat === '') {
        $cat = '未分类';
    }
    $methods = ApiManager::normalizeMethods(isset($row['method']) ? $row['method'] : 'GET');
    $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : ApiManager::STATUS_NORMAL);
    $endpoint = ApiManager::resolveCallUrl($row);
    if ($endpoint === '') {
        $endpoint = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));
    }
    $paramsList = FrontendApi::parseParamsList(isset($row['params']) ? (string) $row['params'] : '');
    $responsePretty = vs_api_docs_pretty_response(isset($row['response']) ? (string) $row['response'] : '');
    $docRaw = trim((string) (isset($row['doc']) ? $row['doc'] : ''));
    $aidocRaw = trim((string) (isset($row['aidoc']) ? $row['aidoc'] : ''));
    $docHtml = '';
    if ($docRaw !== '') {
        $docHtml = vs_api_docs_render_md($docRaw);
    } elseif ($aidocRaw !== '') {
        $docHtml = vs_api_docs_render_md($aidocRaw);
    }
    $examples = vs_api_docs_request_examples($endpoint, $methods, $paramsList);
    $search = mb_strtolower($name . ' ' . $desc . ' ' . $cat . ' ' . $endpoint, 'UTF-8');

    return array(
        'id'              => $id,
        'name'            => $name,
        'desc'            => $desc,
        'category'        => $cat,
        'methods'         => $methods,
        'status'          => $status,
        'status_label'    => ApiManager::statusLabel($status),
        'endpoint'        => $endpoint,
        'params_list'     => $paramsList,
        'response_pretty' => $responsePretty,
        'doc_html'        => $docHtml,
        'examples'        => $examples,
        'search'          => $search,
    );
}

/**
 * @param string $method
 * @return string
 */
function vs_api_docs_method_class($method)
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
 * @param array $methods
 * @param string $prefix docs-tree__method|method-badge
 * @return string
 */
function vs_api_docs_method_badges_html(array $methods, $prefix)
{
    $html = '';
    foreach ($methods as $m) {
        $m = strtoupper((string) $m);
        if ($m === '') {
            continue;
        }
        $cls = vs_api_docs_method_class($m);
        $html .= '<span class="' . vs_e($prefix) . ' ' . vs_e($prefix) . '--' . vs_e($cls) . '">' . vs_e($m) . '</span>';
    }
    return $html;
}

/**
 * @param int $status
 * @return string
 */
function vs_api_docs_status_badge_class($status)
{
    $status = ApiManager::normalizeStatus($status);
    if ($status === ApiManager::STATUS_DISABLED) {
        return 'vs-badge--error';
    }
    if ($status === ApiManager::STATUS_MAINTENANCE) {
        return 'vs-badge--warning';
    }
    return 'vs-badge--success';
}

$grouped = array();
$docsCtx = array();
foreach ($apis as $row) {
    if (!is_array($row)) {
        continue;
    }
    $ctx = vs_api_docs_ctx($row);
    if ($ctx['id'] <= 0 || $ctx['name'] === '') {
        continue;
    }
    $docsCtx[] = $ctx;
    $cat = $ctx['category'];
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = array();
    }
    $grouped[$cat][] = $ctx;
}
ksort($grouped, SORT_STRING);
if (isset($grouped['未分类'])) {
    $uncat = $grouped['未分类'];
    unset($grouped['未分类']);
    $grouped['未分类'] = $uncat;
}

$firstId = isset($docsCtx[0]) ? (int) $docsCtx[0]['id'] : 0;

vs_admin_layout_start('接口文档', 'api-docs');
?>

<div id="apiDocsPage" data-first-id="<?php echo (int) $firstId; ?>">
    <?php if (!$tableReady): ?>
        <div class="vs-panel">
            <?php vs_render_notice('warning', '', '接口表尚未就绪，请先执行数据库结构更新。', array('compact' => true)); ?>
        </div>
    <?php elseif (count($docsCtx) === 0): ?>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiDocsEmpty">
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无可用接口</h3>
                <p class="vs-api-list-empty__desc">审核通过且可用的接口将出现在此文档中心。</p>
            </div>
        </div>
    <?php else: ?>
        <div class="docs-layout">
            <aside class="docs-tree" id="docsTree">
                <button class="docs-tree-toggle" id="docsTreeToggle" type="button">
                    <span>接口目录</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="docs-tree__search">
                    <div class="vs-search-bar__input-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" class="vs-input vs-search-bar__input" id="apiDocsSearchInput"
                               placeholder="搜索接口..." autocomplete="off">
                    </div>
                </div>
                <div class="docs-tree__body" id="docsTreeBody">
                    <?php
                    $groupIdx = 0;
                    foreach ($grouped as $catName => $items):
                        $isOpen = $groupIdx < 2;
                        $groupIdx++;
                        ?>
                        <div class="docs-tree__group<?php echo $isOpen ? ' is-open' : ''; ?>" data-docs-group>
                            <button class="docs-tree__group-btn" type="button">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span><?php echo vs_e($catName); ?></span>
                                <svg class="docs-tree__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                            <div class="docs-tree__sub">
                                <?php foreach ($items as $item): ?>
                                    <button type="button"
                                            class="docs-tree__item<?php echo ((int) $item['id'] === $firstId) ? ' is-active' : ''; ?>"
                                            data-docs-item="<?php echo (int) $item['id']; ?>"
                                            data-search="<?php echo vs_e($item['search']); ?>">
                                        <?php echo vs_api_docs_method_badges_html($item['methods'], 'docs-tree__method'); ?>
                                        <span class="docs-tree__item-text"><?php echo vs_e($item['name']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="docs-content" id="docsContent">
                <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiDocsSearchEmpty" hidden>
                    <div class="vs-api-list-empty__card">
                        <h3 class="vs-api-list-empty__title">暂无匹配接口</h3>
                        <p class="vs-api-list-empty__desc">当前搜索下没有接口，可清空关键词重试。</p>
                    </div>
                </div>

                <?php foreach ($docsCtx as $item): ?>
                    <div class="doc-panel" data-docs-panel="<?php echo (int) $item['id']; ?>"
                        <?php echo ((int) $item['id'] === $firstId) ? '' : ' hidden'; ?>>
                        <div class="doc-panel__head">
                            <div class="doc-panel__title">
                                <?php echo vs_api_docs_method_badges_html($item['methods'], 'method-badge'); ?>
                                <span class="doc-panel__name"><?php echo vs_e($item['name']); ?></span>
                            </div>
                            <?php if ($item['desc'] !== ''): ?>
                                <div class="doc-panel__desc"><?php echo vs_e($item['desc']); ?></div>
                            <?php endif; ?>
                            <div class="doc-panel__meta">
                                <span class="vs-badge <?php echo vs_api_docs_status_badge_class($item['status']); ?>">
                                    <?php echo vs_e($item['status_label']); ?>
                                </span>
                                <span class="vs-badge vs-badge--default"><?php echo vs_e($item['category']); ?></span>
                            </div>
                        </div>
                        <div class="doc-panel__body">
                            <div class="endpoint-block">
                                <?php echo vs_api_docs_method_badges_html($item['methods'], 'method-badge'); ?>
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
                                <button type="button" class="doc-tabs__btn" data-docs-tab="request">请求示例</button>
                                <button type="button" class="doc-tabs__btn" data-docs-tab="response">响应示例</button>
                                <button type="button" class="doc-tabs__btn" data-docs-tab="doc">文档</button>
                            </div>

                            <div class="doc-tab-pane is-active" data-docs-pane="params">
                                <div class="doc-section">
                                    <?php if (count($item['params_list']) === 0): ?>
                                        <p class="doc-empty-hint">暂无参数说明</p>
                                    <?php else: ?>
                                        <div class="params-table--wrap">
                                            <table class="params-table">
                                                <thead>
                                                    <tr>
                                                        <th>参数名</th>
                                                        <th>类型</th>
                                                        <th>必填</th>
                                                        <th>说明</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($item['params_list'] as $p): ?>
                                                        <tr>
                                                            <td><span class="params-table__name"><?php echo vs_e($p['name']); ?></span></td>
                                                            <td><span class="params-table__type"><?php echo vs_e($p['type']); ?></span></td>
                                                            <td>
                                                                <?php if (!empty($p['required'])): ?>
                                                                    <span class="params-table__required params-table__required--yes">是</span>
                                                                <?php else: ?>
                                                                    <span class="params-table__required params-table__required--no">否</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="params-table__desc"><?php echo vs_e($p['description']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="request" hidden>
                                <div class="doc-section">
                                    <div class="code-block">
                                        <div class="code-block__head">
                                            <span class="code-block__lang">cURL</span>
                                        </div>
                                        <pre class="code-block__pre"><code><?php echo vs_e($item['examples']['curl']); ?></code></pre>
                                    </div>
                                    <div class="code-block">
                                        <div class="code-block__head">
                                            <span class="code-block__lang">JavaScript</span>
                                        </div>
                                        <pre class="code-block__pre"><code><?php echo vs_e($item['examples']['js']); ?></code></pre>
                                    </div>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="response" hidden>
                                <div class="doc-section">
                                    <?php if ($item['response_pretty'] === ''): ?>
                                        <p class="doc-empty-hint">暂无响应示例</p>
                                    <?php else: ?>
                                        <div class="code-block">
                                            <div class="code-block__head">
                                                <span class="code-block__lang">JSON</span>
                                            </div>
                                            <pre class="code-block__pre"><code><?php echo vs_e($item['response_pretty']); ?></code></pre>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="doc" hidden>
                                <div class="doc-section doc-md-body">
                                    <?php if ($item['doc_html'] === ''): ?>
                                        <p class="doc-empty-hint">暂无文档内容</p>
                                    <?php else: ?>
                                        <?php echo $item['doc_html']; ?>
                                    <?php endif; ?>
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
vs_admin_layout_end(array('api-docs.js'));
