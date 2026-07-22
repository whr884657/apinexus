<?php if (!defined('VS_THEME_RENDER')) { exit; }

$api = isset($api) && is_array($api) ? $api : null;
$notFound = !empty($notFound) || $api === null;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$playground = isset($playground) && is_array($playground) ? $playground : array(
    'loggedIn' => false,
    'apiKey' => '',
    'apiKeyCount' => 0,
    'userCenterUrl' => $vsBase . '/user/index',
    'loginUrl' => $vsBase . '/user/login',
);

$methods = (!$notFound && isset($api['methods']) && is_array($api['methods'])) ? $api['methods'] : array('GET');
$primaryMethod = !$notFound && !empty($api['method']) ? (string) $api['method'] : (isset($methods[0]) ? (string) $methods[0] : 'GET');
$points = !$notFound && isset($api['points']) ? (float) $api['points'] : 0;
$billingLabel = !$notFound && !empty($api['billing_label'])
    ? (string) $api['billing_label']
    : FrontendApi::billingLabel(
        !$notFound && isset($api['charge']) ? $api['charge'] : 0,
        $points
    );
$chargeDetailLabel = $billingLabel;
if (!$notFound && !empty($api['charge']) && $points > 0) {
    $fmt = rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.');
    $chargeDetailLabel = $fmt . ' 积分 / 次';
}
$callsLabel = !$notFound ? number_format((int) (isset($api['calls']) ? $api['calls'] : 0)) : '0';
$paramsList = (!$notFound && isset($api['params_list']) && is_array($api['params_list'])) ? $api['params_list'] : array();
$paramsRaw = (!$notFound && isset($api['params'])) ? (string) $api['params'] : '';
$paramsPretty = $paramsRaw !== '' ? FrontendApi::prettyParamsJson($paramsRaw) : '';
$hasParamsTable = count($paramsList) > 0;
$keyLabel = !$notFound && !empty($api['needkey_label']) ? (string) $api['needkey_label'] : '无需 KEY';

