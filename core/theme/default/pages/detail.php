<?php if (!defined('VS_THEME_RENDER')) { exit; }

$apiRaw = (isset($api) && is_array($api)) ? $api : null;
$notFound = !empty($notFound) || $apiRaw === null;
/** @var array $api 始终为数组，避免 IDE 在分支内误判 null */
$api = $apiRaw !== null ? $apiRaw : array();
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
$authWayLabel = '无需密钥';
if (!$notFound) {
    $needKeyVal = isset($api['needkey']) ? (int) $api['needkey'] : 0;
    if ($needKeyVal !== 0) {
        $authWayLabel = !empty($api['keyways_label'])
            ? (string) $api['keyways_label']
            : 'Query 参数';
    }
}
$keywaysList = (!$notFound && isset($api['keyways']) && is_array($api['keyways']))
    ? $api['keyways']
    : array('query');
$showQsAuthSwitch = !$notFound
    && (int) (isset($api['needkey']) ? $api['needkey'] : 0) !== 0
    && count($keywaysList) > 1;
$isDisabled = !$notFound && !empty($api['disabled']);
$isMaintenance = !$notFound && !empty($api['maintenance']);
$callBlocked = $isDisabled || $isMaintenance;
$endpointDisplay = (!$notFound && isset($api['endpoint'])) ? (string) $api['endpoint'] : '';
$endpointBlurText = 'https://••••••••••••/api/v1/••••••••';

