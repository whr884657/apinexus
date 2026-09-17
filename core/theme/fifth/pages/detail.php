<?php
/**
 * 主题 five · API 详情（自研 TH5 视觉，能力对齐默认主题）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$apiRaw = (isset($api) && is_array($api)) ? $api : null;
$notFound = !empty($notFound) || $apiRaw === null;
/** @var array $api */
$api = $apiRaw !== null ? $apiRaw : array();
$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
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
$openapiJson = (!$notFound && isset($api['openapi_json'])) ? (string) $api['openapi_json'] : '';
$hasParamsTable = count($paramsList) > 0;
$hasOpenApi = $openapiJson !== '';
$paramsCopyDefault = $paramsPretty !== '' ? $paramsPretty : $paramsRaw;
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
$endpointRaw = (!$notFound && isset($api['endpoint'])) ? (string) $api['endpoint'] : '';
$endpointCopy = ($endpointRaw !== '' && !$isDisabled) ? vs_call_url_absolute($endpointRaw) : '';
$endpointDisplay = ($endpointRaw !== '' && !$isDisabled) ? vs_call_url_host_path($endpointRaw) : '';
$endpointBlurText = '••••••••••••/••••••••••••';

$pageApiSnapshot = (!$notFound && $api !== array()) ? $api : null;
$recommendApi = (!$notFound) ? FrontendApi::pickRandomRecommend((int) $api['id']) : null;

$disclaimerEnabled = class_exists('Config') && Config::get('api_disclaimer_on', '0') === '1';
$disclaimerThemeOn = class_exists('ThemeManager') && ThemeManager::themeSettingBool('show_api_disclaimer', true);
$disclaimerBody = ($disclaimerEnabled && $disclaimerThemeOn)
    ? trim(function_exists('vs_ensure_plaintext_field')
        ? vs_ensure_plaintext_field(Config::get('api_disclaimer', ''))
        : (string) Config::get('api_disclaimer', ''))
    : '';

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

$TH5Tabs = array();
if (!$notFound) {
    if ($paramsRaw !== '') {
        $TH5Tabs[] = array('id' => 'params', 'label' => '请求参数');
    }
    if (!empty($api['response'])) {
        $TH5Tabs[] = array('id' => 'response', 'label' => '返回示例');
    }
    $TH5Tabs[] = array('id' => 'doc', 'label' => '详细文档');
    $TH5Tabs[] = array('id' => 'quickstart', 'label' => '快速上手');
    $TH5Tabs[] = array('id' => 'playground', 'label' => '在线测试');
    $TH5Tabs[] = array('id' => 'feedback', 'label' => '接口反馈');
}
$TH5FirstTab = isset($TH5Tabs[0]['id']) ? (string) $TH5Tabs[0]['id'] : '';

