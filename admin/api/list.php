<?php
/**
 * 文件：admin/api/list.php
 * 作用：接口列表（后台添加 / 编辑 / 状态管理）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $payloadFromPost = function () {
        return array(
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'endpoint'    => isset($_POST['endpoint']) ? (string) $_POST['endpoint'] : '',
            'apitype'     => isset($_POST['apitype']) ? (int) $_POST['apitype'] : ApiManager::APITYPE_LOCAL,
            'targeturl'   => isset($_POST['targeturl']) ? (string) $_POST['targeturl'] : '',
            'proxyslug'   => isset($_POST['proxyslug']) ? (string) $_POST['proxyslug'] : '',
            'method'      => isset($_POST['method']) ? $_POST['method'] : 'GET',
            'params'      => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response'    => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'         => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'       => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
            'needkey'     => isset($_POST['needkey']) ? (int) $_POST['needkey'] : 0,
            'charge'      => isset($_POST['charge']) ? (int) $_POST['charge'] : 0,
            'price'       => isset($_POST['price']) ? $_POST['price'] : 0,
            'status'      => isset($_POST['status']) ? $_POST['status'] : ApiManager::STATUS_NORMAL,
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'category'    => isset($_POST['category']) ? (string) $_POST['category'] : '',
        );
    };

    if ($action === 'get') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $row = ApiManager::findById($id);
        if (!$row) {
            AjaxResponse::error('接口不存在');
        }
        AjaxResponse::success('ok', array('api' => ApiManager::formatRow($row)));
    }

    if ($action === 'create') {
        $data = $payloadFromPost();
        $publishUid = AdminUserBinding::publishUserId((int) Auth::id());
        if (!is_int($publishUid)) {
            AjaxResponse::error((string) $publishUid);
        }
        // 使用绑定用户身份展示投稿者；审核默认通过，不进「待审核」
        $data['userid'] = $publishUid;
        $data['audit'] = ApiManager::AUDIT_APPROVED;
        $result = ApiManager::create($data);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('接口已添加', array(
            'api'         => $result,
            'api_summary' => ApiManager::formatRowSummary($result),
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $data = $payloadFromPost();
        // 管理员后台编辑不改审核态（本页无审核控件；发布侧一律视为已通过）
        $data['audit'] = ApiManager::AUDIT_APPROVED;
        $result = ApiManager::update($id, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiManager::findById($id);
        $formatted = ApiManager::formatRow($row);
        AjaxResponse::success('接口已保存', array(
            'api'         => $formatted,
            'api_summary' => ApiManager::formatRowSummary($formatted),
        ));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $status = ApiManager::normalizeStatus(isset($_POST['status']) ? $_POST['status'] : '');
        $result = ApiManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('状态已更新', array(
            'api_id'       => $id,
            'status'       => $status,
            'status_label' => ApiManager::statusLabel($status),
        ));
    }

    if ($action === 'set_audit') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $audit = ApiManager::normalizeAuditStatus(isset($_POST['audit']) ? $_POST['audit'] : '');
        $result = ApiManager::setAuditStatus($id, $audit);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('审核状态已更新', array(
            'api_id'      => $id,
            'audit'       => $audit,
            'audit_label' => ApiManager::auditStatusLabel($audit),
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $result = ApiManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('接口已删除', array('api_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiManager::tableReady();
$apis = $tableReady ? ApiManager::listAll() : array();
$defaultIconPaths = ApiCategoryManager::defaultIconPaths();
$iconBase = rtrim(vs_base_url(), '/');
$categories = ApiCategoryManager::tableReady() ? ApiCategoryManager::listEnabled() : array();

$countTotal = count($apis);
$countMaint = 0;
$countPending = 0;
foreach ($apis as $_row) {
    if ((int) (isset($_row['status']) ? $_row['status'] : 0) === ApiManager::STATUS_MAINTENANCE) {
        $countMaint += 1;
    }
    if (ApiManager::hasAuditColumn() && (int) (isset($_row['audit']) ? $_row['audit'] : 1) === ApiManager::AUDIT_PENDING) {
        $countPending += 1;
    }
}
$titleMeta = '当前接口总数 ' . $countTotal;
if ($countMaint > 0 || $countPending > 0) {
    if ($countMaint > 0) {
        $titleMeta .= '，维护中 ' . $countMaint;
    }
    if ($countPending > 0) {
        $titleMeta .= '，待审核 ' . $countPending;
    }
}

/**
 * @param array $row
 * @return void
 */
