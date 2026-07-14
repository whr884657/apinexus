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
            'name'             => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'description'      => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'endpoint'         => isset($_POST['endpoint']) ? (string) $_POST['endpoint'] : '',
            'method'           => isset($_POST['method']) ? (string) $_POST['method'] : 'GET',
            'params'   => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response' => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'      => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'    => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
            'needkey'  => isset($_POST['needkey']) ? (int) $_POST['needkey'] : 0,
            'status'   => isset($_POST['status']) ? $_POST['status'] : ApiManager::STATUS_NORMAL,
            'audit'    => isset($_POST['audit'])
                ? (int) $_POST['audit']
                : ApiManager::AUDIT_APPROVED,
            'icon'             => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'category'         => isset($_POST['category']) ? (string) $_POST['category'] : '',
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
        $admin = Auth::user();
        $data['userid'] = ($admin && !empty($admin['binduid'])) ? (int) $admin['binduid'] : 0;
        // 管理员后台发布：默认审核通过
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
        $result = ApiManager::update($id, $payloadFromPost());
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
    $desc = trim((string) $api['description']);
    $payloadJson = json_encode($api, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="vs-api-list-row"
         data-api-row="<?php echo $apiId; ?>"
         data-api-status="<?php echo $status; ?>"
         data-api-audit="<?php echo $auditStatus; ?>"
         data-search="<?php echo vs_e(mb_strtolower($api['name'] . ' ' . $api['endpoint'] . ' ' . $api['category'], 'UTF-8')); ?>"
         data-payload='<?php echo $payloadJson !== false ? $payloadJson : '{}'; ?>'>
        <div class="vs-api-list-row__icon">
            <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" data-field="icon">
        </div>
        <div class="vs-api-list-row__main">
            <div class="vs-api-list-row__name" data-field="name"><?php echo vs_e($api['name']); ?></div>
            <div class="vs-api-list-row__desc" data-field="description">
                <?php if ($desc !== ''): ?>
                    <?php echo vs_e($desc); ?>
                <?php else: ?>
                    <span class="vs-api-list-row__empty">—</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="vs-api-list-row__method">
            <span class="vs-api-list-method vs-api-list-method--<?php echo vs_e(strtolower($api['method'])); ?>" data-field="method">
                <?php echo vs_e($api['method']); ?>
            </span>
        </div>
        <div class="vs-api-list-row__endpoint" data-field="endpoint" title="<?php echo vs_e($api['endpoint']); ?>">
            <?php echo vs_e($api['endpoint']); ?>
        </div>
        <div class="vs-api-list-row__calls" data-field="calls"><?php echo (int) $api['calls']; ?></div>
        <div class="vs-api-list-row__key" data-field="needkey_label">
            <?php echo vs_e(isset($api['needkey_label']) ? $api['needkey_label'] : ApiManager::requireKeyLabel($api['needkey'])); ?>
        </div>
        <div class="vs-api-list-row__status">
            <span class="vs-api-list-status <?php echo $statusClass; ?>" data-field="status_label">
                <?php echo vs_e($api['status_label']); ?>
            </span>
            <span class="vs-api-list-audit <?php echo $auditClass; ?>" data-field="audit_label">
                <?php echo vs_e(isset($api['audit_label']) ? $api['audit_label'] : ApiManager::auditStatusLabel($auditStatus)); ?>
            </span>
        </div>
        <div class="vs-api-list-row__actions">
            <button type="button" class="vs-btn vs-btn--default vs-api-list-action"
                    data-api-action="edit" data-api-id="<?php echo $apiId; ?>">编辑</button>
            <?php if ($status !== ApiManager::STATUS_NORMAL): ?>
                <button type="button" class="vs-btn vs-btn--default vs-api-list-action"
                        data-api-action="normal" data-api-id="<?php echo $apiId; ?>">正常</button>
            <?php endif; ?>
            <?php if ($status !== ApiManager::STATUS_MAINTENANCE): ?>
                <button type="button" class="vs-btn vs-btn--default vs-api-list-action"
                        data-api-action="maintenance" data-api-id="<?php echo $apiId; ?>">维护</button>
            <?php endif; ?>
            <?php if ($status !== ApiManager::STATUS_DISABLED): ?>
                <button type="button" class="vs-btn vs-btn--default vs-api-list-action"
                        data-api-action="disable" data-api-id="<?php echo $apiId; ?>">禁用</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--danger vs-api-list-action"
                    data-api-action="delete" data-api-id="<?php echo $apiId; ?>">删除</button>
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
                   placeholder="搜索名称 / 地址" autocomplete="off">
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

<div class="vs-panel vs-api-list-panel" id="apiListPage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>">

    <?php if (!$tableReady): ?>
        <div class="vs-api-list-upgrade">
            <?php vs_render_notice('warning', '', '接口管理功能尚未就绪，请先前往「系统升级」完成更新后再使用。', array('compact' => true)); ?>
            <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip vs-api-list-tip--enter">
            <?php vs_render_notice('info', '', '正常：可对外提供服务。维护：站点前台仍可看到，但暂不可请求。禁用：站点前台不显示。未通过审核的接口也不会在站点前台展示。在本页发布时，默认设为审核通过。', array('compact' => true)); ?>
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
            <div class="vs-api-list-table__head" aria-hidden="true">
                <span class="vs-api-list-table__col vs-api-list-table__col--icon"></span>
                <span class="vs-api-list-table__col">名称</span>
                <span class="vs-api-list-table__col vs-api-list-table__col--method">方法</span>
                <span class="vs-api-list-table__col">地址</span>
                <span class="vs-api-list-table__col vs-api-list-table__col--calls">调用</span>
                <span class="vs-api-list-table__col vs-api-list-table__col--key">密钥</span>
                <span class="vs-api-list-table__col vs-api-list-table__col--status">状态</span>
                <span class="vs-api-list-table__col vs-api-list-table__col--actions">操作</span>
            </div>
            <div class="vs-api-list-table__body" id="apiListBody">
                <?php foreach ($apis as $row): ?>
                    <?php vs_render_api_list_item($row); ?>
                <?php endforeach; ?>
            </div>
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
        <form id="apiListForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" id="apiListFormId" name="api_id" value="">

            <div class="vs-api-list-form-tabs" role="tablist">
                <button type="button" class="vs-api-list-form-tab is-active" data-api-form-tab="basic" role="tab" aria-selected="true">基础</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="params" role="tab" aria-selected="false">参数</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="docs" role="tab" aria-selected="false">文档</button>
            </div>

            <div class="vs-api-list-form-pane is-active" data-api-form-pane="basic">
                <div class="vs-form-row">
                    <label class="vs-label">接口图标</label>
                    <div class="vs-api-cat-icon-picker" id="apiListIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                    <label class="vs-label vs-api-cat-icon-url-label" for="apiListIconUrl">或填写图标链接</label>
                    <input type="url" class="vs-input" id="apiListIconUrl" name="icon"
                           placeholder="https://example.com/icon.png" maxlength="255">
                    <p class="vs-form-hint">点选下方图标，或填写图片链接地址。</p>
                </div>
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
                        <label class="vs-label" for="apiListFormMethod">请求方式 <span class="vs-req">*</span></label>
                        <select class="vs-input vs-select" id="apiListFormMethod" name="method">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label" for="apiListFormStatus">接口状态</label>
                        <select class="vs-input vs-select" id="apiListFormStatus" name="status">
                            <option value="0">正常</option>
                            <option value="2">维护</option>
                            <option value="1">禁用</option>
                        </select>
                    </div>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormAudit">审核状态</label>
                    <select class="vs-input vs-select" id="apiListFormAudit" name="audit">
                        <option value="1" selected>审核通过</option>
                        <option value="0">待审核</option>
                        <option value="2">审核不通过</option>
                    </select>
                    <p class="vs-form-hint">在本页发布时默认「审核通过」；待审核与未通过的接口不会出现在站点前台。</p>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormEndpoint">接口地址 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormEndpoint" name="endpoint" maxlength="500" required
                           placeholder="https://api.example.com/v1/demo">
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormCategory">所属分类</label>
                        <select class="vs-input vs-select" id="apiListFormCategory" name="category">
                            <option value="">未分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="vs-api-list-key-field">
                        <label class="vs-label" for="apiListFormRequireKey">是否需要密钥</label>
                        <select class="vs-input vs-select" id="apiListFormRequireKey" name="needkey">
                            <option value="0">完全不需要</option>
                            <option value="1">必须需要</option>
                            <option value="2">可选（可填可不填）</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="params" hidden>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormParams">请求参数（JSON 数组）</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormParams" name="params" rows="8"
                              placeholder='[{"name":"q","type":"string","required":true,"description":"关键词"}]'></textarea>
                    <p class="vs-form-hint">留空表示无参数。请按示例填写 JSON 数组（含参数名、类型、是否必填、说明等）。</p>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormResponse">返回参数示例</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormResponse" name="response" rows="8"
                              placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
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

<?php vs_admin_layout_end(array('icon-picker.js', 'api-list.js')); ?>
