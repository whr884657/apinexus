<?php
/**
 * 主题4：前台接口文档。结构、图标、标签色、代码高亮对齐管理端接口文档，没有编辑按钮。
 * 手机端目录从左边滑出。不输出底部栏。
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!function_exists('docs4_pretty')) {
    /**
     * @param string $raw
     * @return string
     */
    function docs4_pretty(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        return $raw;
    }
}

if (!function_exists('docs4_method_class')) {
    /**
     * @param string $method
     * @return string
     */
    function docs4_method_class(string $method)
    {
        $m = strtolower($method);
        if ($m === 'post' || $m === 'put' || $m === 'delete') {
            return $m;
        }
        return 'get';
    }
}

if (!function_exists('docs4_method_slash_html')) {
    /**
     * @param array<int,string> $methods
     * @param string $prefix
     * @return string
     */
    function docs4_method_slash_html(array $methods, string $prefix = 'method-slash')
    {
        $parts = array();
        foreach ($methods as $m) {
            $m = strtoupper(trim((string) $m));
            if ($m === '') {
                continue;
            }
            $cls = docs4_method_class($m);
            $parts[] = '<span class="' . vs_e($prefix) . '__part ' . vs_e($prefix) . '__part--' . vs_e($cls) . '">'
                . vs_e($m) . '</span>';
        }
        if ($parts === array()) {
            return '';
        }
        $sep = '<span class="' . vs_e($prefix) . '__sep" aria-hidden="true">/</span>';
        return '<span class="' . vs_e($prefix) . '">' . implode($sep, $parts) . '</span>';
    }
}

