<?php
/**
 * 文件：user/api-manage.php
 * 作用：开发者 API 管理（提交接口、查看审核状态与未通过原因）
 */

require_once __DIR__ . '/init.php';

vs_user_require_developer('API 管理');

$userId = (int) UserAuth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!UserRole::currentCanPublishApi()) {
        AjaxResponse::error('无权操作', 403);
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $payloadFromPost = function () {
        return array(
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'endpoint'    => isset($_POST['endpoint']) ? (string) $_POST['endpoint'] : '',
            'method'      => isset($_POST['method']) ? (string) $_POST['method'] : 'GET',
            'params'      => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response'    => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'         => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'       => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
            'needkey'     => isset($_POST['needkey']) ? (int) $_POST['needkey'] : 0,
            'status'      => ApiManager::STATUS_NORMAL,
            'audit'       => ApiManager::AUDIT_PENDING,
            'rejectreason'=> '',
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'category'    => isset($_POST['category']) ? (string) $_POST['category'] : '',
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
        if (!ApiManager::hasAuditColumn() || !ApiManager::hasRejectReasonColumn()) {
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
        if (!$mail['ok']) {
            $msg .= $mail['error'] !== '' ? ('（管理员邮件未送达：' . $mail['error'] . '）') : '（管理员邮件未送达）';
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
        if (!$mail['ok']) {
            $msg .= $mail['error'] !== '' ? ('（管理员邮件未送达：' . $mail['error'] . '）') : '（管理员邮件未送达）';
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

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiManager::tableReady() && ApiManager::hasAuditColumn() && ApiManager::hasRejectReasonColumn();
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
    $payloadJson = json_encode($api, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="vs-user-api-row" data-api-row="<?php echo $apiId; ?>"
         data-payload='<?php echo $payloadJson !== false ? $payloadJson : '{}'; ?>'>
        <div class="vs-user-api-row__main">
            <div class="vs-user-api-row__title">
                <strong data-field="name"><?php echo vs_e($api['name']); ?></strong>
                <span class="vs-api-list-audit <?php echo vs_e($api['audit_class']); ?>" data-field="audit_label">
                    <?php echo vs_e($api['audit_label']); ?>
                </span>
            </div>
            <div class="vs-user-api-row__meta">
                <span data-field="method"><?php echo vs_e($api['method']); ?></span>
                ·
                <span data-field="endpoint"><?php echo vs_e($api['endpoint']); ?></span>
            </div>
            <p class="vs-user-api-row__reason" data-field="rejectreason"<?php echo $reason === '' ? ' hidden' : ''; ?>>
                未通过原因：<?php echo vs_e($reason); ?>
            </p>
        </div>
        <div class="vs-user-api-row__actions">
            <button type="button" class="vs-btn vs-btn--default vs-user-api-edit" data-api-id="<?php echo $apiId; ?>">编辑</button>
            <button type="button" class="vs-btn vs-btn--danger vs-user-api-delete" data-api-id="<?php echo $apiId; ?>">删除</button>
        </div>
    </div>
    <?php
}

vs_user_layout_start('API 管理', 'api-manage');
echo '<link rel="stylesheet" href="' . vs_e($iconBase) . '/assets/css/admin.css?v=' . VS_VERSION . '">' . "\n";
?>

<div class="vs-panel" id="userApiManagePage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '接口投稿功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
    <?php else: ?>
        <?php vs_render_notice('info', '', '提交后需管理员审核。通过后会出现在站点前台，并显示在本页列表中。修改已有接口将重新进入待审核。', array('compact' => true)); ?>

        <div class="vs-user-api-toolbar">
            <button type="button" class="vs-btn vs-btn--primary" id="userApiAddBtn">提交接口</button>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="userApiEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">点击上方「提交接口」，填写名称、地址与文档后等待审核。</p>
            </div>
        </div>

        <div class="vs-user-api-list" id="userApiList"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($apis as $row): ?>
                <?php vs_render_user_api_item($row); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--lg" id="userApiFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="userApiFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="userApiFormTitle">提交接口</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="userApiForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" id="userApiFormId" name="api_id" value="">

            <div class="vs-form-row">
                <label class="vs-label">接口图标</label>
                <div class="vs-api-cat-icon-picker" id="userApiIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                <label class="vs-label vs-api-cat-icon-url-label" for="userApiIconUrl">或填写图标链接</label>
                <input type="url" class="vs-input" id="userApiIconUrl" name="icon"
                       placeholder="https://example.com/icon.png" maxlength="255">
                <p class="vs-form-hint">点选下方图标，或填写图片链接地址。</p>
            </div>
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
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="userApiFormMethod">请求方式 <span class="vs-req">*</span></label>
                    <select class="vs-input vs-select" id="userApiFormMethod" name="method">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                    </select>
                </div>
                <div>
                    <label class="vs-label" for="userApiFormNeedkey">密钥要求</label>
                    <select class="vs-input vs-select" id="userApiFormNeedkey" name="needkey">
                        <option value="0">不需要</option>
                        <option value="1">必须需要</option>
                        <option value="2">可选（可填可不填）</option>
                    </select>
                </div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormEndpoint">接口地址 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormEndpoint" name="endpoint" maxlength="500" required
                       placeholder="https://api.example.com/v1/demo">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormCategory">所属分类</label>
                <select class="vs-input vs-select" id="userApiFormCategory" name="category">
                    <option value="">未分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormParams">请求参数（JSON 数组）</label>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="userApiFormParams" name="params" rows="6"
                          placeholder='[{"name":"q","type":"string","required":true,"description":"关键词"}]'></textarea>
                <p class="vs-form-hint">留空表示无参数。请按示例填写 JSON 数组。</p>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormResponse">返回参数示例</label>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="userApiFormResponse" name="response" rows="5"
                          placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormDoc">普通文档</label>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="userApiFormDoc" name="doc" rows="6"
                          placeholder="面向普通用户的接口说明…"></textarea>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormAidoc">AI 文档</label>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="userApiFormAidoc" name="aidoc" rows="6"
                          placeholder="面向 AI / Agent 的结构化说明…"></textarea>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userApiForm" class="vs-btn vs-btn--primary" id="userApiFormSubmitBtn">提交审核</button>
        </footer>
    </div>
</div>

<style>
.vs-user-api-toolbar { margin: 12px 0 16px; }
.vs-user-api-list { display: flex; flex-direction: column; gap: 12px; }
.vs-user-api-row {
    display: flex; gap: 16px; align-items: flex-start; justify-content: space-between;
    padding: 16px; border: 1px solid var(--vs-border, #e2e8f0); border-radius: 12px; background: #fff;
}
.vs-user-api-row__main { flex: 1; min-width: 0; }
.vs-user-api-row__title { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 6px; }
.vs-user-api-row__meta { font-size: 13px; color: #64748b; word-break: break-all; }
.vs-user-api-row__reason { margin: 8px 0 0; font-size: 13px; color: #b45309; }
.vs-user-api-row__actions { display: flex; gap: 8px; flex-shrink: 0; }
@media (max-width: 640px) {
    .vs-user-api-row { flex-direction: column; }
}
</style>
<?php endif; ?>

<?php
vs_user_layout_end($tableReady ? array('modal.js', 'icon-picker.js', 'user-api-manage.js') : array());