function vs_render_api_list_item(array $row)
{
    $api = ApiManager::formatRowSummary($row);
    if (!$api) {
        return;
    }
    $apiId = (int) $api['id'];
    $status = (int) $api['status'];
    $statusClass = 'is-normal';
    if ($status === ApiManager::STATUS_DISABLED) {
        $statusClass = 'is-disabled';
    } elseif ($status === ApiManager::STATUS_MAINTENANCE) {
        $statusClass = 'is-maintenance';
    }
    $auditStatus = isset($api['audit']) ? (int) $api['audit'] : ApiManager::AUDIT_APPROVED;
    $auditClass = isset($api['audit_class']) ? (string) $api['audit_class'] : ApiManager::auditStatusClass($auditStatus);
    $callUrl = isset($api['call_url']) ? (string) $api['call_url'] : (string) $api['endpoint'];
    $typeBadge = isset($api['apitype_badge']) ? (string) $api['apitype_badge'] : ApiManager::apiTypeBadge(isset($api['apitype']) ? $api['apitype'] : 0);
    $keyBadge = isset($api['needkey_badge']) ? (string) $api['needkey_badge'] : ApiManager::requireKeyBadge(isset($api['needkey']) ? $api['needkey'] : 0);
    $category = isset($api['category']) ? trim((string) $api['category']) : '';
    $username = isset($api['username']) ? trim((string) $api['username']) : '';
    if ($username === '') {
        if ((int) $api['userid'] > 0) {
            $username = '用户#' . (int) $api['userid'];
        } else {
            $bound = AdminUserBinding::getBoundUser((int) Auth::id());
            $username = ($bound && !empty($bound['username'])) ? (string) $bound['username'] : '管理员';
        }
    }
    $searchHay = mb_strtolower($api['name'] . ' ' . $callUrl . ' ' . $api['endpoint'] . ' ' . $category . ' ' . $typeBadge . ' ' . $username, 'UTF-8');
    $payloadJson = json_encode($api, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $methods = isset($api['methods']) && is_array($api['methods'])
        ? $api['methods']
        : ApiManager::normalizeMethods(isset($api['method']) ? $api['method'] : 'GET');
    $typeClass = ($typeBadge === '代理') ? 'vs-api-tag--proxy' : 'vs-api-tag--local';
    ?>
    <div class="vs-api-item"
         data-api-row="<?php echo $apiId; ?>"
         data-api-status="<?php echo $status; ?>"
         data-api-audit="<?php echo $auditStatus; ?>"
         data-search="<?php echo vs_e($searchHay); ?>"
         data-payload='<?php echo $payloadJson !== false ? $payloadJson : '{}'; ?>'>
        <div class="vs-api-item__icon">
            <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
        </div>
        <div class="vs-api-item__title">
            <span class="vs-api-item__name" data-field="name"><?php echo vs_e($api['name']); ?></span>
            <span class="vs-api-item__id" data-field="id">#<?php echo $apiId; ?></span>
        </div>
        <div class="vs-api-item__endpoint">
            <span class="vs-api-list-methods" data-field="method">
                <?php foreach ($methods as $m): ?>
                    <?php
                    $mSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $m));
                    if ($mSlug === '') {
                        $mSlug = 'get';
                    }
                    ?>
                    <span class="vs-api-list-method vs-api-list-method--<?php echo vs_e($mSlug); ?>"><?php echo vs_e(strtoupper((string) $m)); ?></span>
                <?php endforeach; ?>
            </span>
            <span class="vs-api-item__url" data-field="call_url" title="<?php echo vs_e($callUrl); ?>"><?php echo vs_e($callUrl); ?></span>
        </div>
        <div class="vs-api-item__tags" data-field="tags">
            <?php if ($category !== ''): ?>
                <span class="vs-api-tag vs-api-tag--cat" data-field="category"><?php echo vs_e($category); ?></span>
            <?php endif; ?>
            <span class="vs-api-tag vs-api-tag--free" data-field="charge_tag"><?php
                $charge = isset($api['charge']) ? (int) $api['charge'] : 0;
                $price = isset($api['price']) ? (string) $api['price'] : '0';
                if ($charge === 1 && (float) $price > 0) {
                    echo vs_e('每次 ' . $price . ' 积分');
                } else {
                    echo '免费';
                }
            ?></span>
            <?php if ($keyBadge !== ''): ?>
                <span class="vs-api-tag vs-api-tag--key" data-field="needkey_badge"><?php echo vs_e($keyBadge); ?></span>
            <?php endif; ?>
            <span class="vs-api-tag <?php echo $typeClass; ?>" data-field="apitype_badge"><?php echo vs_e($typeBadge); ?></span>
            <?php if ($auditStatus !== ApiManager::AUDIT_APPROVED): ?>
                <span class="vs-api-tag vs-api-tag--audit <?php echo $auditClass; ?>" data-field="audit_label"><?php echo vs_e(isset($api['audit_label']) ? $api['audit_label'] : ApiManager::auditStatusLabel($auditStatus)); ?></span>
            <?php endif; ?>
        </div>
        <div class="vs-api-item__meta">
            <div class="vs-api-item__status">
                状态：<span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>" data-field="status_label"><?php echo vs_e($api['status_label']); ?></span>
            </div>
            <div class="vs-api-item__calls" title="请求次数">请求：<strong data-field="calls"><?php echo (int) $api['calls']; ?></strong></div>
            <div class="vs-api-item__author" title="提交者">提交：<em data-field="username"><?php echo vs_e($username); ?></em></div>
        </div>
        <div class="vs-api-item__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-api-action="edit" data-api-id="<?php echo $apiId; ?>">编辑</button>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-normal vs-api-list-action<?php echo $status === ApiManager::STATUS_NORMAL ? ' is-active' : ''; ?>" data-api-action="normal" data-api-id="<?php echo $apiId; ?>">正常</button>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-maint vs-api-list-action<?php echo $status === ApiManager::STATUS_MAINTENANCE ? ' is-active' : ''; ?>" data-api-action="maintenance" data-api-id="<?php echo $apiId; ?>">维护</button>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-disabled vs-api-list-action<?php echo $status === ApiManager::STATUS_DISABLED ? ' is-active' : ''; ?>" data-api-action="disable" data-api-id="<?php echo $apiId; ?>">禁用</button>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="<?php echo $apiId; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-api-list-toolbar">
        <label class="vs-api-list-search" for="apiListSearchInput">
            <span class="vs-api-list-search__icon" aria-hidden="true"></span>
            <input type="search" class="vs-input vs-api-list-search__input" id="apiListSearchInput"
                   placeholder="搜索名称 / 地址 / 提交者" autocomplete="off">
        </label>
        <button type="button" class="vs-btn vs-btn--primary vs-api-list-add-btn" id="apiListOpenAddBtn">
            <span class="vs-api-list-add-btn__icon" aria-hidden="true">+</span>
            <span class="vs-api-list-add-btn__text">添加接口</span>
        </button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口列表', 'api-list', $headerActions);
