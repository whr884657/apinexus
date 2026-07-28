<?php
/**
 * 文件：admin/api/docs.php
 * 作用：接口文档浏览器（左侧目录树 + 右侧文档面板；文档字段专用编辑弹窗）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'get_docs') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $row = ApiManager::findById($id);
        if (!$row) {
            AjaxResponse::error('接口不存在');
        }
        AjaxResponse::success('ok', array(
            'api_id'   => $id,
            'name'     => isset($row['name']) ? (string) $row['name'] : '',
            'params'   => isset($row['params']) ? (string) $row['params'] : '',
            'response' => isset($row['response']) ? (string) $row['response'] : '',
            'doc'      => isset($row['doc']) ? (string) $row['doc'] : '',
            'aidoc'    => isset($row['aidoc']) ? (string) $row['aidoc'] : '',
        ));
    }

    if ($action === 'save_docs') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $data = array(
            'params'   => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response' => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'      => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'    => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
        );
        $data = vs_decode_transport_fields($data, array('doc', 'aidoc', 'response', 'params'));
        $result = ApiManager::updateDocsContent($id, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiManager::findById($id);
        if (!$row) {
            AjaxResponse::error('接口不存在');
        }
        $ctx = vs_api_docs_ctx($row);
        AjaxResponse::success('文档已保存', array(
            'api_id'         => $id,
            'params_html'    => vs_api_docs_params_html($ctx),
            'response_html'  => vs_api_docs_response_html($ctx),
            'request_html'   => vs_api_docs_request_html($ctx),
            'doc_html'       => vs_api_docs_doc_html($ctx),
            'params'         => isset($row['params']) ? (string) $row['params'] : '',
            'response'       => isset($row['response']) ? (string) $row['response'] : '',
            'doc'            => isset($row['doc']) ? (string) $row['doc'] : '',
            'aidoc'          => isset($row['aidoc']) ? (string) $row['aidoc'] : '',
        ));
    }

    AjaxResponse::error('未知操作');
}

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
 * 生成简单请求示例（cURL / JavaScript）——无 :::qs 时的回退
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
    if ($desc !== '' && function_exists('vs_decode_transport_field')) {
        $desc = trim(vs_decode_transport_field($desc));
    }
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
    $docHtml = $docRaw !== '' ? vs_api_docs_render_md($docRaw) : '';
    $keyways = ApiManager::normalizeKeyways(isset($row['keyways']) ? $row['keyways'] : ApiManager::KEYWAY_QUERY);
    $qsBundle = class_exists('ApiQuickstart')
        ? ApiQuickstart::qsBundleFromAidoc($aidocRaw, $keyways)
        : array('auths' => array(), 'authLabels' => array(), 'byAuth' => array());
    $examples = vs_api_docs_request_examples($endpoint, $methods, $paramsList);
    $apitype = ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : ApiManager::APITYPE_LOCAL);
    $needkey = ApiManager::normalizeRequireKey(isset($row['needkey']) ? $row['needkey'] : 0);
    $qpm = ApiManager::normalizeQpm(isset($row['qpm']) ? $row['qpm'] : 0);
    $charge = ApiManager::normalizeCharge(isset($row['charge']) ? $row['charge'] : 0);
    $price = isset($row['price']) ? $row['price'] : 0;
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
        'qs_bundle'       => $qsBundle,
        'examples'        => $examples,
        'apitype'         => $apitype,
        'apitype_label'   => ApiManager::apiTypeLabel($apitype),
        'needkey'         => $needkey,
        'needkey_label'   => ApiManager::requireKeyLabel($needkey),
        'qpm'             => $qpm,
        'qpm_label'       => ApiManager::qpmLabel($qpm),
        'charge'          => $charge,
        'charge_label'    => ApiManager::chargeLabel($charge),
        'price_label'     => ($charge === ApiManager::CHARGE_PAID)
            ? (class_exists('PayConfig')
                ? (PayConfig::fmtPoints($price) . ' 积分/次')
                : ((string) $price . ' 积分/次'))
            : '',
        'keyways_label'   => ApiManager::keywaysLabel($keyways),
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
 * GET/POST 合并斜线双色标签
 *
 * @param array  $methods
 * @param string $prefix method-slash|docs-tree__slash
 * @return string
 */
