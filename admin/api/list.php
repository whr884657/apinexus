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
            'request_params'   => isset($_POST['request_params']) ? (string) $_POST['request_params'] : '',
            'response_example' => isset($_POST['response_example']) ? (string) $_POST['response_example'] : '',
            'doc_normal'       => isset($_POST['doc_normal']) ? (string) $_POST['doc_normal'] : '',
            'doc_ai'           => isset($_POST['doc_ai']) ? (string) $_POST['doc_ai'] : '',
            'require_key'      => !empty($_POST['require_key']) ? 1 : 0,
            'status'           => isset($_POST['status']) ? (string) $_POST['status'] : ApiManager::STATUS_NORMAL,
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
        $data['user_id'] = ($admin && !empty($admin['bound_user_id'])) ? (int) $admin['bound_user_id'] : 0;
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
        $status = isset($_POST['status']) ? (string) $_POST['status'] : '';
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
    $status = (string) $api['status'];
    $statusClass = 'is-normal';
    if ($status === ApiManager::STATUS_DISABLED) {
        $statusClass = 'is-disabled';
    } elseif ($status === ApiManager::STATUS_MAINTENANCE) {
        $statusClass = 'is-maintenance';
    }
    $desc = trim((string) $api['description']);
    $payloadJson = json_encode($api, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="vs-api-list-row"
         data-api-row="<?php echo $apiId; ?>"
         data-api-status="<?php echo vs_e($status); ?>"
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
        <div class="vs-api-list-row__calls" data-field="call_count"><?php echo (int) $api['call_count']; ?></div>
        <div class="vs-api-list-row__key" data-field="require_key_label">
            <?php echo !empty($api['require_key']) ? '需要' : '否'; ?>
        </div>
        <div class="vs-api-list-row__status">
            <span class="vs-api-list-status <?php echo $statusClass; ?>" data-field="status_label">
                <?php echo vs_e($api['status_label']); ?>
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
            <?php vs_render_notice('warning', '', '接口数据表未就绪或仍为旧结构。请先执行数据库结构更新后再使用接口列表。', array('compact' => true)); ?>
            <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade.php'); ?>">前往系统升级</a>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip vs-api-list-tip--enter">
            <?php vs_render_notice('info', '', '禁用：前台完全不显示。维护：前台可见，但请求将提示「维护中」。图标为本地 SVG 或外链，主题可不展示。', array('compact' => true)); ?>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiListEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <div class="vs-api-list-empty__icon" aria-hidden="true">+</div>
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">点击右上角「添加接口」，配置名称、地址、参数与文档。</p>
                <button type="button" class="vs-btn vs-btn--primary" id="apiListEmptyAddBtn">添加接口</button>
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
                    <label class="vs-label" for="apiListIconSearch">接口图标</label>
                    <input type="search" class="vs-input vs-icon-picker-search" id="apiListIconSearch"
                           placeholder="搜索图标名，如 weather、alipay、api" autocomplete="off">
                    <div class="vs-api-cat-icon-picker" id="apiListIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                    <label class="vs-label vs-api-cat-icon-url-label" for="apiListIconUrl">或填写图标链接</label>
                    <input type="url" class="vs-input" id="apiListIconUrl" name="icon"
                           placeholder="https://example.com/icon.png" maxlength="255">
                    <p class="vs-form-hint">图标来自系统 SVG 库；非主题通用字段，未支持的主题可忽略。</p>
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
                        <label class="vs-label" for="apiListFormStatus">状态</label>
                        <select class="vs-input vs-select" id="apiListFormStatus" name="status">
                            <option value="normal">正常</option>
                            <option value="maintenance">维护</option>
                            <option value="disabled">禁用</option>
                        </select>
                    </div>
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
                        <span class="vs-label">是否需要密钥</span>
                        <label class="vs-checkbox">
                            <input type="checkbox" id="apiListFormRequireKey" name="require_key" value="1">
                            <span>请求时需要密钥</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="params" hidden>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormParams">请求参数（JSON 数组）</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormParams" name="request_params" rows="8"
                              placeholder='[{"name":"q","type":"string","required":true,"description":"关键词"}]'></textarea>
                    <p class="vs-form-hint">留空表示无参数。须为 JSON 数组，字段建议含 name / type / required / description。</p>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormResponse">返回参数示例</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormResponse" name="response_example" rows="8"
                              placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="docs" hidden>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormDocNormal">普通文档</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormDocNormal" name="doc_normal" rows="10"
                              placeholder="面向普通用户的接口说明…"></textarea>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormDocAi">AI 文档</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormDocAi" name="doc_ai" rows="10"
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