?>

<div id="apiListPage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>"
     data-stats-total="<?php echo (int) $countTotal; ?>"
     data-stats-maint="<?php echo (int) $countMaint; ?>"
     data-stats-pending="<?php echo (int) $countPending; ?>">

    <div class="vs-panel vs-api-list-panel">
    <?php if (!$tableReady): ?>
        <div class="vs-api-list-upgrade">
            <?php vs_render_notice('warning', '', '接口管理功能尚未就绪，请先前往「系统升级」完成更新后再使用。', array('compact' => true)); ?>
            <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade'); ?>">前往系统升级</a>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip vs-api-list-tip--enter">
            <?php vs_render_notice('info', '', '正常：可对外提供服务。维护：站点前台仍可看到，但暂不可请求。禁用：站点前台不显示。未通过审核的接口也不会在站点前台展示。', array('compact' => true)); ?>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiListEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">请点击右上角「添加接口」进行配置（名称、地址、参数与文档等）。</p>
            </div>
        </div>

        <div class="vs-api-list-empty" id="apiListSearchEmpty" hidden>
            <?php vs_render_notice('info', '', '没有匹配的接口。', array('compact' => true)); ?>
        </div>

        <div class="vs-api-list-table" id="apiListTable"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-table__body" id="apiListBody">
                <?php foreach ($apis as $row): ?>
                    <?php vs_render_api_list_item($row); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    </div>

    <?php if ($tableReady): ?>
    <div class="vs-api-list-footer" id="apiListFooter"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
        <div class="vs-api-pager" id="apiListPager">
            <label class="vs-api-list-pagesize" for="apiListPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select vs-api-list-pagesize__select" id="apiListPageSize" data-vs-pick>
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <button type="button" class="vs-api-pager__nav" id="apiListPrevBtn" aria-label="上一页">上一页</button>
            <div class="vs-api-pager__nums" id="apiListPagerNums" role="navigation" aria-label="页码"></div>
            <button type="button" class="vs-api-pager__nav" id="apiListNextBtn" aria-label="下一页">下一页</button>
        </div>
        <p class="vs-api-list-stats" id="apiListStats"><?php echo vs_e($titleMeta); ?></p>
    </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--lg" id="apiListFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="apiListFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="apiListFormTitle">添加接口</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="apiListForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="apiListFormId" name="api_id" value="">

            <div class="vs-api-list-form-tabs" role="tablist">
                <button type="button" class="vs-api-list-form-tab is-active" data-api-form-tab="basic" role="tab" aria-selected="true">基础</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="params" role="tab" aria-selected="false">参数</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="docs" role="tab" aria-selected="false">文档</button>
            </div>

            <div class="vs-api-list-form-pane is-active" data-api-form-pane="basic">
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormName">接口名称 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormName" name="name" maxlength="100" required
                           placeholder="例如：天气查询">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormDesc">接口描述</label>
                    <textarea class="vs-input vs-textarea" id="apiListFormDesc" name="description" rows="3"
                              placeholder="简要说明接口用途"></textarea>
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormStatus">接口状态</label>
                        <select class="vs-input vs-select" id="apiListFormStatus" name="status" data-vs-pick>
                            <option value="0">正常</option>
                            <option value="2">维护</option>
                            <option value="1">禁用</option>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label">请求方式 <span class="vs-req">*</span></label>
                        <div class="vs-method-toggles" id="apiListFormMethodChecks" role="group" aria-label="请求方式">
                            <button type="button" class="vs-method-toggle is-on" data-api-method="GET" aria-pressed="true">GET</button>
                            <button type="button" class="vs-method-toggle" data-api-method="POST" aria-pressed="false">POST</button>
                        </div>
                        <p class="vs-form-hint">可同时选择 GET 与 POST。</p>
                    </div>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label">接口类型</label>
                    <div class="vs-api-type-tabs" id="apiListTypeTabs" role="tablist">
                        <button type="button" class="vs-btn vs-btn--primary vs-api-type-tab" data-apitype="0">本地接口</button>
                        <button type="button" class="vs-btn vs-btn--default vs-api-type-tab" data-apitype="1">代理外链</button>
                    </div>
                    <input type="hidden" id="apiListFormApiType" name="apitype" value="0">
                    <p class="vs-form-hint" id="apiListTypeHint">本地接口：只填本站路径，如 /api/img/index.php</p>
                </div>
                <div class="vs-form-row" id="apiListEndpointRow">
                    <label class="vs-label" for="apiListFormEndpoint" id="apiListEndpointLabel">本地路径 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormEndpoint" name="endpoint" maxlength="500" required
                           placeholder="/api/img/index.php">
                </div>
                <div class="vs-form-row" id="apiListTargetRow" hidden>
                    <label class="vs-label" for="apiListFormTargetUrl">上游完整地址 <span class="vs-req">*</span></label>
                    <input type="url" class="vs-input" id="apiListFormTargetUrl" name="targeturl" maxlength="500"
                           placeholder="https://api.example.com/v1/demo">
                    <p class="vs-form-hint">访问本站公开地址时，将跳转到该上游，并附带查询参数。</p>
                </div>
                <div class="vs-form-row" id="apiListSlugRow" hidden>
                    <label class="vs-label" for="apiListFormProxySlug">接口短码 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormProxySlug" name="proxyslug" maxlength="64"
                           placeholder="例如 sjspks（3～64 位字母或数字）" pattern="[A-Za-z0-9]{3,64}" autocomplete="off">
                    <p class="vs-form-hint">公开地址形如 <?php echo vs_e(rtrim(vs_base_url(), '/')); ?>/apis/短码</p>
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormCategory">所属分类</label>
                        <select class="vs-input vs-select" id="apiListFormCategory" name="category" data-vs-pick>
                            <option value="">未分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label" for="apiListFormRequireKey">是否需要密钥</label>
                        <select class="vs-input vs-select" id="apiListFormRequireKey" name="needkey" data-vs-pick>
                            <option value="0">完全不需要</option>
                            <option value="1">必须需要</option>
                            <option value="2">可选（可填可不填）</option>
                        </select>
                    </div>
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormCharge">是否收费</label>
                        <select class="vs-input vs-select" id="apiListFormCharge" name="charge" data-vs-pick>
                            <option value="0">免费</option>
                            <option value="1">收费</option>
                        </select>
                    </div>
                    <div id="apiListPriceRow" hidden>
                        <label class="vs-label" for="apiListFormPrice">每次扣除积分</label>
                        <input type="number" class="vs-input" id="apiListFormPrice" name="price" min="0.0001" step="0.0001" placeholder="如 0.1 或 1">
                    </div>
                </div>
                <p class="vs-form-hint">「完全不需要」与「可选」调用规则相同；选「完全不需要」时前台通常不展示密钥填写框，「可选」会展示可空输入。本页发布的接口默认审核通过。收费接口调用时须提供有效密钥且余额足够。</p>
                <div class="vs-form-row">
                    <label class="vs-label">接口图标</label>
                    <div class="vs-api-cat-icon-picker" id="apiListIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                    <label class="vs-label vs-api-cat-icon-url-label" for="apiListIconUrl">或填写图标链接</label>
                    <input type="url" class="vs-input" id="apiListIconUrl" name="icon"
                           placeholder="https://example.com/icon.png" maxlength="255">
                    <p class="vs-form-hint">点选下方图标，或填写图片链接地址。</p>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="params" hidden>
                <div class="vs-form-row">
                    <label class="vs-label">请求参数</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormParams" name="params" hidden aria-hidden="true"></textarea>
                    <div class="vs-params-editor" id="apiListParamsEditor" data-hidden-id="apiListFormParams"></div>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormResponse">返回参数示例</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormResponse" name="response" rows="8"
                              placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
                    <p class="vs-form-hint">返回示例保持 JSON 文本填写即可。</p>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="docs" hidden>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormDocNormal">普通文档</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormDocNormal" name="doc" rows="10"
                              placeholder="面向普通用户的接口说明…"></textarea>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormDocAi">AI 文档</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormDocAi" name="aidoc" rows="10"
                              placeholder="面向 AI / Agent 的结构化说明…"></textarea>
                </div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="apiListForm" class="vs-btn vs-btn--primary" id="apiListFormSubmitBtn">保存</button>
        </footer>
    </div>
</div>

<?php vs_admin_layout_end(array('vs-pick.js', 'icon-picker.js', 'api-params-editor.js', 'api-list.js')); ?>