function vs_api_docs_method_slash_html(array $methods, $prefix = 'method-slash')
{
    $parts = array();
    foreach ($methods as $m) {
        $m = strtoupper(trim((string) $m));
        if ($m === '') {
            continue;
        }
        $cls = vs_api_docs_method_class($m);
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

/**
 * @param array $item
 * @return string
 */
function vs_api_docs_params_html(array $item)
{
    if (count($item['params_list']) === 0) {
        return '<p class="doc-empty-hint">暂无参数说明</p>';
    }
    $html = '<div class="params-table--wrap"><table class="params-table"><thead><tr>'
        . '<th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr></thead><tbody>';
    foreach ($item['params_list'] as $p) {
        $req = !empty($p['required'])
            ? '<span class="params-table__required params-table__required--yes">是</span>'
            : '<span class="params-table__required params-table__required--no">否</span>';
        $html .= '<tr>'
            . '<td><span class="params-table__name">' . vs_e($p['name']) . '</span></td>'
            . '<td><span class="params-table__type">' . vs_e($p['type']) . '</span></td>'
            . '<td>' . $req . '</td>'
            . '<td class="params-table__desc">' . vs_e($p['description']) . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}

/**
 * @param array $item
 * @return string
 */
function vs_api_docs_response_html(array $item)
{
    if ($item['response_pretty'] === '') {
        return '<p class="doc-empty-hint">暂无响应示例</p>';
    }
    return '<div class="code-block"><div class="code-block__head">'
        . '<span class="code-block__lang">JSON</span></div>'
        . '<pre class="code-block__pre"><code class="language-json" data-vs-syn="json">'
        . vs_e($item['response_pretty']) . '</code></pre></div>';
}

/**
 * 请求示例：绑定 aidoc 的 鉴权×语言；无 qs 时回退简易示例
 *
 * @param array $item
 * @return string
 */
function vs_api_docs_request_html(array $item)
{
    $bundle = isset($item['qs_bundle']) && is_array($item['qs_bundle']) ? $item['qs_bundle'] : array();
    $auths = isset($bundle['auths']) && is_array($bundle['auths']) ? $bundle['auths'] : array();
    $byAuth = isset($bundle['byAuth']) && is_array($bundle['byAuth']) ? $bundle['byAuth'] : array();
    $labels = isset($bundle['authLabels']) && is_array($bundle['authLabels'])
        ? $bundle['authLabels']
        : (class_exists('ApiQuickstart') ? ApiQuickstart::authLabels() : array());

    if ($auths === array()) {
        $html = '<p class="doc-empty-hint">暂无代码示例，以下为根据参数自动生成的简易示例。可在编辑中写入 :::qs 多语言块。</p>';
        $html .= '<div class="code-block"><div class="code-block__head"><span class="code-block__lang">cURL</span></div>'
            . '<pre class="code-block__pre"><code class="language-bash" data-vs-syn="bash">'
            . vs_e($item['examples']['curl']) . '</code></pre></div>';
        $html .= '<div class="code-block"><div class="code-block__head"><span class="code-block__lang">JavaScript</span></div>'
            . '<pre class="code-block__pre"><code class="language-javascript" data-vs-syn="javascript">'
            . vs_e($item['examples']['js']) . '</code></pre></div>';
        return $html;
    }

    $html = '<div class="docs-qs" data-docs-qs>';
    if (count($auths) > 1) {
        $html .= '<div class="docs-qs__auth-tabs" role="tablist" aria-label="鉴权方式">';
        foreach ($auths as $ai => $authId) {
            $lbl = isset($labels[$authId]) ? $labels[$authId] : $authId;
            $active = $ai === 0 ? ' is-active' : '';
            $html .= '<button type="button" class="docs-qs__auth-tab' . $active . '" data-qs-auth="'
                . vs_e($authId) . '" role="tab" aria-selected="' . ($ai === 0 ? 'true' : 'false') . '">'
                . vs_e($lbl) . '</button>';
        }
        $html .= '</div>';
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
            foreach ($samples as $qs) {
                $html .= '<div class="code-block"><div class="code-block__head">'
                    . '<span class="code-block__lang">' . vs_e($qs['label']) . '</span></div>'
                    . '<pre class="code-block__pre"><code class="language-' . vs_e($qs['syn'])
                    . '" data-vs-syn="' . vs_e($qs['syn']) . '">' . vs_e($qs['code']) . '</code></pre></div>';
            }
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param array $item
 * @return string
 */
function vs_api_docs_doc_html(array $item)
{
    if ($item['doc_html'] === '') {
        return '<p class="doc-empty-hint">暂无文档内容</p>';
    }
    return $item['doc_html'];
}

/**
 * @param array $item
 * @return string
 */
function vs_api_docs_meta_badges_html(array $item)
{
    $html = '<span class="vs-badge ' . vs_api_docs_status_badge_class($item['status']) . '">'
        . vs_e($item['status_label']) . '</span>';
    $html .= '<span class="vs-badge vs-badge--default">' . vs_e($item['category']) . '</span>';
    $html .= '<span class="vs-badge vs-badge--default">' . vs_e($item['apitype_label']) . '</span>';
    $html .= '<span class="vs-badge vs-badge--default">' . vs_e($item['needkey_label']) . '</span>';
    $html .= '<span class="vs-badge vs-badge--default">' . vs_e($item['charge_label']) . '</span>';
    if ($item['price_label'] !== '') {
        $html .= '<span class="vs-badge vs-badge--default">' . vs_e($item['price_label']) . '</span>';
    }
    $html .= '<span class="vs-badge vs-badge--default">QPM ' . vs_e($item['qpm_label']) . '</span>';
    if ($item['keyways_label'] !== '') {
        $html .= '<span class="vs-badge vs-badge--default">' . vs_e($item['keyways_label']) . '</span>';
    }
    return $html;
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
$firstName = isset($docsCtx[0]) ? (string) $docsCtx[0]['name'] : '';

vs_admin_layout_start('接口文档', 'api-docs');
?>

<div id="apiDocsPage" data-first-id="<?php echo (int) $firstId; ?>"
     data-first-name="<?php echo vs_e($firstName); ?>">
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
                        <input type="search" class="vs-input vs-search-bar__input" id="apiDocsSearchInput"
                               placeholder="搜索接口..." autocomplete="off">
                    </div>
                </div>
                <div class="docs-tree__body" id="docsTreeBody">
                    <?php foreach ($grouped as $catName => $items): ?>
                        <div class="docs-tree__group" data-docs-group>
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
                                            data-docs-name="<?php echo vs_e($item['name']); ?>"
                                            data-search="<?php echo vs_e($item['search']); ?>">
                                        <?php echo vs_api_docs_method_slash_html($item['methods'], 'docs-tree__slash'); ?>
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
                            <div class="doc-panel__title-row">
                                <div class="doc-panel__title">
                                    <span class="doc-panel__name"><?php echo vs_e($item['name']); ?></span>
                                </div>
                                <button type="button" class="vs-btn vs-btn--default vs-btn--sm"
                                        data-docs-edit="<?php echo (int) $item['id']; ?>">编辑</button>
                            </div>
                            <?php if ($item['desc'] !== ''): ?>
                                <div class="doc-panel__desc"><?php echo vs_e($item['desc']); ?></div>
                            <?php endif; ?>
                            <div class="doc-panel__meta">
                                <?php echo vs_api_docs_meta_badges_html($item); ?>
                            </div>
                        </div>
                        <div class="doc-panel__body">
                            <div class="endpoint-block">
                                <?php echo vs_api_docs_method_slash_html($item['methods'], 'method-slash'); ?>
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
                                <button type="button" class="doc-tabs__btn" data-docs-tab="doc">文档</button>
                            </div>

                            <div class="doc-tab-pane is-active" data-docs-pane="params">
                                <div class="doc-section" data-docs-slot="params">
                                    <?php echo vs_api_docs_params_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="response" hidden>
                                <div class="doc-section" data-docs-slot="response">
                                    <?php echo vs_api_docs_response_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="request" hidden>
                                <div class="doc-section" data-docs-slot="request">
                                    <?php echo vs_api_docs_request_html($item); ?>
                                </div>
                            </div>

                            <div class="doc-tab-pane" data-docs-pane="doc" hidden>
                                <div class="doc-section doc-md-body" data-docs-slot="doc">
                                    <?php echo vs_api_docs_doc_html($item); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vs-overlay vs-overlay--lg" id="apiDocsEditOverlay" hidden aria-hidden="true">
            <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
            <div class="vs-overlay__panel" role="dialog" aria-labelledby="apiDocsEditTitle" aria-modal="true">
                <div class="vs-overlay__handle" aria-hidden="true"></div>
                <header class="vs-overlay__head">
                    <h3 class="vs-overlay__title" id="apiDocsEditTitle">编辑文档</h3>
                    <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
                </header>
                <form id="apiDocsEditForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
                    <input type="hidden" id="apiDocsEditId" name="api_id" value="">
                    <div class="vs-form-row">
                        <label class="vs-label">请求参数</label>
                        <textarea class="vs-input vs-textarea vs-api-list-code" id="apiDocsEditParams" name="params" hidden aria-hidden="true"></textarea>
                        <div class="vs-params-editor" id="apiDocsParamsEditor" data-hidden-id="apiDocsEditParams"></div>
                    </div>
                    <div class="vs-form-row">
                        <label class="vs-label" for="apiDocsEditResponse">响应示例</label>
                        <textarea class="vs-input vs-textarea vs-api-list-code" id="apiDocsEditResponse" name="response" rows="8"
                                  placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
                    </div>
                    <div class="vs-form-row">
                        <label class="vs-label" for="apiDocsEditAidoc">请求示例（:::qs 多语言）</label>
                        <textarea class="vs-input vs-textarea vs-api-list-code" id="apiDocsEditAidoc" name="aidoc" rows="10"
                                  data-vs-md="off" placeholder=":::qs lang=curl&#10;...&#10;:::"></textarea>
                        <p class="vs-form-hint">与前台快速上手一致：:::qs lang=语言 [auth=鉴权]，共 3 类鉴权 × 9 种语言。</p>
                    </div>
                    <div class="vs-form-row">
                        <label class="vs-label" for="apiDocsEditDoc">详细文档（Markdown）</label>
                        <textarea class="vs-input vs-textarea vs-api-list-code" id="apiDocsEditDoc" name="doc" rows="10"
                                  data-vs-md="off" placeholder="面向调用方的详细说明…"></textarea>
                    </div>
                </form>
                <footer class="vs-overlay__foot">
                    <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
                    <button type="submit" class="vs-btn vs-btn--primary" form="apiDocsEditForm" id="apiDocsEditSave">保存</button>
                </footer>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$mdCss = rtrim(vs_base_url(), '/') . '/core/markdown/assets/css/markdown-render.css';
echo '<link rel="stylesheet" href="' . vs_e($mdCss) . '?v=' . vs_e(VS_VERSION) . '">' . "\n";
vs_admin_layout_end(array('api-params-editor.js', 'vs-syntax.js', 'api-docs.js'));