$detailPageMarkdown = '';
if (!$notFound) {
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
        . '**路径 / 完整地址：** ' . ($isDisabled ? '（已禁用，地址已隐藏）' : $endpointCopy) . "\n"
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
}
?>
<section class="th5-page th5-detail" id="apiDetailPage"
      data-api-id="<?php echo $notFound ? '0' : (int) $api['id']; ?>"
      data-endpoint="<?php echo ($notFound || $isDisabled) ? '' : vs_e($endpointCopy); ?>"
      data-maintenance="<?php echo $isMaintenance ? '1' : '0'; ?>"
      data-disabled="<?php echo $isDisabled ? '1' : '0'; ?>">
  <div class="th5-page__inner max-w-5xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 接口详情</div>

    <?php if ($notFound): ?>
      <h1 class="font-display font-bold tracking-tight th5-page__title">接口不存在</h1>
      <p class="th5-page__lead text-fg-2">该接口不存在、未通过审核或已下架。</p>
      <a class="btn-primary inline-flex mt-6" href="<?php echo vs_e($vsBase); ?>/apis">返回全部接口</a>
    <?php else: ?>

      <div class="th5-detail-q__hero">
        <div class="th5-detail-q__hero-grid" aria-hidden="true"></div>
        <div class="th5-detail-q__hero-inner">
          <div class="th5-detail-q__crumb">
            <span class="th5-detail-q__crumb-dot" aria-hidden="true"></span>
            / 接口详情
            <?php if (!empty($api['category_name'])): ?>
              <span class="th5-detail-q__crumb-sep">·</span>
              <em><?php echo vs_e($api['category_name']); ?></em>
            <?php endif; ?>
          </div>
          <div class="th5-detail-q__title-row">
            <span class="th5-detail-q__method<?php
              $methodCls = strtolower($primaryMethod);
              if ($methodCls === 'post') { echo ' m-post'; }
              elseif ($methodCls === 'put') { echo ' m-put'; }
              elseif ($methodCls === 'delete' || $methodCls === 'del') { echo ' m-del'; }
              else { echo ' m-get'; }
            ?>"><?php echo vs_e(isset($api['method_label']) ? $api['method_label'] : strtoupper($primaryMethod)); ?></span>
            <h1 class="font-display font-bold tracking-tight th5-page__title m-0"><?php echo vs_e($api['name']); ?></h1>
            <?php if ($isDisabled): ?>
              <span class="th5-detail-q__status is-disabled">已禁用</span>
            <?php elseif ($isMaintenance): ?>
              <span class="th5-detail-q__status is-maint">维护中</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($api['desc'])): ?>
            <p class="th5-detail-q__lead"><?php echo vs_e($api['desc']); ?></p>
          <?php endif; ?>
          <div class="th5-detail-q__chips">
            <span class="th5-detail-q__chip"><i data-lucide="activity" style="width:14px;height:14px;"></i>调用 <?php echo vs_e($callsLabel); ?> 次</span>
            <span class="th5-detail-q__chip"><i data-lucide="coins" style="width:14px;height:14px;"></i><?php echo vs_e($chargeDetailLabel); ?></span>
            <span class="th5-detail-q__chip"><i data-lucide="key-round" style="width:14px;height:14px;"></i><?php echo vs_e($keyLabel); ?></span>
            <span class="th5-detail-q__chip"><i data-lucide="gauge" style="width:14px;height:14px;"></i>QPM <?php echo vs_e(isset($api['qpm_label']) ? $api['qpm_label'] : '不限制'); ?></span>
            <span class="th5-detail-q__chip"><i data-lucide="shield-check" style="width:14px;height:14px;"></i><?php echo vs_e($authWayLabel); ?></span>
          </div>
          <div class="th5-detail-q__actions">
            <button type="button" class="th5-detail-q__btn" id="detailCopyMdBtn"><i data-lucide="code-2" style="width:15px;height:15px;"></i>复制 Markdown</button>
            <a class="th5-detail-q__btn th5-detail-q__btn--ghost" href="<?php echo vs_e($vsBase); ?>/apis"><i data-lucide="arrow-right" style="width:15px;height:15px;"></i>返回接口市场</a>
          </div>
        </div>
      </div>

      <?php if ($isDisabled): ?>
        <div class="th5-alert th5-alert--danger mt-4">该接口已被禁用，调用地址已隐藏，暂时无法请求。</div>
      <?php elseif ($isMaintenance): ?>
        <div class="th5-alert th5-alert--warn mt-4">当前接口维护中，暂时无法调用。</div>
      <?php endif; ?>

      <div class="th5-panel card mt-8 th5-detail-q__panel">
        <div class="th5-detail-q__panel-title"><span class="th5-detail-q__panel-icon"><i data-lucide="server" style="width:17px;height:17px;"></i></span><span>接口信息</span></div>
        <?php if ($endpointDisplay !== '' || $isDisabled): ?>
          <div class="th5-detail-q__endpoint">
            <span class="th5-detail-q__endpoint-prompt" aria-hidden="true">$</span>
            <span class="th5-detail-q__endpoint-method<?php
              $mCls = strtolower($primaryMethod);
              if ($mCls === 'post') { echo ' m-post'; }
              elseif ($mCls === 'put') { echo ' m-put'; }
              elseif ($mCls === 'delete' || $mCls === 'del') { echo ' m-del'; }
              else { echo ' m-get'; }
            ?>"><?php echo vs_e(isset($api['method_label']) ? $api['method_label'] : strtoupper($primaryMethod)); ?></span>
            <?php if ($isDisabled): ?>
              <span id="detailEndpoint" class="th5-detail-q__endpoint-path th5-endpoint__mask" title="接口已禁用"><?php echo vs_e($endpointBlurText); ?></span>
            <?php else: ?>
              <span id="detailEndpoint" class="th5-detail-q__endpoint-path break-all"><?php echo vs_e($endpointDisplay); ?></span>
            <?php endif; ?>
            <?php if (!$isDisabled && $endpointCopy !== ''): ?>
              <button type="button" class="th5-detail-q__endpoint-copy" data-copy="<?php echo vs_e($endpointCopy); ?>" aria-label="复制端点">复制</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="th5-info-grid th5-detail-q__info">
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="terminal" style="width:13px;height:13px;"></i>方法</div>
            <div class="th5-info-value"><?php echo vs_e(isset($api['method_label']) ? $api['method_label'] : strtoupper($primaryMethod)); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="globe" style="width:13px;height:13px;"></i>分类</div>
            <div class="th5-info-value"><?php echo vs_e(!empty($api['category_name']) ? $api['category_name'] : '未分类'); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="coins" style="width:13px;height:13px;"></i>计费</div>
            <div class="th5-info-value"><?php echo vs_e($chargeDetailLabel); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="key-round" style="width:13px;height:13px;"></i>KEY</div>
            <div class="th5-info-value"><?php echo vs_e($keyLabel); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="activity" style="width:13px;height:13px;"></i>调用</div>
            <div class="th5-info-value"><?php echo vs_e($callsLabel); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="gauge" style="width:13px;height:13px;"></i>QPM</div>
            <div class="th5-info-value"><?php echo vs_e(isset($api['qpm_label']) ? $api['qpm_label'] : '不限制'); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="shield-check" style="width:13px;height:13px;"></i>鉴权</div>
            <div class="th5-info-value"><?php echo vs_e($authWayLabel); ?></div>
          </div>
          <div class="th5-info-item">
            <div class="th5-info-label"><i data-lucide="user-plus" style="width:13px;height:13px;"></i>作者</div>
            <div class="th5-info-value">
              <?php if (!empty($api['author']) && is_array($api['author']) && !empty($api['author']['profile_url'])): ?>
                <a href="<?php echo vs_e($api['author']['profile_url']); ?>" style="color:var(--accent);">
                  <?php echo vs_e(!empty($api['author']['username']) ? $api['author']['username'] : '开发者'); ?>
                </a>
              <?php else: ?>
                —
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="th5-tabs mt-8" role="tablist" aria-label="接口详情分区">
        <?php foreach ($TH5Tabs as $tab): ?>
          <button type="button"
                  class="th5-pill<?php echo $tab['id'] === $TH5FirstTab ? ' is-active' : ''; ?>"
                  role="tab"
                  aria-selected="<?php echo $tab['id'] === $TH5FirstTab ? 'true' : 'false'; ?>"
                  data-th5-tab="<?php echo vs_e($tab['id']); ?>"><?php echo vs_e($tab['label']); ?></button>
        <?php endforeach; ?>
      </div>

      <div class="th5-panels mt-4">

        <?php if ($paramsRaw !== ''): ?>
        <div class="th5-panel card" data-th5-panel="params" role="tabpanel"<?php echo $TH5FirstTab === 'params' ? '' : ' hidden'; ?>>
          <div class="th5-panel__tools">
            <h2 class="font-display font-semibold text-lg m-0">请求参数</h2>
            <div class="th5-panel__actions">
              <?php if ($hasParamsTable): ?>
                <button type="button" class="th5-pill is-active" data-params-mode="table">表格</button>
                <button type="button" class="th5-pill" data-params-mode="json">JSON</button>
              <?php elseif ($hasOpenApi): ?>
                <button type="button" class="th5-pill is-active" data-params-mode="json">JSON</button>
              <?php endif; ?>
              <?php if ($hasOpenApi): ?>
                <button type="button" class="th5-pill" data-params-mode="openapi">OpenAPI</button>
              <?php endif; ?>
              <button type="button" class="btn-ghost text-sm" id="paramsCopyBtn"
                      data-copy="<?php echo vs_e($paramsCopyDefault); ?>"
                      data-copy-json="<?php echo vs_e($paramsCopyDefault); ?>">复制</button>
            </div>
          </div>
          <?php if ($hasParamsTable): ?>
            <div class="th5-table-wrap mt-4" id="paramsTableMode">
              <table class="th5-table">
                <thead>
                  <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th><th>示例</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($paramsList as $p): ?>
                  <tr>
                    <td class="font-mono"><?php echo vs_e($p['name']); ?></td>
                    <td class="font-mono"><?php echo vs_e($p['type']); ?></td>
                    <td><?php echo !empty($p['required']) ? '<span class="tag hot">是</span>' : '<span class="text-muted">否</span>'; ?></td>
                    <td><?php echo vs_e($p['description']); ?></td>
                    <td class="font-mono"><?php echo vs_e($p['example']); ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="th5-code mt-4" id="paramsJsonMode" hidden>
              <pre class="font-mono" id="paramsJsonCode"><?php echo vs_e($paramsPretty); ?></pre>
            </div>
          <?php else: ?>
            <div class="th5-code mt-4" id="paramsJsonMode">
              <pre class="font-mono"><?php echo vs_e($paramsRaw); ?></pre>
            </div>
          <?php endif; ?>
          <?php if ($hasOpenApi): ?>
            <div class="th5-code mt-4" id="paramsOpenApiMode" hidden>
              <pre class="font-mono" id="paramsOpenApiCode"><?php echo vs_e($openapiJson); ?></pre>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($api['response'])): ?>
        <div class="th5-panel card" data-th5-panel="response" role="tabpanel"<?php echo $TH5FirstTab === 'response' ? '' : ' hidden'; ?>>
          <div class="th5-panel__tools">
            <h2 class="font-display font-semibold text-lg m-0">返回示例</h2>
            <button type="button" class="btn-ghost text-sm" data-copy="<?php echo vs_e($api['response']); ?>">复制</button>
          </div>
          <div class="th5-code mt-4" id="responseSampleWrap">
            <pre class="font-mono" id="responseSample"><?php echo vs_e($api['response']); ?></pre>
          </div>
        </div>
        <?php endif; ?>

        <div class="th5-panel card" data-th5-panel="doc" role="tabpanel"<?php echo $TH5FirstTab === 'doc' ? '' : ' hidden'; ?>>
          <h2 class="font-display font-semibold text-lg m-0 mb-4">详细文档</h2>
          <?php if (!empty($api['doc'])): ?>
            <div class="markdown-body vs-md-body th5-md"><?php echo TH5_md_render((string) $api['doc']); ?></div>
          <?php else: ?>
            <p class="text-fg-2 m-0">暂无详细文档</p>
          <?php endif; ?>
        </div>

        <div class="th5-panel card" data-th5-panel="quickstart" role="tabpanel" id="detailQuickstartCard"<?php echo $TH5FirstTab === 'quickstart' ? '' : ' hidden'; ?>>
          <h2 class="font-display font-semibold text-lg m-0 mb-4">快速上手</h2>
          <?php if ($qsSamples === array()): ?>
            <p class="text-fg-2 m-0">暂无代码示例。管理员可在后台用 AI 生成或手动编写。</p>
          <?php else: ?>
            <?php if ($qsShowAuthTabs): ?>
            <div class="th5-qs-auth" id="detailQsAuthTabs" role="tablist" aria-label="鉴权方式">
              <?php foreach ($qsBundle['auths'] as $ai => $authId): ?>
                <?php
                $authLbl = isset($qsBundle['authLabels'][$authId])
                    ? (string) $qsBundle['authLabels'][$authId]
                    : ApiQuickstart::authLabel($authId);
                ?>
                <button type="button"
                        class="th5-pill<?php echo $ai === 0 ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $ai === 0 ? 'true' : 'false'; ?>"
                        data-qs-auth="<?php echo vs_e($authId); ?>"><?php echo vs_e($authLbl); ?></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="th5-qs" id="detailQuickstart"
                 data-qs-count="<?php echo count($qsSamples); ?>"
                 data-qs-multi-auth="<?php echo $qsShowAuthTabs ? '1' : '0'; ?>">
              <div class="th5-qs-tabs" id="detailQsTabs" role="tablist" aria-label="示例语言">
                <?php foreach ($qsSamples as $qi => $qs): ?>
                <button type="button"
                        class="th5-pill<?php echo $qi === 0 ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $qi === 0 ? 'true' : 'false'; ?>"
                        data-qs-idx="<?php echo (int) $qi; ?>"
                        data-qs-id="<?php echo vs_e($qs['id']); ?>"
                        data-qs-syn="<?php echo vs_e(isset($qs['syn']) ? $qs['syn'] : 'javascript'); ?>">
                  <?php if (!empty($qs['icon_gray'])): ?>
                  <img src="<?php echo vs_e($qs['icon_gray']); ?>" alt="" width="14" height="14" loading="lazy">
                  <?php endif; ?>
                  <span><?php echo vs_e($qs['label']); ?></span>
                </button>
                <?php endforeach; ?>
              </div>
              <div class="th5-qs-panel mt-4">
                <button type="button" class="btn-ghost text-sm th5-qs-copy" id="detailQsCopy">复制</button>
                <pre class="th5-code font-mono" id="detailQsCode"><code class="language-<?php echo vs_e(isset($qsSamples[0]['syn']) ? $qsSamples[0]['syn'] : 'bash'); ?>" data-vs-syn="<?php echo vs_e(isset($qsSamples[0]['syn']) ? $qsSamples[0]['syn'] : 'bash'); ?>" data-vs-plain="<?php echo vs_e(isset($qsSamples[0]['code']) ? $qsSamples[0]['code'] : ''); ?>"><?php echo vs_e(isset($qsSamples[0]['code']) ? $qsSamples[0]['code'] : ''); ?></code></pre>
              </div>
            </div>
            <script>
            <?php
            $qsRowToJs = function ($row) {
                return array(
                    'id' => isset($row['id']) ? $row['id'] : '',
                    'label' => isset($row['label']) ? $row['label'] : (isset($row['id']) ? $row['id'] : ''),
                    'code' => isset($row['code']) ? $row['code'] : '',
                    'syn' => isset($row['syn']) ? $row['syn'] : 'javascript',
                    'icon_gray' => isset($row['icon_gray']) ? $row['icon_gray'] : '',
                    'icon_color' => isset($row['icon_color']) ? $row['icon_color'] : '',
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
        </div>

        <div class="th5-panel card" data-th5-panel="playground" role="tabpanel" id="detailPlayground"<?php echo $TH5FirstTab === 'playground' ? '' : ' hidden'; ?>>
          <h2 class="font-display font-semibold text-lg m-0 mb-4">在线测试</h2>
          <?php if ($isDisabled): ?>
            <div class="th5-alert th5-alert--danger">接口已禁用，暂不可测试。</div>
          <?php elseif ($isMaintenance): ?>
            <div class="th5-alert th5-alert--warn">维护中，暂不可测试。</div>
          <?php elseif ($endpointDisplay === ''): ?>
            <p class="text-fg-2 m-0">未配置调用地址，无法测试。</p>
          <?php else: ?>
            <div class="th5-pg-grid">
              <div class="th5-pg-pane">
                <div class="th5-field-label">请求地址</div>
                <div class="th5-endpoint font-mono text-sm" id="pgUrlPreview"><?php echo vs_e($endpointDisplay); ?></div>

                <?php if (count($methods) > 1): ?>
                  <div class="th5-field-label mt-4">Method</div>
                  <div class="th5-method-row" id="pgMethodSelector">
                    <?php foreach ($methods as $i => $m): ?>
                      <button type="button" class="th5-pill<?php echo $i === 0 ? ' is-active' : ''; ?>" data-method="<?php echo vs_e(strtoupper(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></button>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <input type="hidden" id="pgMethodHidden" value="<?php echo vs_e(strtoupper($primaryMethod)); ?>">
                <?php endif; ?>

                <div class="th5-field-label mt-4">参数</div>
                <div id="pgParamsWrap" class="th5-pg-params">
                  <?php if ($hasParamsTable): ?>
                    <?php foreach ($paramsList as $p): ?>
                      <label class="th5-field">
                        <span class="font-mono">
                          <?php echo vs_e($p['name']); ?>
                          <?php if (!empty($p['required'])): ?><em style="color:var(--accent);">*</em><?php endif; ?>
                        </span>
                        <?php if (strtolower($p['type']) === 'file'): ?>
                          <input type="file" class="input param-input" data-param="<?php echo vs_e($p['name']); ?>">
                        <?php else: ?>
                          <input type="text" class="input param-input" data-param="<?php echo vs_e($p['name']); ?>"
                                 placeholder="<?php echo vs_e($p['example'] !== '' ? $p['example'] : ($p['description'] !== '' ? $p['description'] : $p['name'])); ?>">
                        <?php endif; ?>
                      </label>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p class="text-fg-2 text-sm">无声明参数，可直接发送请求。</p>
                  <?php endif; ?>
                </div>
                <button type="button" class="btn-primary mt-5 w-full justify-center" id="pgSendBtn">发送请求</button>
              </div>
              <div class="th5-pg-pane">
                <div class="th5-pg-resp-head">
                  <span class="th5-field-label" style="margin:0;">Response</span>
                  <div class="th5-pg-resp-meta">
                    <button type="button" class="btn-ghost text-sm" id="pgCopyBtn" hidden disabled aria-hidden="true">复制</button>
                    <span class="tag" id="pgStatus">等待中</span>
                  </div>
                </div>
                <pre class="th5-code font-mono mt-3" id="pgResponse">// 结果将在此处显示</pre>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="th5-panel card" data-th5-panel="feedback" role="tabpanel" id="detailFeedbackCard"
             data-logged-in="<?php echo !empty($playground['loggedIn']) ? '1' : '0'; ?>"
             data-login-url="<?php echo vs_e(isset($playground['loginUrl']) ? (string) $playground['loginUrl'] : ($vsBase . '/user/login')); ?>"
             data-feedback-ready="<?php echo !empty($playground['feedbackReady']) ? '1' : '0'; ?>"
             <?php echo $TH5FirstTab === 'feedback' ? '' : 'hidden'; ?>>
          <h2 class="font-display font-semibold text-lg m-0 mb-4">接口反馈</h2>
          <?php if (empty($playground['feedbackReady'])): ?>
            <p class="text-fg-2 m-0">反馈功能暂未开放，请稍后再试。</p>
          <?php else: ?>
            <form id="detailFeedbackForm" class="th5-form" method="post" action="" novalidate>
              <input type="hidden" name="action" value="submit_feedback">
              <input type="hidden" name="apiid" value="<?php echo (int) $api['id']; ?>">
              <label class="th5-field" for="detailFeedbackContent">
                <span>问题描述</span>
                <textarea class="input th5-textarea" id="detailFeedbackContent" name="content"
                          rows="4" maxlength="500"
                          placeholder="请描述遇到的问题或改进建议（5～500 字）"
                          <?php echo empty($playground['loggedIn']) ? '' : 'required'; ?>></textarea>
              </label>
              <button type="submit" class="btn-primary mt-4" id="detailFeedbackBtn">提交反馈</button>
            </form>
          <?php endif; ?>
        </div>

      </div>

      <?php if ($disclaimerBody !== ''): ?>
        <section class="th5-panel card mt-8" id="detailDisclaimer">
          <h2 class="font-display font-semibold text-lg m-0 mb-4">免责声明</h2>
          <div class="markdown-body vs-md-body th5-md">
            <?php echo TH5_md_render($disclaimerBody); ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($recommendApi !== null): ?>
        <section class="th5-panel card mt-8 th5-detail-q__panel" id="detailRecommend">
          <div class="th5-detail-q__panel-title"><span class="th5-detail-q__panel-icon"><i data-lucide="sparkles" style="width:17px;height:17px;"></i></span><span>推荐接口</span></div>
          <div class="th5-detail-q__rec">
            <?php
            $r = $recommendApi;
            $rId = (int) (isset($r['id']) ? $r['id'] : 0);
            $rName = trim((string) (isset($r['name']) ? $r['name'] : ''));
            $rDesc = trim((string) (isset($r['desc']) ? $r['desc'] : ''));
            $rUrl = !empty($r['detail_url']) ? (string) $r['detail_url'] : ($rId > 0 ? vs_api_detail_url($rId) : ($vsBase . '/apis'));
            $rIcon = trim((string) (isset($r['icon']) ? $r['icon'] : ''));
            $rMaint = !empty($r['maintenance']);
            $rMethod = isset($r['method']) ? strtoupper(trim((string) $r['method'])) : 'GET';
            $rPoints = isset($r['points']) ? (float) $r['points'] : 0;
            $rBilling = trim((string) (isset($r['billing_label']) ? $r['billing_label'] : ''));
            if ($rBilling === '') {
                $rBilling = $rPoints > 0
                    ? (rtrim(rtrim(number_format($rPoints, 4, '.', ''), '0'), '.') . '积分/次')
                    : '免费';
            }
            $rPaid = $rPoints > 0 || ($rBilling !== '免费' && strcasecmp($rBilling, 'free') !== 0);
            $rNeed = isset($r['needkey']) ? (int) $r['needkey'] : 0;
            $rCalls = isset($r['calls']) ? (int) $r['calls'] : 0;
            $rCallsLabel = number_format($rCalls);
            $rCat = trim((string) (isset($r['category_name']) ? $r['category_name'] : ''));
            $rMCls = strtolower($rMethod);
            if ($rMCls === 'post') { $rMCls = 'm-post'; }
            elseif ($rMCls === 'put') { $rMCls = 'm-put'; }
            elseif ($rMCls === 'delete' || $rMCls === 'del') { $rMCls = 'm-del'; }
            else { $rMCls = 'm-get'; }
            ?>
            <a class="th5-api-card-q<?php echo $rMaint ? ' is-maintenance' : ''; ?>" href="<?php echo vs_e($rUrl); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;">
              <div class="th5-api-card-q__head">
                <?php if ($rCat !== ''): ?>
                  <span class="th5-api-card-q__tag"><?php echo vs_e($rCat); ?></span>
                <?php else: ?>
                  <span class="th5-api-card-q__tag">推荐</span>
                <?php endif; ?>
                <span class="th5-api-card-q__method <?php echo $rMCls; ?>"><?php echo vs_e($rMethod); ?></span>
              </div>
              <h3 class="th5-api-card-q__name">
                <?php if ($rIcon !== ''): ?>
                  <img class="api-icon-img" src="<?php echo vs_e($rIcon); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">
                <?php endif; ?>
                <span><?php echo vs_e($rName !== '' ? $rName : ('接口 #' . $rId)); ?></span>
              </h3>
              <?php if ($rDesc !== ''): ?>
                <p class="th5-api-card-q__desc"><?php echo vs_e($rDesc); ?></p>
              <?php endif; ?>
              <div class="th5-api-card-q__foot">
                <span class="th5-api-card-q__calls"><i data-lucide="activity" style="width:13px;height:13px;"></i><?php echo vs_e($rCallsLabel); ?> 次调用</span>
                <span class="th5-api-card-q__tags">
                  <?php if ($rMaint): ?>
                    <span class="tag hot">维护中</span>
                  <?php elseif ($rPaid): ?>
                    <span class="tag points"><?php echo vs_e($rBilling); ?></span>
                  <?php else: ?>
                    <span class="tag free">免费</span>
                  <?php endif; ?>
                  <?php if (!$rMaint && $rNeed === 1): ?><span class="tag key">KEY必填</span><?php endif; ?>
                  <?php if (!$rMaint && $rNeed === 2): ?><span class="tag key">KEY可选</span><?php endif; ?>
                </span>
                <span class="th5-api-card-q__arrow"><i data-lucide="arrow-up-right" style="width:15px;height:15px;"></i></span>
              </div>
            </a>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<script>
<?php
$jsApi = is_array($pageApiSnapshot) ? $pageApiSnapshot : ((!$notFound && is_array($api)) ? $api : null);
?>
window.detailApiData = <?php echo json_encode($jsApi === null ? null : array(
    'id' => (int) $jsApi['id'],
    'name' => isset($jsApi['name']) ? $jsApi['name'] : '',
    'endpoint' => $isDisabled ? '' : $endpointCopy,
    'methods' => $methods,
    'method' => $primaryMethod,
    'maintenance' => !empty($jsApi['maintenance']) ? 1 : 0,
    'disabled' => $isDisabled ? 1 : 0,
    'needkey' => isset($jsApi['needkey']) ? (int) $jsApi['needkey'] : 0,
    'keyways' => $keywaysList,
    'params_list' => $paramsList,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.playgroundKeyContext = <?php echo json_encode(array(
    'loggedIn' => !empty($playground['loggedIn']),
    'apiKeyCount' => isset($playground['apiKeyCount']) ? (int) $playground['apiKeyCount'] : 0,
    'userCenterUrl' => isset($playground['userCenterUrl']) ? (string) $playground['userCenterUrl'] : ($vsBase . '/user/index'),
    'loginUrl' => isset($playground['loginUrl']) ? (string) $playground['loginUrl'] : ($vsBase . '/user/login'),
    'keysUrl' => isset($playground['keysUrl']) ? (string) $playground['keysUrl'] : vs_site_path('/core/front/playground-key.php'),
    'feedbackReady' => !empty($playground['feedbackReady']),
), JSON_UNESCAPED_UNICODE); ?>;
window.VS_CSRF_TOKEN = <?php echo json_encode(isset($playground['csrf']) ? (string) $playground['csrf'] : AuthSecurity::csrfToken()); ?>;
window.VS_PLAY_URL = <?php echo json_encode(isset($playground['playUrl']) ? (string) $playground['playUrl'] : (rtrim($vsBase, '/') . '/core/playground/relay.php')); ?>;
window.VS_BASE_URL = window.VS_BASE_URL || <?php echo json_encode(rtrim($vsBase, '/')); ?>;
</script>
<script type="application/json" id="detailPageMarkdownJson"><?php echo json_encode($detailPageMarkdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
<?php $vsSyntaxHref = ThemeManager::pageScriptUrl('vs-syntax.js'); if ($vsSyntaxHref !== ''): ?>
<script src="<?php echo vs_e($vsSyntaxHref); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo vs_e($vsBase); ?>/core/markdown/assets/js/markdown-render.js?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('fifth', 'assets/js/playground-response.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('fifth', 'assets/js/pages/detail-quickstart.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('fifth', 'assets/js/pages/detail.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
