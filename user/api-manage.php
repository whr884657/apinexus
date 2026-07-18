<?php
/**
 * 文件：user/api-manage.php
 * 作用：开发者 API 管理（提交接口、查看审核状态）
 *
 * 权限：未绑定管理员 → 仅可投代理外链；已绑定 → 与后台相同可选本地/代理。
 */

require_once __DIR__ . '/init.php';

vs_user_require_developer('API 管理');

$userId = (int) UserAuth::id();
$canLocal = AdminUserBinding::isUserBoundToAdmin($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!UserRole::currentCanPublishApi()) {
        AjaxResponse::error('无权操作', 403);
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $payloadFromPost = function () use ($canLocal) {
        $apitype = isset($_POST['apitype']) ? (int) $_POST['apitype'] : ApiManager::APITYPE_PROXY;
        if (!$canLocal) {
            $apitype = ApiManager::APITYPE_PROXY;
        }
        return array(
            'name'         => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'description'  => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'endpoint'     => isset($_POST['endpoint']) ? (string) $_POST['endpoint'] : '',
            'apitype'      => $apitype,
            'targeturl'    => isset($_POST['targeturl']) ? (string) $_POST['targeturl'] : '',
            'proxyslug'    => isset($_POST['proxyslug']) ? (string) $_POST['proxyslug'] : '',
            'method'       => isset($_POST['method']) ? $_POST['method'] : 'GET',
            'params'       => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response'     => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'          => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'        => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
            'needkey'      => isset($_POST['needkey']) ? (int) $_POST['needkey'] : 0,
            'charge'       => isset($_POST['charge']) ? (int) $_POST['charge'] : 0,
            'price'        => isset($_POST['price']) ? $_POST['price'] : 0,
            'status'       => ApiManager::STATUS_NORMAL,
            'audit'        => ApiManager::AUDIT_PENDING,
            'rejectreason' => '',
            'icon'         => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'category'     => isset($_POST['category']) ? (string) $_POST['category'] : '',
        );
    };

    $assertOwner = function ($apiId) use ($userId) {
        $row = ApiManager::findById($apiId);
        if (!$row) {
            return '接口不存在';
        }
        if ((int) $row['userid'] !== $userId) {
            return '无权操作该接口';
        }
        return $row;
    };

    if ($action === 'get') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $row = $assertOwner($id);
        if (!is_array($row)) {
            AjaxResponse::error($row);
        }
        AjaxResponse::success('ok', array('api' => ApiManager::formatRow($row)));
    }

    if ($action === 'create') {
        if (!ApiManager::hasAuditColumn() || !ApiManager::hasRejectReasonColumn() || !ApiManager::hasProxyColumns()) {
            AjaxResponse::error('请先联系管理员完成系统升级后再提交接口');
        }
        $data = $payloadFromPost();
        $data['userid'] = $userId;
        $data['audit'] = ApiManager::AUDIT_PENDING;
        $result = ApiManager::create($data);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        $mail = ApiNotify::notifyAdminsPending($result);
        $msg = '已提交，等待管理员审核';
        if (!$mail['ok'] && $mail['error'] !== '' && strpos($mail['error'], '已关闭') === false) {
            $msg .= '（管理员邮件未送达：' . $mail['error'] . '）';
        }
        AjaxResponse::success($msg, array(
            'api'         => $result,
            'api_summary' => ApiManager::formatRowSummary($result),
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $data = $payloadFromPost();
        $data['audit'] = ApiManager::AUDIT_PENDING;
        $data['rejectreason'] = '';
        $result = ApiManager::update($id, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiManager::findById($id);
        $formatted = ApiManager::formatRow($row);
        $mail = ApiNotify::notifyAdminsPending($formatted);
        $msg = '已保存并重新提交审核';
        if (!$mail['ok'] && $mail['error'] !== '' && strpos($mail['error'], '已关闭') === false) {
            $msg .= '（管理员邮件未送达：' . $mail['error'] . '）';
        }
        AjaxResponse::success($msg, array(
            'api'         => $formatted,
            'api_summary' => ApiManager::formatRowSummary($formatted),
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $result = ApiManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('接口已删除', array('api_id' => $id));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $audit = ApiManager::normalizeAuditStatus(isset($owned['audit']) ? $owned['audit'] : ApiManager::AUDIT_PENDING);
        if ($audit !== ApiManager::AUDIT_APPROVED) {
            AjaxResponse::error('仅审核通过的接口可调整运行状态');
        }
        $status = ApiManager::normalizeStatus(isset($_POST['status']) ? $_POST['status'] : '');
        $result = ApiManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiManager::findById($id);
        AjaxResponse::success('状态已更新', array(
            'api_id'       => $id,
            'status'       => $status,
            'status_label' => ApiManager::statusLabel($status),
            'api_summary'  => ApiManager::formatRowSummary($row),
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiManager::tableReady()
    && ApiManager::hasAuditColumn()
    && ApiManager::hasRejectReasonColumn()
    && ApiManager::hasProxyColumns();
$apis = $tableReady ? ApiManager::listByUser($userId) : array();
$categories = ApiCategoryManager::tableReady() ? ApiCategoryManager::listEnabled() : array();
$defaultIconPaths = ApiCategoryManager::defaultIconPaths();
$iconBase = rtrim(vs_base_url(), '/');

/**
 * @param array $row
 * @return void
 */
function vs_render_user_api_item(array $row)
{
    $api = ApiManager::formatRowSummary($row);
    if (!$api) {
        return;
    }
    $apiId = (int) $api['id'];
    $reason = isset($api['rejectreason']) ? trim((string) $api['rejectreason']) : '';
    $callUrl = isset($api['call_url']) ? (string) $api['call_url'] : (string) $api['endpoint'];
    $audit = (int) $api['audit'];
    $rowStatus = isset($api['status']) ? (int) $api['status'] : ApiManager::STATUS_NORMAL;
    $rowStatusClass = 'is-normal';
    if ($rowStatus === ApiManager::STATUS_DISABLED) {
        $rowStatusClass = 'is-disabled';
    } elseif ($rowStatus === ApiManager::STATUS_MAINTENANCE) {
        $rowStatusClass = 'is-maintenance';
    }
    $methods = isset($api['methods']) && is_array($api['methods'])
        ? $api['methods']
        : ApiManager::normalizeMethods(isset($api['method']) ? $api['method'] : 'GET');
    $approved = $audit === ApiManager::AUDIT_APPROVED;
    $keyBadge = isset($api['needkey_badge']) ? (string) $api['needkey_badge'] : ApiManager::requireKeyBadge(isset($api['needkey']) ? $api['needkey'] : 0);
    $category = isset($api['category']) ? trim((string) $api['category']) : '';
    ?>
    <div class="vs-api-item vs-user-api-row" data-api-row="<?php echo $apiId; ?>" data-api-status="<?php echo $rowStatus; ?>" data-api-audit="<?php echo $audit; ?>">
        <div class="vs-api-item__icon">
            <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer">
        </div>
        <div class="vs-api-item__title">
            <span class="vs-api-item__name" data-field="name"><?php echo vs_e($api['name']); ?></span>
            <span class="vs-api-item__id">#<?php echo $apiId; ?></span>
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
        <div class="vs-api-item__tags">
            <?php if ($category !== ''): ?>
                <span class="vs-api-tag vs-api-tag--cat"><?php echo vs_e($category); ?></span>
            <?php endif; ?>
            <span class="vs-api-tag vs-api-tag--free" data-field="charge_tag"><?php
                $charge = isset($api['charge']) ? (int) $api['charge'] : 0;
                $price = isset($api['price']) ? (string) $api['price'] : '0';
                echo ($charge === 1 && (float) $price > 0) ? vs_e('每次 ' . $price . ' 积分') : '免费';
            ?></span>
            <?php if ($keyBadge !== ''): ?>
                <span class="vs-api-tag vs-api-tag--key"><?php echo vs_e($keyBadge); ?></span>
            <?php endif; ?>
            <?php if (!$approved): ?>
                <span class="vs-api-tag vs-api-tag--audit <?php echo vs_e($api['audit_class']); ?>" data-field="audit_label"><?php echo vs_e($api['audit_label']); ?></span>
            <?php endif; ?>
        </div>
        <div class="vs-api-item__meta">
            <div class="vs-api-item__status">
                <?php if ($approved): ?>
                    状态：<span class="vs-api-tag vs-api-tag--status <?php echo $rowStatusClass; ?>" data-field="status_label"><?php echo vs_e($api['status_label']); ?></span>
                <?php else: ?>
                    <span data-field="status_label"></span>
                <?php endif; ?>
            </div>
            <div class="vs-api-item__calls" title="请求次数">请求：<strong data-field="calls"><?php echo (int) $api['calls']; ?></strong></div>
            <div class="vs-api-item__author"></div>
        </div>
        <p class="vs-api-review-reason vs-user-api-row__reason" data-field="rejectreason"<?php echo $reason === '' ? ' hidden' : ''; ?>>
            未通过原因：<?php echo vs_e($reason); ?>
        </p>
        <div class="vs-api-item__actions vs-user-api-row__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-user-api-edit" data-api-id="<?php echo $apiId; ?>">编辑</button>
            <?php if ($approved): ?>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-normal vs-user-api-status<?php echo $rowStatus === ApiManager::STATUS_NORMAL ? ' is-active' : ''; ?>" data-api-id="<?php echo $apiId; ?>" data-status="0">正常</button>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-maint vs-user-api-status<?php echo $rowStatus === ApiManager::STATUS_MAINTENANCE ? ' is-active' : ''; ?>" data-api-id="<?php echo $apiId; ?>" data-status="2">维护</button>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-disabled vs-user-api-status<?php echo $rowStatus === ApiManager::STATUS_DISABLED ? ' is-active' : ''; ?>" data-api-id="<?php echo $apiId; ?>" data-status="1">禁用</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-user-api-delete" data-api-id="<?php echo $apiId; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    $headerActions = '<button type="button" class="vs-btn vs-btn--primary" id="userApiAddBtn">提交接口</button>';
}

vs_user_layout_start('API 管理', 'api-manage', $headerActions);
?>

<div class="vs-panel" id="userApiManagePage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-can-local="<?php echo $canLocal ? '1' : '0'; ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '接口投稿功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
    <?php else: ?>
        <?php
        $tip = $canLocal
            ? '可提交本地接口或代理外链。提交后需管理员审核；修改后将重新进入待审核。'
            : '当前账号仅可提交代理外链接口（对方完整地址）。提交后需管理员审核；修改后将重新进入待审核。';
        vs_render_notice('info', '', $tip, array('compact' => true));
        ?>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="userApiEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">点击右上角「提交接口」，填写信息后等待审核。</p>
            </div>
        </div>

        <div class="vs-api-list-table vs-user-api-list" id="userApiList"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-table__body">
                <?php foreach ($apis as $row): ?>
                    <?php vs_render_user_api_item($row); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-api-list-footer" id="userApiFooter"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
    <div class="vs-api-pager" id="userApiPager">
        <label class="vs-api-list-pagesize" for="userApiPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select vs-api-list-pagesize__select" id="userApiPageSize" data-vs-pick>
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
            </select>
        </label>
        <button type="button" class="vs-api-pager__nav" id="userApiPrevBtn" aria-label="上一页">上一页</button>
        <div class="vs-api-pager__nums" id="userApiPagerNums" role="navigation" aria-label="页码"></div>
        <button type="button" class="vs-api-pager__nav" id="userApiNextBtn" aria-label="下一页">下一页</button>
    </div>
    <p class="vs-api-list-stats" id="userApiStats">共 <?php echo (int) count($apis); ?> 个接口</p>
</div>
<?php endif; ?>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--lg" id="userApiFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="userApiFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="userApiFormTitle">提交接口</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="userApiForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="userApiFormId" name="api_id" value="">
            <input type="hidden" id="userApiFormApiType" name="apitype" value="<?php echo $canLocal ? '0' : '1'; ?>">

            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormName">接口名称 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormName" name="name" maxlength="100" required
                       placeholder="例如：天气查询">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormDesc">接口描述</label>
                <textarea class="vs-input vs-textarea" id="userApiFormDesc" name="description" rows="3"
                          placeholder="简要说明接口用途"></textarea>
            </div>

            <?php if ($canLocal): ?>
            <div class="vs-form-row">
                <label class="vs-label">接口类型</label>
                <div class="vs-api-type-tabs" id="userApiTypeTabs">
                    <button type="button" class="vs-btn vs-btn--primary vs-user-api-type-tab" data-apitype="0">本地接口</button>
                    <button type="button" class="vs-btn vs-btn--default vs-user-api-type-tab" data-apitype="1">代理外链</button>
                </div>
                <p class="vs-form-hint" id="userApiTypeHint">本地接口：只填本站路径，如 /api/img/index.php</p>
            </div>
            <?php else: ?>
            <p class="vs-form-hint">本账号仅支持提交外链接口：填写对方完整地址与短码，系统生成本站公开地址。</p>
            <?php endif; ?>

            <div class="vs-form-row" id="userApiEndpointRow"<?php echo $canLocal ? '' : ' hidden'; ?>>
                <label class="vs-label" for="userApiFormEndpoint">本地路径 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormEndpoint" name="endpoint" maxlength="500"
                       placeholder="/api/img/index.php">
            </div>
            <div class="vs-form-row" id="userApiTargetRow"<?php echo $canLocal ? ' hidden' : ''; ?>>
                <label class="vs-label" for="userApiFormTargetUrl">上游完整地址 <span class="vs-req">*</span></label>
                <input type="url" class="vs-input" id="userApiFormTargetUrl" name="targeturl" maxlength="500"
                       placeholder="https://api.example.com/v1/demo" <?php echo $canLocal ? '' : 'required'; ?>>
            </div>
            <div class="vs-form-row" id="userApiSlugRow"<?php echo $canLocal ? ' hidden' : ''; ?>>
                <label class="vs-label" for="userApiFormProxySlug">接口短码 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormProxySlug" name="proxyslug" maxlength="64"
                       placeholder="例如 sjspks（3～64 位字母或数字）" pattern="[A-Za-z0-9]{3,64}"
                       autocomplete="off" <?php echo $canLocal ? '' : 'required'; ?>>
                <p class="vs-form-hint">公开地址：<?php echo vs_e($iconBase); ?>/apis/短码</p>
            </div>

            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label">请求方式</label>
                    <div class="vs-method-toggles" id="userApiFormMethodChecks" role="group" aria-label="请求方式">
                        <button type="button" class="vs-method-toggle is-on" data-api-method="GET" aria-pressed="true">GET</button>
                        <button type="button" class="vs-method-toggle" data-api-method="POST" aria-pressed="false">POST</button>
                    </div>
                    <p class="vs-form-hint">可同时选择 GET 与 POST。</p>
                </div>
                <div>
                    <label class="vs-label" for="userApiFormNeedkey">密钥要求</label>
                    <select class="vs-input vs-select" id="userApiFormNeedkey" name="needkey" data-vs-pick>
                        <option value="0">完全不需要</option>
                        <option value="1">必须需要</option>
                        <option value="2">可选（可填可不填）</option>
                    </select>
                    <p class="vs-form-hint">「完全不需要」与「可选」调用规则相同；选「完全不需要」时前台通常不展示密钥填写框。</p>
                </div>
            </div>
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="userApiFormCharge">是否收费</label>
                    <select class="vs-input vs-select" id="userApiFormCharge" name="charge" data-vs-pick>
                        <option value="0">免费</option>
                        <option value="1">收费</option>
                    </select>
                </div>
                <div id="userApiPriceRow" hidden>
                    <label class="vs-label" for="userApiFormPrice">每次扣除积分</label>
                    <input type="number" class="vs-input" id="userApiFormPrice" name="price" min="0.0001" step="0.0001" placeholder="如 0.1 或 1">
                </div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormCategory">所属分类</label>
                <select class="vs-input vs-select" id="userApiFormCategory" name="category" data-vs-pick>
                    <option value="">未分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vs-form-row">
                <label class="vs-label">请求参数</label>
                <textarea class="vs-input vs-textarea" id="userApiFormParams" name="params" hidden aria-hidden="true"></textarea>
                <div class="vs-params-editor" id="userApiParamsEditor" data-hidden-id="userApiFormParams"></div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormResponse">返回参数示例</label>
                <textarea class="vs-input vs-textarea" id="userApiFormResponse" name="response" rows="4"
                          placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
                <p class="vs-form-hint">返回示例保持 JSON 文本填写即可。</p>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormDoc">普通文档</label>
                <textarea class="vs-input vs-textarea" id="userApiFormDoc" name="doc" rows="5"></textarea>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormAidoc">AI 文档</label>
                <textarea class="vs-input vs-textarea" id="userApiFormAidoc" name="aidoc" rows="5"></textarea>
            </div>
            <div class="vs-form-row">
                <label class="vs-label">接口图标</label>
                <div class="vs-api-cat-icon-picker" id="userApiIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                <label class="vs-label vs-api-cat-icon-url-label" for="userApiIconUrl">或填写图标链接</label>
                <input type="url" class="vs-input" id="userApiIconUrl" name="icon"
                       placeholder="https://example.com/icon.png" maxlength="255">
                <p class="vs-form-hint">点选下方图标，或填写图片链接地址。</p>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userApiForm" class="vs-btn vs-btn--primary" id="userApiFormSubmitBtn">提交审核</button>
        </footer>
    </div>
</div>

<style>
.vs-api-type-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.vs-user-api-list { margin-top: 12px; }
.vs-user-api-row__reason {
    margin: 0;
    font-size: 12px;
    color: #b45309;
}
.vs-user-api-row__reason[hidden] { display: none !important; }
</style>
<?php endif; ?>

<?php
vs_user_layout_end($tableReady ? array('vs-pick.js', 'icon-picker.js', 'api-params-editor.js', 'user-api-manage.js') : array());