$recommendApi = null;
$pageApiSnapshot = (!$notFound && $api !== array()) ? $api : null;
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
      data-endpoint="<?php echo ($notFound || $isDisabled) ? '' : vs_e(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>"
      data-maintenance="<?php echo $isMaintenance ? '1' : '0'; ?>"
      data-disabled="<?php echo $isDisabled ? '1' : '0'; ?>">
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

    <?php
    $detailImgBase = rtrim(ThemeManager::assetUrl(ThemeManager::activeId(), 'assets/img'), '/') . '/';
    $detailSiteName = class_exists('SiteContext') ? SiteContext::siteName() : 'ApiNexus';
    $detailHost = parse_url($vsBase, PHP_URL_HOST);
    if (!is_string($detailHost) || $detailHost === '') {
        $detailHost = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    }
    $detailApiBase = rtrim($vsBase, '/') . '/api/v1';
    $detailDocRaw = isset($api['doc']) ? trim((string) $api['doc']) : '';
    $detailMdParts = array();
    $detailMdParts[] = '# ' . (isset($api['name']) ? (string) $api['name'] : '接口文档');
    if (!empty($api['category_name'])) {
        $detailMdParts[] = '**分类：** ' . (string) $api['category_name'];
    }
    if (!empty($api['desc'])) {
        $detailMdParts[] = trim((string) $api['desc']);
    }
    $detailMdParts[] = "## 接口信息\n\n"
        . '**方法：** ' . (isset($api['method_label']) ? (string) $api['method_label'] : strtoupper($primaryMethod)) . "\n"
        . '**路径 / 完整地址：** ' . ($isDisabled ? '（已禁用，地址已隐藏）' : (isset($api['endpoint']) ? (string) $api['endpoint'] : '')) . "\n"
        . '**状态：** ' . ($isDisabled ? '已禁用' : ($isMaintenance ? '维护中' : '正常')) . "\n"
        . '**计费：** ' . $chargeDetailLabel . "\n"
        . '**KEY：** ' . $keyLabel . "\n"
        . '**鉴权方式：** ' . $authWayLabel . "\n"
        . '**QPM：** ' . (isset($api['qpm_label']) ? (string) $api['qpm_label'] : '不限制') . "\n"
        . '**文档页：** ' . (function_exists('vs_api_detail_url') ? vs_api_detail_url((int) $api['id']) : (rtrim($vsBase, '/') . '/detail/' . (int) $api['id']));
    if ($hasParamsTable) {
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
        $detailMdParts[] = $tbl;
    } elseif ($paramsRaw !== '') {
        $detailMdParts[] = "## 请求参数\n\n```json\n" . ($paramsPretty !== '' ? $paramsPretty : $paramsRaw) . "\n```";
    }
    if (!empty($api['response'])) {
        $detailMdParts[] = "## 返回示例\n\n```json\n" . trim((string) $api['response']) . "\n```";
    }
    if ($detailDocRaw !== '') {
        $detailMdParts[] = "## 详细文档\n\n" . $detailDocRaw;
    }
    $detailPageMarkdown = implode("\n\n", $detailMdParts);
    ?>
    <header class="detail-header">
        <div class="detail-header__top">
            <div class="detail-meta">
                <?php foreach ($methods as $m): ?>
                    <span class="method-badge <?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
                <?php endforeach; ?>
                <?php if ($isDisabled): ?>
                    <span class="api-chip api-chip--disabled">已禁用</span>
                <?php elseif ($isMaintenance): ?>
                    <span class="api-chip api-chip--maintenance">维护中</span>
                <?php else: ?>
                    <span class="api-chip <?php echo $points > 0 ? 'api-chip--points' : 'api-chip--free'; ?>"><?php echo vs_e($billingLabel); ?></span>
                <?php endif; ?>
                <span class="api-chip api-chip--key"><?php echo vs_e($keyLabel); ?></span>
                <?php if (!empty($api['category_name'])): ?>
                    <span class="api-chip"><?php echo vs_e($api['category_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="detail-title-row">
            <h1 class="detail-title"><?php echo vs_e($api['name']); ?></h1>
            <div class="detail-ai-split" id="detailAiSplit">
                <button type="button" class="detail-ai-split__main" id="detailAskDoubaoBtn" title="问问豆包">
                    <span class="detail-ai-split__ask">问问</span>
                    <img class="detail-ai-split__avatar" src="<?php echo vs_e($detailImgBase . 'doubao.svg'); ?>" alt="" width="16" height="16" decoding="async">
                    <span class="detail-ai-split__name">豆包</span>
                </button>
                <span class="detail-ai-split__divider" aria-hidden="true"></span>
                <button type="button" class="detail-ai-split__chevron" id="detailAiMenuBtn"
                        aria-expanded="false" aria-haspopup="true" aria-controls="detailAiMenu" title="更多操作">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="detail-ai-split__menu" id="detailAiMenu" role="menu" hidden>
                    <button type="button" class="detail-ai-split__item" role="menuitem" data-ai-action="copy-md">
                        <img class="detail-ai-split__item-icon" src="<?php echo vs_e($detailImgBase . 'fuzhi.svg'); ?>" alt="" width="16" height="16" decoding="async">
                        <span>复制整页为 Markdown</span>
                    </button>
                    <button type="button" class="detail-ai-split__item" role="menuitem" data-ai-action="ask-doubao">
                        <img class="detail-ai-split__item-icon detail-ai-split__item-icon--round" src="<?php echo vs_e($detailImgBase . 'doubao.svg'); ?>" alt="" width="16" height="16" decoding="async">
                        <span>问问豆包</span>
                        <svg class="detail-ai-split__ext" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php if (!empty($api['desc'])): ?>
        <p class="detail-desc"><?php echo vs_e($api['desc']); ?></p>
        <?php endif; ?>
    </header>

    <section class="detail-card">
        <h2 class="detail-section-title">接口信息</h2>
        <?php if ($endpointDisplay !== '' || $isDisabled): ?>
        <div class="endpoint-box<?php echo $isDisabled ? ' is-blurred' : ''; ?>">
            <div class="endpoint-box__text font-mono">
                <span class="endpoint-box__method"><?php echo vs_e(isset($api['method_label']) ? $api['method_label'] : strtoupper($primaryMethod)); ?></span>
                <?php if ($isDisabled): ?>
                <span id="detailEndpoint" class="endpoint-box__masked" title="接口已禁用，调用地址已隐藏"><?php echo vs_e($endpointBlurText); ?></span>
                <?php else: ?>
                <span id="detailEndpoint"><?php echo vs_e($endpointDisplay); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!$isDisabled): ?>
            <button type="button" class="btn-copy" data-copy="<?php echo vs_e($endpointDisplay); ?>">复制</button>
            <?php endif; ?>
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
            <div class="info-item">
                <div class="info-label">QPM</div>
                <div class="info-value info-value--qpm"><?php echo vs_e(isset($api['qpm_label']) ? $api['qpm_label'] : '不限制'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">鉴权方式</div>
                <div class="info-value info-value--auth"><?php echo vs_e($authWayLabel); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">作者</div>
                <div class="info-value info-value--author">
                    <?php if (!empty($api['author']) && is_array($api['author']) && !empty($api['author']['profile_url'])): ?>
                    <a class="detail-author" href="<?php echo vs_e($api['author']['profile_url']); ?>">
                        <?php if (!empty($api['author']['avatar'])): ?>
                        <img class="detail-author__avatar" src="<?php echo vs_e($api['author']['avatar']); ?>" alt="" width="22" height="22" loading="lazy">
                        <?php endif; ?>
                        <span class="detail-author__name"><?php echo vs_e(!empty($api['author']['username']) ? $api['author']['username'] : '开发者'); ?></span>
                    </a>
                    <?php else: ?>
                    <span class="detail-author__empty">—</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($isDisabled): ?>
        <div class="detail-notice detail-notice--danger">该接口已被禁用，调用地址与可调用信息已隐藏，暂时无法请求。</div>
        <?php elseif ($isMaintenance): ?>
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

    <section class="detail-card" id="detailFeedbackCard"
             data-logged-in="<?php echo !empty($playground['loggedIn']) ? '1' : '0'; ?>"
             data-login-url="<?php echo vs_e(isset($playground['loginUrl']) ? (string) $playground['loginUrl'] : ($vsBase . '/user/login')); ?>"
             data-feedback-ready="<?php echo !empty($playground['feedbackReady']) ? '1' : '0'; ?>">
        <h2 class="detail-section-title">接口反馈</h2>
        <?php if (empty($playground['feedbackReady'])): ?>
        <p class="detail-empty-hint">反馈功能暂未开放，请稍后再试。</p>
        <?php else: ?>
        <form id="detailFeedbackForm" class="detail-feedback" method="post" action="" novalidate>
            <input type="hidden" name="action" value="submit_feedback">
            <input type="hidden" name="apiid" value="<?php echo (int) $api['id']; ?>">
            <label class="detail-feedback__label" for="detailFeedbackContent">问题描述</label>
            <textarea class="form-input detail-feedback__textarea" id="detailFeedbackContent" name="content"
                      rows="4" maxlength="500"
                      placeholder="请描述遇到的问题或改进建议（5～500 字）"
                      <?php echo empty($playground['loggedIn']) ? '' : 'required'; ?>></textarea>
            <div class="detail-feedback__foot">
                <button type="submit" class="btn-geek detail-feedback__submit" id="detailFeedbackBtn">提交反馈</button>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <section class="detail-card detail-fold is-collapsed" id="detailDocCard">
        <button type="button" class="detail-fold__toggle" id="detailDocToggle" aria-expanded="false" aria-controls="detailDocBody">
            <span class="detail-section-title detail-fold__title">详细文档</span>
            <span class="detail-fold__hint" id="detailDocHint">预览 · 点击展开</span>
            <span class="detail-fold__chevron" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
        </button>
        <div class="detail-fold__body" id="detailDocBody">
            <div class="detail-fold__inner">
                <?php if (!empty($api['doc'])): ?>
                <div class="markdown-body vs-md-body detail-md is-parsed"><?php echo Markdown::render((string) $api['doc']); ?></div>
                <?php else: ?>
                <p class="detail-empty-hint">暂无详细文档</p>
                <?php endif; ?>
            </div>
            <div class="detail-fold__fade" id="detailDocFade" aria-hidden="true"></div>
        </div>
    </section>

    <section class="detail-card" id="detailQuickstartCard">
        <h2 class="detail-section-title">快速上手</h2>
        <?php
        $qsBundle = array('auths' => array(), 'authLabels' => array(), 'byAuth' => array());
        $qsSamples = array();
        if (!$notFound) {
            $qsBundle = ApiQuickstart::qsBundleFromAidoc(
                isset($api['aidoc']) ? (string) $api['aidoc'] : '',
                $keywaysList
            );
            $qsAuths = isset($qsBundle['auths']) && is_array($qsBundle['auths']) ? $qsBundle['auths'] : array();
            if (!empty($qsAuths)) {
                $firstAuth = $qsAuths[0];
                $byAuth = isset($qsBundle['byAuth']) && is_array($qsBundle['byAuth']) ? $qsBundle['byAuth'] : array();
                $qsSamples = isset($byAuth[$firstAuth]) && is_array($byAuth[$firstAuth]) ? $byAuth[$firstAuth] : array();
            }
        }
        $qsShowAuthTabs = $showQsAuthSwitch && count($qsBundle['auths']) > 1;
        ?>
        <?php if ($qsSamples === array()): ?>
        <p class="detail-empty-hint">暂无代码示例。管理员可在后台用 AI 生成或手动编写。</p>
        <?php else: ?>
        <?php if ($qsShowAuthTabs): ?>
        <div class="detail-quickstart__auth-tabs" id="detailQsAuthTabs" role="tablist" aria-label="鉴权方式">
            <?php foreach ($qsBundle['auths'] as $ai => $authId): ?>
            <?php
            $authLbl = isset($qsBundle['authLabels'][$authId])
                ? (string) $qsBundle['authLabels'][$authId]
                : ApiQuickstart::authLabel($authId);
            ?>
            <button type="button"
                    class="detail-quickstart__auth-tab<?php echo $ai === 0 ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $ai === 0 ? 'true' : 'false'; ?>"
                    data-qs-auth="<?php echo vs_e($authId); ?>"><?php echo vs_e($authLbl); ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="detail-quickstart" id="detailQuickstart"
             data-qs-count="<?php echo count($qsSamples); ?>"
             data-qs-multi-auth="<?php echo $qsShowAuthTabs ? '1' : '0'; ?>">
            <div class="detail-quickstart__tabs" id="detailQsTabs" role="tablist" aria-label="示例语言">
                <?php foreach ($qsSamples as $qi => $qs): ?>
                <button type="button"
                        class="detail-quickstart__tab<?php echo $qi === 0 ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $qi === 0 ? 'true' : 'false'; ?>"
                        data-qs-idx="<?php echo (int) $qi; ?>"
                        data-qs-id="<?php echo vs_e($qs['id']); ?>"
                        data-qs-syn="<?php echo vs_e(isset($qs['syn']) ? $qs['syn'] : 'javascript'); ?>">
                    <span class="detail-quickstart__icon<?php echo !empty($qs['single_icon']) ? ' is-single' : ''; ?>" aria-hidden="true">
                        <img class="detail-quickstart__icon-img is-gray" src="<?php echo vs_e($qs['icon_gray']); ?>" alt="" width="16" height="16" loading="lazy">
                        <?php if (empty($qs['single_icon'])): ?>
                        <img class="detail-quickstart__icon-img is-color" src="<?php echo vs_e($qs['icon_color']); ?>" alt="" width="16" height="16" loading="lazy">
                        <?php endif; ?>
                    </span>
                    <span class="detail-quickstart__label"><?php echo vs_e($qs['label']); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="detail-quickstart__panel">
                <button type="button" class="detail-quickstart__copy" id="detailQsCopy">复制</button>
                <pre class="detail-quickstart__code font-mono" id="detailQsCode"><code class="language-<?php echo vs_e(isset($qsSamples[0]['syn']) ? $qsSamples[0]['syn'] : 'bash'); ?>" data-vs-syn="<?php echo vs_e(isset($qsSamples[0]['syn']) ? $qsSamples[0]['syn'] : 'bash'); ?>" data-vs-plain="<?php echo vs_e(isset($qsSamples[0]['code']) ? $qsSamples[0]['code'] : ''); ?>"><?php echo vs_e(isset($qsSamples[0]['code']) ? $qsSamples[0]['code'] : ''); ?></code></pre>
            </div>
        </div>
        <script>
        <?php
        $qsRowToJs = function ($row) {
            return array(
                'id'          => isset($row['id']) ? $row['id'] : '',
                'label'       => isset($row['label']) ? $row['label'] : (isset($row['id']) ? $row['id'] : ''),
                'code'        => isset($row['code']) ? $row['code'] : '',
                'syn'         => isset($row['syn']) ? $row['syn'] : 'javascript',
                'icon_gray'   => isset($row['icon_gray']) ? $row['icon_gray'] : '',
                'icon_color'  => isset($row['icon_color']) ? $row['icon_color'] : '',
                'single_icon' => !empty($row['single_icon']) ? 1 : 0,
            );
        };
        $qsByAuthJs = array();
        if (!empty($qsBundle['byAuth']) && is_array($qsBundle['byAuth'])) {
            foreach ($qsBundle['byAuth'] as $authKey => $rows) {
                $qsByAuthJs[(string) $authKey] = array_map($qsRowToJs, is_array($rows) ? $rows : array());
            }
        }
        $qsJsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
        ?>
        window.detailQsLangIcons = <?php echo json_encode(ApiQuickstart::langIconMap(), $qsJsonFlags); ?>;
        window.detailQsSamples = <?php echo json_encode(array_map($qsRowToJs, $qsSamples), $qsJsonFlags); ?>;
        window.detailQsBundle = <?php echo json_encode(array(
            'auths' => isset($qsBundle['auths']) ? $qsBundle['auths'] : array(),
            'authLabels' => isset($qsBundle['authLabels']) ? $qsBundle['authLabels'] : array(),
            'byAuth' => $qsByAuthJs,
        ), $qsJsonFlags); ?>;
        </script>
        <?php endif; ?>
    </section>

    <?php
    // 内容：系统设置启用 + 非空正文；展示：默认主题 show_api_disclaimer
    $disclaimerEnabled = class_exists('Config') && Config::get('api_disclaimer_on', '0') === '1';
    $disclaimerThemeOn = class_exists('ThemeManager') && ThemeManager::themeSettingBool('show_api_disclaimer', true);
    $disclaimerBody = ($disclaimerEnabled && $disclaimerThemeOn)
        ? trim((string) Config::get('api_disclaimer', ''))
        : '';
    if ($disclaimerBody !== ''):
    ?>
    <section class="detail-card detail-disclaimer" id="detailDisclaimer">
        <h2 class="detail-section-title">免责声明</h2>
        <div class="markdown-body vs-md-body detail-md is-parsed detail-disclaimer__body">
            <?php echo Markdown::render($disclaimerBody); ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="detail-card" id="detailPlayground">
        <h2 class="detail-section-title">在线测试</h2>
        <?php if ($isDisabled): ?>
        <div class="detail-notice detail-notice--danger">接口已禁用，暂不可测试。</div>
        <?php elseif ($isMaintenance): ?>
        <div class="detail-notice detail-notice--warn">维护中，暂不可测试。</div>
        <?php elseif ($endpointDisplay === ''): ?>
        <p class="detail-empty-hint">未配置调用地址，无法测试。</p>
        <?php else: ?>
        <div class="playground-grid">
            <div class="playground-pane">
                <div class="playground-label">请求地址</div>
                <div class="endpoint-box endpoint-box--sm">
                    <div class="endpoint-box__text font-mono" id="pgUrlPreview"><?php echo vs_e($endpointDisplay); ?></div>
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
    'endpoint' => $isDisabled ? '' : (isset($jsApi['endpoint']) ? $jsApi['endpoint'] : ''),
    'methods' => $methods,
    'method' => $primaryMethod,
    'maintenance' => !empty($jsApi['maintenance']) ? 1 : 0,
    'disabled' => $isDisabled ? 1 : 0,
    'needkey' => isset($jsApi['needkey']) ? (int) $jsApi['needkey'] : 0,
    'keyways' => $keywaysList,
    'params_list' => $paramsList,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.playgroundUserApiKey = <?php echo json_encode(isset($playground['apiKey']) ? (string) $playground['apiKey'] : ''); ?>;
window.playgroundKeyContext = <?php echo json_encode(array(
    'loggedIn' => !empty($playground['loggedIn']),
    'apiKeyCount' => isset($playground['apiKeyCount']) ? (int) $playground['apiKeyCount'] : 0,
    'userCenterUrl' => isset($playground['userCenterUrl']) ? (string) $playground['userCenterUrl'] : ($vsBase . '/user/index'),
    'loginUrl' => isset($playground['loginUrl']) ? (string) $playground['loginUrl'] : ($vsBase . '/user/login'),
    'feedbackReady' => !empty($playground['feedbackReady']),
), JSON_UNESCAPED_UNICODE); ?>;
window.VS_CSRF_TOKEN = <?php echo json_encode(isset($playground['csrf']) ? (string) $playground['csrf'] : AuthSecurity::csrfToken()); ?>;
window.VS_PLAY_URL = <?php echo json_encode(isset($playground['playUrl']) ? (string) $playground['playUrl'] : (rtrim($vsBase, '/') . '/core/playground/relay.php')); ?>;
window.VS_BASE_URL = window.VS_BASE_URL || <?php echo json_encode(rtrim($vsBase, '/')); ?>;
window.detailPageMarkdown = <?php echo json_encode(isset($detailPageMarkdown) ? (string) $detailPageMarkdown : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.detailAiMeta = <?php echo json_encode(array(
    'siteName' => isset($detailSiteName) ? (string) $detailSiteName : 'ApiNexus',
    'host' => isset($detailHost) ? (string) $detailHost : '',
    'apiBase' => isset($detailApiBase) ? (string) $detailApiBase : (rtrim($vsBase, '/') . '/api/v1'),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
<?php $vsSyntaxHref = ThemeManager::pageScriptUrl('vs-syntax.js'); if ($vsSyntaxHref !== ''): ?>
<script src="<?php echo vs_e($vsSyntaxHref); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo vs_e($vsBase); ?>/core/markdown/assets/js/markdown-render.js?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