if (!function_exists('docs4_params_html')) {
    /**
     * @param array<int,array<string,mixed>> $paramsList
     * @return string
     */
    function docs4_params_html(array $paramsList)
    {
        if (count($paramsList) === 0) {
            return '<p class="doc-empty-hint">暂无参数说明</p>';
        }
        $html = '<div class="params-table--wrap"><table class="params-table"><thead><tr>'
            . '<th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr></thead><tbody>';
        foreach ($paramsList as $p) {
            if (!is_array($p)) {
                continue;
            }
            $req = !empty($p['required'])
                ? '<span class="params-table__required params-table__required--yes">是</span>'
                : '<span class="params-table__required params-table__required--no">否</span>';
            $html .= '<tr>'
                . '<td><span class="params-table__name">' . vs_e(isset($p['name']) ? (string) $p['name'] : '') . '</span></td>'
                . '<td><span class="params-table__type">' . vs_e(isset($p['type']) ? (string) $p['type'] : '') . '</span></td>'
                . '<td>' . $req . '</td>'
                . '<td class="params-table__desc">' . vs_e(isset($p['description']) ? (string) $p['description'] : '') . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }
}

if (!function_exists('docs4_request_examples')) {
    /**
     * @param string $endpoint
     * @param array<int,string> $methods
     * @param array<int,array<string,mixed>> $paramsList
     * @return array{curl:string,js:string}
     */
    function docs4_request_examples(string $endpoint, array $methods, array $paramsList)
    {
        $endpoint = trim($endpoint);
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
}

if (!function_exists('docs4_request_html')) {
    /**
     * @param array<string,mixed> $ctx
     * @return string
     */
    function docs4_request_html(array $ctx)
    {
        $bundle = isset($ctx['qs_bundle']) && is_array($ctx['qs_bundle']) ? $ctx['qs_bundle'] : array();
        $auths = isset($bundle['auths']) && is_array($bundle['auths']) ? $bundle['auths'] : array();
        $byAuth = isset($bundle['byAuth']) && is_array($bundle['byAuth']) ? $bundle['byAuth'] : array();
        $labels = isset($bundle['authLabels']) && is_array($bundle['authLabels'])
            ? $bundle['authLabels']
            : (class_exists('ApiQuickstart') ? ApiQuickstart::authLabels() : array());
        $examples = isset($ctx['examples']) && is_array($ctx['examples']) ? $ctx['examples'] : array('curl' => '', 'js' => '');
        if ($auths === array()) {
            $html = '<p class="doc-empty-hint">暂无代码示例，以下为根据参数自动生成的简易示例。</p>';
            $html .= '<div class="code-block"><div class="code-block__head"><span class="code-block__lang">cURL</span></div>'
                . '<pre class="code-block__pre"><code class="language-bash" data-vs-syn="bash">'
                . vs_e(isset($examples['curl']) ? (string) $examples['curl'] : '') . '</code></pre></div>';
            $html .= '<div class="code-block"><div class="code-block__head"><span class="code-block__lang">JavaScript</span></div>'
                . '<pre class="code-block__pre"><code class="language-javascript" data-vs-syn="javascript">'
                . vs_e(isset($examples['js']) ? (string) $examples['js'] : '') . '</code></pre></div>';
            return $html;
        }
        $html = '<div class="docs-qs" data-docs-qs>';
        if (count($auths) > 1) {
            $html .= '<div class="docs-qs__auth-tabs" role="tablist" aria-label="鉴权方式">';
            foreach ($auths as $ai => $authId) {
                $lbl = isset($labels[$authId]) ? $labels[$authId] : $authId;
                $active = $ai === 0 ? ' is-active' : '';
                $html .= '<button type="button" class="docs-qs__auth-tab' . $active . '" data-qs-auth="'
                    . vs_e((string) $authId) . '" role="tab" aria-selected="' . ($ai === 0 ? 'true' : 'false') . '">'
                    . vs_e((string) $lbl) . '</button>';
            }
            $html .= '</div>';
        }
        foreach ($auths as $ai => $authId) {
            $samples = isset($byAuth[$authId]) && is_array($byAuth[$authId]) ? $byAuth[$authId] : array();
            $paneHidden = $ai === 0 ? '' : ' hidden';
            $paneActive = $ai === 0 ? ' is-active' : '';
            $html .= '<div class="docs-qs__auth-pane' . $paneActive . '" data-qs-auth-pane="'
                . vs_e((string) $authId) . '"' . $paneHidden . '>';
            if ($samples === array()) {
                $html .= '<p class="doc-empty-hint">该鉴权方式暂无示例</p>';
            } else {
                foreach ($samples as $qs) {
                    if (!is_array($qs)) {
                        continue;
                    }
                    $html .= '<div class="code-block"><div class="code-block__head">'
                        . '<span class="code-block__lang">' . vs_e(isset($qs['label']) ? (string) $qs['label'] : '') . '</span></div>'
                        . '<pre class="code-block__pre"><code class="language-' . vs_e(isset($qs['syn']) ? (string) $qs['syn'] : '')
                        . '" data-vs-syn="' . vs_e(isset($qs['syn']) ? (string) $qs['syn'] : '') . '">'
                        . vs_e(isset($qs['code']) ? (string) $qs['code'] : '') . '</code></pre></div>';
                }
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('docs4_badges_html')) {
    /**
     * @param array<string,mixed> $ctx
     * @return string
     */
    function docs4_badges_html(array $ctx)
    {
        $html = '<span class="vs-badge ' . vs_e((string) $ctx['status_class']) . '">' . vs_e((string) $ctx['status_label']) . '</span>';
        if ((string) $ctx['category'] !== '') {
            $html .= '<span class="vs-badge vs-badge--default">' . vs_e((string) $ctx['category']) . '</span>';
        }
        $typeClass = ((int) $ctx['apitype'] === ApiManager::APITYPE_PROXY) ? 'type-badge--proxy' : 'type-badge--local';
        $html .= '<span class="type-badge ' . $typeClass . '">' . vs_e((string) $ctx['apitype_badge']) . '</span>';
        $needkey = (int) $ctx['needkey'];
        $keyBadge = (string) $ctx['needkey_badge'];
        if ($needkey === ApiManager::KEY_NONE) {
            $noneLabel = $keyBadge !== '' ? $keyBadge : '无需 KEY';
            $html .= '<span class="key-badge key-badge--none">' . vs_e($noneLabel) . '</span>';
        } elseif ($needkey === ApiManager::KEY_REQUIRED) {
            $html .= '<span class="key-badge key-badge--required">' . vs_e($keyBadge) . '</span>';
        } else {
            $html .= '<span class="key-badge key-badge--optional">' . vs_e($keyBadge) . '</span>';
        }
        if ((int) $ctx['charge'] === ApiManager::CHARGE_PAID && (string) $ctx['price_label'] !== '') {
            $html .= '<span class="charge-badge charge-badge--points">' . vs_e((string) $ctx['price_label']) . '</span>';
        } else {
            $free = (string) $ctx['charge_label'] !== '' ? (string) $ctx['charge_label'] : '免费';
            $html .= '<span class="charge-badge charge-badge--free">' . vs_e($free) . '</span>';
        }
        $qpm = (int) $ctx['qpm'];
        if ($qpm > 0) {
            $html .= '<span class="qpm-badge qpm-badge--limit">QPM ' . vs_e((string) $ctx['qpm_label']) . '</span>';
        } else {
            $html .= '<span class="vs-badge vs-badge--default">QPM ' . vs_e((string) $ctx['qpm_label']) . '</span>';
        }
        if ((string) $ctx['keyways_label'] !== '') {
            $html .= '<span class="vs-badge vs-badge--default">' . vs_e((string) $ctx['keyways_label']) . '</span>';
        }
        return $html;
    }
}

if (!function_exists('docs4_markdown')) {
    /**
     * @param array<string,mixed> $ctx
     * @return string
     */
    function docs4_markdown(array $ctx)
    {
        $parts = array();
        $parts[] = '# ' . (isset($ctx['name']) ? (string) $ctx['name'] : '接口文档');
        if (!empty($ctx['category'])) {
            $parts[] = '**分类：** ' . (string) $ctx['category'];
        }
        if (!empty($ctx['desc'])) {
            $parts[] = trim((string) $ctx['desc']);
        }
        $methods = isset($ctx['methods']) && is_array($ctx['methods']) ? $ctx['methods'] : array();
        $methodLabel = $methods !== array() ? implode('/', $methods) : 'GET';
        $charge = isset($ctx['charge_label']) ? (string) $ctx['charge_label'] : '免费';
        if (!empty($ctx['price_label'])) {
            $charge = (string) $ctx['price_label'];
        }
        $keyLabel = isset($ctx['needkey_label']) ? (string) $ctx['needkey_label'] : '';
        if ($keyLabel === '' && !empty($ctx['needkey_badge'])) {
            $keyLabel = (string) $ctx['needkey_badge'];
        }
        if ($keyLabel === '') {
            $keyLabel = '无需 KEY';
        }
        $endpoint = isset($ctx['endpoint']) ? (string) $ctx['endpoint'] : '';
        if (!empty($ctx['disabled'])) {
            $endpoint = '（已禁用，地址已隐藏）';
        }
        $detailUrl = function_exists('vs_api_detail_url') ? vs_api_detail_url((int) $ctx['id']) : '';
        $parts[] = "## 接口信息\n\n"
            . '**方法：** ' . $methodLabel . "\n"
            . '**路径 / 完整地址：** ' . $endpoint . "\n"
            . '**状态：** ' . (isset($ctx['status_label']) ? (string) $ctx['status_label'] : '') . "\n"
            . '**计费：** ' . $charge . "\n"
            . '**KEY：** ' . $keyLabel . "\n"
            . '**鉴权方式：** ' . (!empty($ctx['keyways_label']) ? (string) $ctx['keyways_label'] : '—') . "\n"
            . '**QPM：** ' . (isset($ctx['qpm_label']) ? (string) $ctx['qpm_label'] : '不限')
            . ($detailUrl !== '' ? ("\n" . '**文档页：** ' . $detailUrl) : '');
        $paramsList = isset($ctx['params_list']) && is_array($ctx['params_list']) ? $ctx['params_list'] : array();
        if (count($paramsList) > 0) {
            $tbl = "## 请求参数\n\n| 参数名 | 类型 | 必填 | 说明 | 示例 |\n| --- | --- | --- | --- | --- |";
            foreach ($paramsList as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $tbl .= "\n| `" . str_replace('|', '\\|', (string) (isset($p['name']) ? $p['name'] : '')) . '` | '
                    . str_replace('|', '\\|', (string) (isset($p['type']) ? $p['type'] : '')) . ' | '
                    . (!empty($p['required']) ? '是' : '否') . ' | '
                    . str_replace('|', '\\|', (string) (isset($p['description']) ? $p['description'] : '')) . ' | '
                    . '`' . str_replace(array('|', '`'), array('\\|', ''), (string) (isset($p['example']) ? $p['example'] : '')) . '` |';
            }
            $parts[] = $tbl;
        }
        if (!empty($ctx['response_pretty'])) {
            $parts[] = "## 返回示例\n\n```json\n" . trim((string) $ctx['response_pretty']) . "\n```";
        }
        if (!empty($ctx['doc_raw'])) {
            $parts[] = "## 详细文档\n\n" . trim((string) $ctx['doc_raw']);
        }
        return implode("\n\n", $parts);
    }
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$playground = (isset($playground) && is_array($playground)) ? $playground : (function_exists('vs_playground_session_context') ? vs_playground_session_context() : array());
$notFound = !empty($notFound);
$selectedId = 0;
if (isset($api) && is_array($api) && isset($api['id'])) {
    $selectedId = (int) $api['id'];
}

$list = class_exists('FrontendApi') ? FrontendApi::listForTheme() : array();
if (!is_array($list)) {
    $list = array();
}

$docsCtx = array();
$grouped = array();
foreach ($list as $item) {
    if (!is_array($item) || empty($item['id']) || empty($item['name'])) {
        continue;
    }
    $cat = isset($item['category_name']) ? trim((string) $item['category_name']) : '';
    if ($cat === '') {
        $cat = '未分类';
    }
    $methods = isset($item['methods']) && is_array($item['methods']) ? $item['methods'] : array('GET');
    $paramsList = isset($item['params_list']) && is_array($item['params_list']) ? $item['params_list'] : array();
    $endpoint = isset($item['endpoint']) ? (string) $item['endpoint'] : '';
    $disabled = !empty($item['disabled']);
    $maintenance = !empty($item['maintenance']);
    if ($disabled) {
        $status = ApiManager::STATUS_DISABLED;
        $statusClass = 'vs-badge--error';
    } elseif ($maintenance) {
        $status = ApiManager::STATUS_MAINTENANCE;
        $statusClass = 'vs-badge--warning';
    } else {
        $status = ApiManager::STATUS_NORMAL;
        $statusClass = 'vs-badge--success';
    }
    $needkey = isset($item['needkey']) ? (int) $item['needkey'] : 0;
    $charge = isset($item['charge']) ? (int) $item['charge'] : 0;
    $points = isset($item['points']) ? $item['points'] : 0;
    $priceLabel = '';
    if ($charge === ApiManager::CHARGE_PAID) {
        $priceLabel = class_exists('PayConfig')
            ? (PayConfig::fmtPoints($points) . ' 积分/次')
            : (isset($item['billing_label']) ? (string) $item['billing_label'] : '收费');
    }
    $aidoc = isset($item['aidoc']) ? (string) $item['aidoc'] : '';
    $keyways = isset($item['keyways']) && is_array($item['keyways']) ? $item['keyways'] : array();
    $docRaw = isset($item['doc']) ? trim((string) $item['doc']) : '';
    $docHtml = '';
    if ($docRaw !== '') {
        $docHtml = class_exists('Markdown') ? Markdown::render($docRaw) : ('<pre class="doc-md-fallback">' . vs_e($docRaw) . '</pre>');
    }
    $desc = isset($item['desc']) ? trim((string) $item['desc']) : '';
    $name = (string) $item['name'];
    $search = function_exists('mb_strtolower')
        ? mb_strtolower($name . ' ' . $desc . ' ' . $cat . ' ' . $endpoint, 'UTF-8')
        : strtolower($name . ' ' . $desc . ' ' . $cat . ' ' . $endpoint);
    $ctx = array(
        'id' => (int) $item['id'],
        'name' => $name,
        'desc' => $desc,
        'category' => $cat,
        'methods' => $methods,
        'endpoint' => $endpoint,
        'params_list' => $paramsList,
        'response_pretty' => docs4_pretty(isset($item['response']) ? (string) $item['response'] : ''),
        'doc_html' => $docHtml,
        'status_label' => ApiManager::statusLabel($status),
        'status_class' => $statusClass,
        'apitype' => isset($item['apitype']) ? (int) $item['apitype'] : 0,
        'apitype_badge' => ApiManager::apiTypeBadge(isset($item['apitype']) ? $item['apitype'] : 0),
        'needkey' => $needkey,
        'needkey_badge' => ApiManager::requireKeyBadge($needkey),
        'needkey_label' => isset($item['needkey_label']) ? (string) $item['needkey_label'] : '',
        'charge' => $charge,
        'charge_label' => isset($item['charge_label']) ? (string) $item['charge_label'] : '免费',
        'price_label' => $priceLabel,
        'qpm' => isset($item['qpm']) ? (int) $item['qpm'] : 0,
        'qpm_label' => isset($item['qpm_label']) ? (string) $item['qpm_label'] : '不限',
        'keyways' => $keyways,
        'keyways_label' => isset($item['keyways_label']) ? (string) $item['keyways_label'] : '',
        'search' => $search,
        'disabled' => $disabled ? 1 : 0,
        'maintenance' => $maintenance ? 1 : 0,
        'qs_bundle' => class_exists('ApiQuickstart') ? ApiQuickstart::qsBundleFromAidoc($aidoc, $keyways) : array(),
        'examples' => docs4_request_examples($endpoint, $methods, $paramsList),
        'doc_raw' => $docRaw,
    );
    $ctx['markdown'] = docs4_markdown($ctx);
    $docsCtx[] = $ctx;
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = array();
    }
    $grouped[$cat][] = $ctx;
}
if (function_exists('ksort')) {
    ksort($grouped, SORT_STRING);
}
if (isset($grouped['未分类'])) {
    $uncat = $grouped['未分类'];
    unset($grouped['未分类']);
    $grouped['未分类'] = $uncat;
}

$byId = array();
foreach ($docsCtx as $ctx) {
    $byId[(int) $ctx['id']] = $ctx;
}
if ($selectedId <= 0 || !isset($byId[$selectedId])) {
    $selectedId = isset($docsCtx[0]) ? (int) $docsCtx[0]['id'] : 0;
}
$firstName = isset($byId[$selectedId]) ? (string) $byId[$selectedId]['name'] : '';

$showAnnounce = ThemeManager::themeSettingBool('show_home_announce', true);
$announceList = ($showAnnounce && class_exists('FrontendAnnouncement')) ? FrontendAnnouncement::listForTheme() : array();
$announcePopup = ($showAnnounce && class_exists('FrontendAnnouncement')) ? FrontendAnnouncement::listPopups() : array();
$hasAnnounce = is_array($announceList) && count($announceList) > 0;
$announceMarquee = '';
$announceTitle = '公告';
$announceHtml = '';
$announcePopupKey = '';
if ($hasAnnounce) {
    $firstAnn = $announceList[0];
    $announceMarquee = isset($firstAnn['preview']) && $firstAnn['preview'] !== '' ? $firstAnn['preview'] : $firstAnn['title'];
    $announceTitle = $firstAnn['title'];
    $announceHtml = isset($firstAnn['body_html']) ? (string) $firstAnn['body_html'] : '';
}
if ($hasAnnounce && is_array($announcePopup) && count($announcePopup) > 0) {
    $pop = $announcePopup[0];
    $announceTitle = $pop['title'];
    if (isset($pop['body_html'])) {
        $announceHtml = (string) $pop['body_html'];
    }
    if (isset($pop['preview']) && $pop['preview'] !== '') {
        $announceMarquee = $pop['preview'];
    }
    $ids = array();
    foreach ($announcePopup as $p) {
        if (isset($p['id'])) {
            $ids[] = (int) $p['id'];
        }
    }
    sort($ids);
    $announcePopupKey = implode('-', $ids);
}

$assetBase = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
$mdCss = $assetBase . '/core/markdown/assets/css/markdown-render.css';
?>
<link rel="stylesheet" href="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/css/docs-browser.css')); ?>?v=<?php echo vs_e(VS_VERSION); ?>">
<link rel="stylesheet" href="<?php echo vs_e($mdCss); ?>?v=<?php echo vs_e(VS_VERSION); ?>">

<?php if ($hasAnnounce): ?>
<div class="home-announcement-bundle">
<section class="home-announcement-wrap home-announcement-wrap--ready" id="homeAnnouncementWrap">
    <button type="button" class="home-announcement-bar" id="homeAnnouncementBtn" aria-label="查看公告详情">
        <span class="home-announcement-label">公告</span>
        <span class="home-announcement-marquee"><span class="home-announcement-track is-ready"><?php echo vs_e($announceMarquee); ?></span></span>
        <span class="home-announcement-action">点击查看</span>
    </button>
</section>
<script type="application/json" id="feer-announcement-client-data"><?php echo json_encode(array(
    'home' => array(
        'title' => $announceTitle,
        'html' => $announceHtml,
        'autopopup' => is_array($announcePopup) && count($announcePopup) > 0,
        'popup_key' => $announcePopupKey,
    ),
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
<div class="home-announcement-modal" id="homeAnnouncementModal" data-modal-kind="home" aria-hidden="true" inert>
    <div class="home-announcement-modal__mask" data-close-announcement="1"></div>
    <div class="home-announcement-modal__card" role="dialog" aria-modal="true">
        <div class="home-announcement-modal__head"><h3 class="home-announcement-modal__title"><?php echo vs_e($announceTitle); ?></h3><button type="button" class="home-announcement-modal__close" data-close-announcement="1">关闭</button></div>
        <div class="home-announcement-modal__body markdown-body" data-announcement-body="home"></div>
        <div class="home-announcement-modal__footer">
            <button type="button" class="home-announcement-btn-secondary" data-announcement-dismiss="1">不再提示</button>
            <button type="button" class="home-announcement-btn-ok" data-close-announcement="1">我知道了</button>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<div id="apiDocsPage" data-first-id="<?php echo (int) $selectedId; ?>" data-first-name="<?php echo vs_e($firstName); ?>">
<?php if ($notFound): ?>
    <p class="doc-empty-hint">该接口不存在或已下架。</p>
<?php endif; ?>
<?php if (count($docsCtx) === 0): ?>
    <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiDocsEmpty">
        <div class="vs-api-list-empty__card">
            <h3 class="vs-api-list-empty__title">暂无可用接口</h3>
            <p class="vs-api-list-empty__desc">审核通过且可用的接口将出现在此文档中心。</p>
        </div>
    </div>
<?php else: ?>
    <button type="button" class="docs4-open" id="docs4Open" aria-expanded="false">接口目录</button>
    <div class="docs4-mask" id="docs4Mask"></div>
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
                    <input type="search" class="vs-input vs-search-bar__input" id="apiDocsSearchInput" placeholder="搜索接口..." autocomplete="off">
                </div>
            </div>
            <div class="docs-tree__body" id="docsTreeBody">
                <?php foreach ($grouped as $catName => $items): ?>
                    <?php
                    $groupOpen = false;
                    foreach ($items as $it) {
                        if ((int) $it['id'] === $selectedId) {
                            $groupOpen = true;
                            break;
                        }
                    }
                    ?>
                    <div class="docs-tree__group<?php echo $groupOpen ? ' is-open' : ''; ?>" data-docs-group>
                        <button class="docs-tree__group-btn" type="button">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            <span><?php echo vs_e($catName); ?></span>
                            <svg class="docs-tree__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                        <div class="docs-tree__sub">
                            <?php foreach ($items as $item): ?>
                                <button type="button"
                                        class="docs-tree__item<?php echo ((int) $item['id'] === $selectedId) ? ' is-active' : ''; ?>"
                                        data-docs-item="<?php echo (int) $item['id']; ?>"
                                        data-docs-name="<?php echo vs_e($item['name']); ?>"
                                        data-search="<?php echo vs_e($item['search']); ?>">
                                    <?php echo docs4_method_slash_html($item['methods'], 'docs-tree__slash'); ?>
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
                <div class="doc-panel" data-docs-panel="<?php echo (int) $item['id']; ?>"<?php echo ((int) $item['id'] === $selectedId) ? '' : ' hidden'; ?>>
                    <div class="doc-panel__head">
                        <div class="doc-panel__title-row">
                            <div class="doc-panel__title">
                                <span class="doc-panel__name"><?php echo vs_e($item['name']); ?></span>
                            </div>
                            <button type="button" class="docs4-md-copy" data-docs-copy-md title="复制整页为 Markdown">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span>复制 Markdown</span>
                            </button>
                        </div>
                        <script type="application/json" class="d4-md-data"><?php echo json_encode(isset($item['markdown']) ? (string) $item['markdown'] : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
                        <?php if ($item['desc'] !== ''): ?>
                            <div class="doc-panel__desc"><?php echo vs_e($item['desc']); ?></div>
                        <?php endif; ?>
                        <div class="doc-panel__meta">
                            <?php echo docs4_badges_html($item); ?>
                        </div>
                    </div>
                    <div class="doc-panel__body">
                        <div class="endpoint-block">
                            <?php echo docs4_method_slash_html($item['methods'], 'method-slash'); ?>
                            <span class="endpoint-block__path"><?php echo vs_e($item['endpoint'] !== '' ? $item['endpoint'] : '—'); ?></span>
                            <?php if ($item['endpoint'] !== ''): ?>
                                <button type="button" class="endpoint-block__copy" data-copy-endpoint data-copy="<?php echo vs_e($item['endpoint']); ?>" aria-label="复制地址" title="复制">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="doc-tabs" role="tablist">
                            <button type="button" class="doc-tabs__btn is-active" data-docs-tab="params">参数说明</button>
                            <button type="button" class="doc-tabs__btn" data-docs-tab="response">响应示例</button>
                            <button type="button" class="doc-tabs__btn" data-docs-tab="request">请求示例</button>
                            <button type="button" class="doc-tabs__btn" data-docs-tab="doc">文档</button>
                            <button type="button" class="doc-tabs__btn" data-docs-tab="play">在线测试</button>
                        </div>
                        <div class="doc-tab-pane is-active" data-docs-pane="params">
                            <div class="doc-section" data-docs-slot="params"><?php echo docs4_params_html($item['params_list']); ?></div>
                        </div>
                        <div class="doc-tab-pane" data-docs-pane="response" hidden>
                            <div class="doc-section" data-docs-slot="response">
                                <?php if ($item['response_pretty'] === ''): ?>
                                    <p class="doc-empty-hint">暂无响应示例</p>
                                <?php else: ?>
                                    <div class="code-block"><div class="code-block__head"><span class="code-block__lang">JSON</span></div>
                                    <pre class="code-block__pre"><code class="language-json" data-vs-syn="json"><?php echo vs_e($item['response_pretty']); ?></code></pre></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="doc-tab-pane" data-docs-pane="request" hidden>
                            <div class="doc-section" data-docs-slot="request"><?php echo docs4_request_html($item); ?></div>
                        </div>
                        <div class="doc-tab-pane" data-docs-pane="doc" hidden>
                            <div class="doc-section doc-md-body" data-docs-slot="doc">
                                <?php echo $item['doc_html'] !== '' ? $item['doc_html'] : '<p class="doc-empty-hint">暂无文档内容</p>'; ?>
                            </div>
                        </div>
                        <div class="doc-tab-pane" data-docs-pane="play" hidden>
                            <div class="doc-section" data-docs-slot="play"></div>
                            <script type="application/json" class="d4-play-data"><?php echo json_encode(array(
                                'id' => (int) $item['id'],
                                'name' => $item['name'],
                                'endpoint' => $item['endpoint'],
                                'endpointShow' => $item['endpoint'] !== '' ? $item['endpoint'] : '—',
                                'methods' => $item['methods'],
                                'needkey' => (int) $item['needkey'],
                                'keyways' => $item['keyways'],
                                'maintenance' => (int) $item['maintenance'],
                                'disabled' => (int) $item['disabled'],
                                'params' => $item['params_list'],
                            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d4-play" id="docs4Play" hidden>
        <div>
            <div class="d4-label">请求地址</div>
            <div class="d4-play__url" id="pgUrlPreview">—</div>
            <div class="d4-label">Method</div>
            <div class="d4-methods" id="pgMethodSelector"></div>
            <div class="d4-label">参数</div>
            <div id="pgParamsWrap"></div>
            <button type="button" class="d4-send" id="pgSendBtn">发送请求</button>
        </div>
        <div>
            <div class="d4-play__resp-head">
                <span class="d4-label">Response</span>
                <span class="vs-badge vs-badge--default" id="pgStatus">等待中</span>
            </div>
            <div class="d4-play__box" id="pgResponse">// 结果将在此处显示</div>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
window.playgroundKeyContext = <?php echo json_encode(array(
    'loggedIn' => !empty($playground['loggedIn']),
    'apiKeyCount' => isset($playground['apiKeyCount']) ? (int) $playground['apiKeyCount'] : 0,
    'userCenterUrl' => isset($playground['userCenterUrl']) ? $playground['userCenterUrl'] : ($vsBase . '/user/index'),
    'loginUrl' => isset($playground['loginUrl']) ? $playground['loginUrl'] : ($vsBase . '/user/login'),
    'keysUrl' => isset($playground['keysUrl']) ? $playground['keysUrl'] : vs_site_path('/core/front/playground-key.php'),
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
window.VS_CSRF_TOKEN = window.VS_CSRF_TOKEN || <?php echo json_encode(isset($playground['csrf']) ? $playground['csrf'] : (class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
window.VS_PLAY_URL = window.VS_PLAY_URL || <?php echo json_encode(isset($playground['playUrl']) ? $playground['playUrl'] : vs_site_path('/core/playground/relay.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="<?php echo vs_e($assetBase); ?>/assets/js/vs-syntax.js?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/js/playground-response.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/js/pages/docs-browser.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php if ($hasAnnounce): ?>
<script src="<?php echo vs_e(ThemeManager::assetUrl('docs', 'assets/js/pages/home-announcement.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
