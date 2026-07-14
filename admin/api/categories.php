<?php
/**
 * 文件：admin/api/categories.php
 * 作用：接口分类管理
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create') {
        $name = isset($_POST['name']) ? (string) $_POST['name'] : '';
        $icon = isset($_POST['icon']) ? (string) $_POST['icon'] : '';
        $description = isset($_POST['description']) ? (string) $_POST['description'] : '';
        $result = ApiCategoryManager::create($name, $icon, $description);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('分类已添加', array(
            'category'     => $result,
            'api_count'    => 0,
            'status_label' => '启用',
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $name = isset($_POST['name']) ? (string) $_POST['name'] : '';
        $icon = isset($_POST['icon']) ? (string) $_POST['icon'] : '';
        $description = isset($_POST['description']) ? (string) $_POST['description'] : '';
        $result = ApiCategoryManager::update($id, $name, $icon, $description);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiCategoryManager::findById($id);
        AjaxResponse::success('分类已保存', array(
            'category'  => ApiCategoryManager::formatRow($row),
            'api_count' => ApiCategoryManager::countApisByName((string) $row['name']),
        ));
    }

    if ($action === 'toggle_status') {
        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        if (!in_array($status, array(0, 1), true)) {
            AjaxResponse::error('无效状态');
        }
        $result = ApiCategoryManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success($status === 1 ? '分类已启用' : '分类已禁用', array(
            'category_id'  => $id,
            'status'       => $status,
            'status_label' => $status === 1 ? '启用' : '禁用',
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $result = ApiCategoryManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('分类已删除', array('category_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$categories = ApiCategoryManager::listAll();
$tableReady = ApiCategoryManager::tableReady();
$defaultIconPaths = ApiCategoryManager::defaultIconPaths();
$iconBase = rtrim(vs_base_url(), '/');

/**
 * @param array $row
 * @return void
 */
function vs_render_api_category_item(array $row)
{
    $catId = (int) $row['id'];
    $enabled = (int) $row['status'] === 1;
    $apiCount = (int) (isset($row['api_count']) ? $row['api_count'] : 0);
    $icon = ApiCategoryManager::resolveIconUrl(isset($row['icon']) ? (string) $row['icon'] : '');
    $desc = trim((string) (isset($row['description']) ? $row['description'] : ''));
    $name = (string) $row['name'];
    ?>
    <div class="vs-api-cat-row"
         data-category-row="<?php echo $catId; ?>"
         data-category-status="<?php echo $enabled ? '1' : '0'; ?>">
        <div class="vs-api-cat-row__icon">
            <img src="<?php echo vs_e($icon); ?>" alt="" width="32" height="32" loading="lazy">
        </div>
        <div class="vs-api-cat-row__name" data-field="name"><?php echo vs_e($name); ?></div>
        <div class="vs-api-cat-row__desc" data-field="description">
            <?php if ($desc !== ''): ?>
                <?php echo vs_e($desc); ?>
            <?php else: ?>
                <span class="vs-api-cat-row__desc-empty">—</span>
            <?php endif; ?>
        </div>
        <div class="vs-api-cat-row__count" data-field="api_count"><?php echo $apiCount; ?></div>
        <div class="vs-api-cat-row__status">
            <span class="vs-api-cat-status<?php echo $enabled ? ' is-on' : ' is-off'; ?>" data-field="status_label">
                <?php echo $enabled ? '启用' : '禁用'; ?>
            </span>
        </div>
        <div class="vs-api-cat-row__actions">
            <button type="button" class="vs-btn vs-btn--pill vs-btn--default vs-api-cat-action"
                    data-cat-action="edit" data-category-id="<?php echo $catId; ?>">编辑</button>
            <?php if ($enabled): ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--default vs-api-cat-action"
                        data-cat-action="disable" data-category-id="<?php echo $catId; ?>">禁用</button>
            <?php else: ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action"
                        data-cat-action="enable" data-category-id="<?php echo $catId; ?>">启用</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger vs-api-cat-action"
                    data-cat-action="delete" data-category-id="<?php echo $catId; ?>"
                    data-api-count="<?php echo $apiCount; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <button type="button" class="vs-btn vs-btn--primary vs-api-cat-add-btn" id="apiCatOpenAddBtn">
        <span class="vs-api-cat-add-btn__icon" aria-hidden="true">+</span>
        <span class="vs-api-cat-add-btn__text">添加分类</span>
    </button>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口分类', 'api-categories', $headerActions);
?>

<div class="vs-panel vs-api-cat-panel" id="apiCategoriesPage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '分类数据表未就绪，请前往「系统管理 → 系统升级」执行数据库结构更新。', array('compact' => true)); ?>
    <?php else: ?>
        <div class="vs-api-cat-empty" id="apiCategoryEmpty"<?php echo count($categories) > 0 ? ' hidden' : ''; ?>>
            <?php vs_render_notice('info', '', '暂无分类，点击「添加分类」创建。', array('compact' => true)); ?>
        </div>

        <div class="vs-api-cat-table" id="apiCategoryTable"<?php echo count($categories) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-cat-table__head" aria-hidden="true">
                <span class="vs-api-cat-table__col vs-api-cat-table__col--icon"></span>
                <span class="vs-api-cat-table__col">名称</span>
                <span class="vs-api-cat-table__col">描述</span>
                <span class="vs-api-cat-table__col vs-api-cat-table__col--count">接口</span>
                <span class="vs-api-cat-table__col vs-api-cat-table__col--status">状态</span>
                <span class="vs-api-cat-table__col vs-api-cat-table__col--actions">操作</span>
            </div>
            <div class="vs-api-cat-table__body" id="apiCategoryList">
                <?php foreach ($categories as $row): ?>
                    <?php vs_render_api_category_item($row); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--lg" id="apiCategoryFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="apiCategoryFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="apiCategoryFormTitle">添加分类</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="apiCategoryForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" id="apiCatFormId" name="category_id" value="">
            <div class="vs-form-row">
                <label class="vs-label">分类图标</label>
                <div class="vs-api-cat-icon-picker" id="apiCatIconPicker" role="listbox" aria-label="选择内置图标"></div>
                <p class="vs-form-hint" id="apiCatIconCountHint">系统内置 SVG 图标库，点选即可</p>
                <label class="vs-label vs-api-cat-icon-url-label" for="apiCatIconUrl">或填写图标链接（正方形）</label>
                <input type="url" class="vs-input" id="apiCatIconUrl" name="icon"
                       placeholder="https://example.com/icon.png" maxlength="255">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="apiCatFormName">分类名称</label>
                <input type="text" class="vs-input" id="apiCatFormName" name="name" maxlength="50" required
                       placeholder="例如：图片、工具、娱乐">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="apiCatFormDesc">分类描述</label>
                <textarea class="vs-input vs-textarea" id="apiCatFormDesc" name="description" maxlength="255"
                          rows="3" placeholder="简要说明该分类包含的接口类型（选填）"></textarea>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--pill" data-overlay-close="1">取消</button>
            <button type="submit" form="apiCategoryForm" class="vs-btn vs-btn--pill vs-btn--pill-primary" id="apiCatFormSubmitBtn">保存</button>
        </footer>
    </div>
</div>

<?php vs_admin_layout_end(array('icon-picker.js', 'api-categories.js')); ?>