$recommendApi = null;
$pageApiSnapshot = (!$notFound && is_array($api)) ? $api : null;
if (!$notFound) {
    $pool = FrontendApi::listForTheme();
    $candidates = array();
    $curId = (int) $api['id'];
    $curCat = isset($api['category']) ? (string) $api['category'] : '';
    foreach ($pool as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((int) (isset($item['id']) ? $item['id'] : 0) === $curId) {
            continue;
        }
        $candidates[] = $item;
    }
    if ($candidates !== array()) {
        $sameCat = array();
        foreach ($candidates as $item) {
            if ($curCat !== '' && (string) (isset($item['category']) ? $item['category'] : '') === $curCat) {
                $sameCat[] = $item;
            }
        }
        $pickPool = $sameCat !== array() ? $sameCat : $candidates;
        usort($pickPool, function ($a, $b) {
            $ca = isset($a['calls']) ? (int) $a['calls'] : 0;
            $cb = isset($b['calls']) ? (int) $b['calls'] : 0;
            if ($ca !== $cb) {
                return $cb - $ca;
            }
            return (int) (isset($b['id']) ? $b['id'] : 0) - (int) (isset($a['id']) ? $a['id'] : 0);
        });
        $recommendApi = $pickPool[0];
    }
}
?>
<main class="main-wrapper container mx-auto px-4 detail-page" id="apiDetailPage"
      data-api-id="<?php echo $notFound ? '0' : (int) $api['id']; ?>"
      data-endpoint="<?php echo $notFound ? '' : vs_e(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>"
      data-maintenance="<?php echo (!$notFound && !empty($api['maintenance'])) ? '1' : '0'; ?>">
    <nav class="detail-crumb text-sm" aria-label="面包屑">
        <a href="<?php echo vs_e($vsBase); ?>/">首页</a>
        <span class="detail-crumb__sep">/</span>
        <a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a>
        <span class="detail-crumb__sep">/</span>
        <span class="detail-crumb__current"><?php echo $notFound ? '未找到' : vs_e($api['name']); ?></span>
    </nav>

    <?php if ($notFound): ?>
    <section class="detail-card detail-card--empty">
        <h1 class="detail-section-title">接口不存在</h1>
        <p class="detail-lead">该接口不存在、未通过审核或已下架，请从全部接口列表重新选择。</p>
        <div class="detail-actions">
            <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
        </div>
    </section>
    <?php else: ?>

    <header class="detail-header">
        <div class="detail-header__top">
            <div class="detail-meta">
                <?php foreach ($methods as $m): ?>
                    <span class="method-badge <?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
                <?php endforeach; ?>
                <?php if (!empty($api['maintenance'])): ?>
                    <span class="api-chip api-chip--maintenance">维护中</span>
                <?php else: ?>
                    <span class="api-chip <?php echo $points > 0 ? 'api-chip--points' : 'api-chip--free'; ?>"><?php echo vs_e($billingLabel); ?></span>
                <?php endif; ?>
                <span class="api-chip api-chip--key"><?php echo vs_e($keyLabel); ?></span>
                <?php if (!empty($api['category_name'])): ?>
                    <span class="api-chip"><?php echo vs_e($api['category_name']); ?></span>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-copy" id="detailCopyAllBtn" title="复制本页关键信息">复制全部</button>
        </div>
        <h1 class="detail-title"><?php echo vs_e($api['name']); ?></h1>
        <?php if (!empty($api['desc'])): ?>
        <p class="detail-desc"><?php echo vs_e($api['desc']); ?></p>
        <?php endif; ?>
    </header>

    <section class="detail-card">
        <h2 class="detail-section-title">接口信息</h2>
        <?php if (!empty($api['endpoint'])): ?>
        <div class="endpoint-box">
            <div class="endpoint-box__text font-mono">
                <span class="endpoint-box__method"><?php echo vs_e(strtoupper($primaryMethod)); ?></span>
                <span id="detailEndpoint"><?php echo vs_e($api['endpoint']); ?></span>
            </div>
            <button type="button" class="btn-copy" data-copy="<?php echo vs_e($api['endpoint']); ?>">复制</button>
        </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Method</div>
                <div class="info-value info-value--method"><?php echo vs_e($api['method_label']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">分类</div>
                <div class="info-value info-value--cat"><?php echo vs_e(!empty($api['category_name']) ? $api['category_name'] : '未分类'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Calls</div>
                <div class="info-value info-value--calls"><?php echo vs_e($callsLabel); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">KEY</div>
                <div class="info-value info-value--key"><?php echo vs_e($keyLabel); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">计费</div>
                <div class="info-value info-value--billing"><?php echo vs_e($chargeDetailLabel); ?></div>
            </div>
            <?php if (!empty($api['createtime'])): ?>
            <div class="info-item">
                <div class="info-label">提交时间</div>
                <div class="info-value info-value--time"><?php echo vs_e($api['createtime']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($api['maintenance'])): ?>
        <div class="detail-notice detail-notice--warn">当前接口维护中，暂时无法调用。</div>
        <?php endif; ?>
    </section>

    <?php if ($paramsRaw !== ''): ?>
    <section class="detail-card" id="detailParamsCard">
        <div class="detail-section-title detail-section-title--tools">
            <span class="detail-section-title__text">请求参数</span>
            <div class="detail-tools">
                <?php if ($hasParamsTable): ?>
                <button type="button" class="btn-mode is-active" data-params-mode="table">表格</button>
                <button type="button" class="btn-mode" data-params-mode="json">JSON</button>
                <?php endif; ?>
                <button type="button" class="btn-copy" data-copy="<?php echo vs_e($paramsPretty !== '' ? $paramsPretty : $paramsRaw); ?>">复制</button>
            </div>
        </div>
        <?php if ($hasParamsTable): ?>
        <div class="params-table-wrap" id="paramsTableMode">
            <table class="params-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                        <th>示例</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paramsList as $p): ?>
                    <tr>
                        <td class="font-mono"><?php echo vs_e($p['name']); ?></td>
                        <td class="font-mono"><?php echo vs_e($p['type']); ?></td>
                        <td><?php echo !empty($p['required']) ? '<span class="req-yes">是</span>' : '<span class="req-no">否</span>'; ?></td>
                        <td><?php echo vs_e($p['description']); ?></td>
                        <td class="font-mono"><?php echo vs_e($p['example']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="code-block" id="paramsJsonMode" hidden>
            <pre class="code-content font-mono json-hl" id="paramsJsonCode"><?php echo vs_e($paramsPretty); ?></pre>
        </div>
        <?php else: ?>
        <div class="code-block">
            <pre class="code-content font-mono json-hl"><?php echo vs_e($paramsRaw); ?></pre>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['response'])): ?>
    <section class="detail-card">
        <div class="detail-section-title detail-section-title--tools">
            <span class="detail-section-title__text">返回示例</span>
            <button type="button" class="btn-copy" data-copy="<?php echo vs_e($api['response']); ?>">复制</button>
        </div>
        <div class="code-block">
            <pre class="code-content font-mono json-hl" id="responseSample"><?php echo vs_e($api['response']); ?></pre>
        </div>
    </section>
    <?php endif; ?>

    <section class="detail-card" id="detailDocCard">
        <h2 class="detail-section-title">详细文档</h2>
        <?php if (!empty($api['doc'])): ?>
        <div class="markdown-body detail-md is-parsed"><?php echo Markdown::render((string) $api['doc']); ?></div>
        <?php else: ?>
        <p class="detail-empty-hint">暂无详细文档</p>
        <?php endif; ?>
    </section>

    <section class="detail-card" id="detailAiDocCard">
        <h2 class="detail-section-title">AI 文档</h2>
        <?php if (!empty($api['aidoc'])): ?>
        <div class="markdown-body detail-md is-parsed"><?php echo Markdown::render((string) $api['aidoc']); ?></div>
        <?php else: ?>
        <p class="detail-empty-hint">暂无 AI 文档</p>
        <?php endif; ?>
    </section>

    <section class="detail-card" id="detailPlayground">
        <h2 class="detail-section-title">在线测试</h2>
        <?php if (!empty($api['maintenance'])): ?>
        <div class="detail-notice detail-notice--warn">维护中，暂不可测试。</div>
        <?php elseif (empty($api['endpoint'])): ?>
        <p class="detail-empty-hint">未配置调用地址，无法测试。</p>
        <?php else: ?>
        <div class="playground-grid">
            <div class="playground-pane">
                <div class="playground-label">请求地址</div>
                <div class="endpoint-box endpoint-box--sm">
                    <div class="endpoint-box__text font-mono" id="pgUrlPreview"><?php echo vs_e($api['endpoint']); ?></div>
                </div>

                <?php if (count($methods) > 1): ?>
                <div class="playground-label">Method</div>
                <div class="method-selector" id="pgMethodSelector">
                    <?php foreach ($methods as $i => $m): ?>
                    <button type="button" class="method-option<?php echo $i === 0 ? ' is-active' : ''; ?>" data-method="<?php echo vs_e(strtoupper(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <input type="hidden" id="pgMethodHidden" value="<?php echo vs_e(strtoupper($primaryMethod)); ?>">
                <?php endif; ?>

                <div class="playground-label">参数</div>
                <div id="pgParamsWrap" class="pg-params">
                    <?php if ($hasParamsTable): ?>
                        <?php foreach ($paramsList as $p): ?>
                        <label class="pg-param">
                            <span class="pg-param__name font-mono">
                                <?php echo vs_e($p['name']); ?>
                                <?php if (!empty($p['required'])): ?><em>*</em><?php endif; ?>
                            </span>
                            <?php if (strtolower($p['type']) === 'file'): ?>
                            <input type="file" class="param-input" data-param="<?php echo vs_e($p['name']); ?>">
                            <?php else: ?>
                            <input type="text" class="param-input form-input" data-param="<?php echo vs_e($p['name']); ?>"
                                   placeholder="<?php echo vs_e($p['example'] !== '' ? $p['example'] : ($p['description'] !== '' ? $p['description'] : $p['name'])); ?>">
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <p class="detail-empty-hint">无声明参数，可直接发送请求。</p>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-geek playground-send" id="pgSendBtn">发送请求</button>
            </div>
            <div class="playground-pane">
                <div class="playground-response-head">
                    <span class="playground-label" style="margin:0;">Response</span>
                    <span class="status-badge" id="pgStatus">等待中</span>
                </div>
                <div class="response-container">
                    <pre class="response-pre font-mono" id="pgResponse">// 结果将在此处显示</pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($recommendApi !== null): ?>
    <section class="detail-card detail-recommend">
        <h2 class="detail-section-title">推荐接口</h2>
        <div class="detail-recommend__grid">
            <?php
            $apiData = array($recommendApi);
            $showDetailBtn = true;
            $cardShell = false;
            include __DIR__ . '/../partials/api-cards-html.php';
            if (is_array($pageApiSnapshot)) {
                $api = $pageApiSnapshot;
            }
            ?>
        </div>
    </section>
    <?php endif; ?>

    <?php endif; ?>
</main>
<div class="copy-toast" id="detailCopyToast" hidden>已复制</div>
<script>
<?php
$jsApi = is_array($pageApiSnapshot) ? $pageApiSnapshot : ((!$notFound && is_array($api)) ? $api : null);
?>
window.detailApiData = <?php echo json_encode($jsApi === null ? null : array(
    'id' => (int) $jsApi['id'],
    'name' => isset($jsApi['name']) ? $jsApi['name'] : '',
    'endpoint' => isset($jsApi['endpoint']) ? $jsApi['endpoint'] : '',
    'methods' => $methods,
    'method' => $primaryMethod,
    'maintenance' => !empty($jsApi['maintenance']) ? 1 : 0,
    'needkey' => isset($jsApi['needkey']) ? (int) $jsApi['needkey'] : 0,
    'params_list' => $paramsList,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.playgroundUserApiKey = <?php echo json_encode(isset($playground['apiKey']) ? (string) $playground['apiKey'] : ''); ?>;
window.playgroundKeyContext = <?php echo json_encode(array(
    'loggedIn' => !empty($playground['loggedIn']),
    'apiKeyCount' => isset($playground['apiKeyCount']) ? (int) $playground['apiKeyCount'] : 0,
    'userCenterUrl' => isset($playground['userCenterUrl']) ? (string) $playground['userCenterUrl'] : ($vsBase . '/user/index'),
    'loginUrl' => isset($playground['loginUrl']) ? (string) $playground['loginUrl'] : ($vsBase . '/user/login'),
), JSON_UNESCAPED_UNICODE); ?>;
window.VS_CSRF_TOKEN = <?php echo json_encode(isset($playground['csrf']) ? (string) $playground['csrf'] : AuthSecurity::csrfToken()); ?>;
window.VS_PLAY_URL = <?php echo json_encode(isset($playground['playUrl']) ? (string) $playground['playUrl'] : (rtrim($vsBase, '/') . '/core/playground/relay.php')); ?>;
</script>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
