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
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $result = ApiCategoryManager::create($name, $sortOrder);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('分类已添加', array(
            'category' => $result,
            'api_count' => 0,
            'status_label' => '启用',
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $name = isset($_POST['name']) ? (string) $_POST['name'] : '';
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $result = ApiCategoryManager::update($id, $name, $sortOrder);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiCategoryManager::findById($id);
        AjaxResponse::success('分类已保存', array(
            'category' => array(
                'id'         => (int) $row['id'],
                'name'       => (string) $row['name'],
                'sort_order' => (int) $row['sort_order'],
                'status'     => (int) $row['status'],
            ),
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
            'category_id' => $id,
            'status'      => $status,
            'status_label'=> $status === 1 ? '启用' : '禁用',
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

vs_admin_layout_start('接口分类', 'api-categories');
?>

<div class="vs-panel" id="apiCategoriesPage">
    <div class="vs-panel__header">
        <div>
            <h2 class="vs-panel__title">接口分类</h2>
            <p class="vs-panel__desc">维护前台接口目录与筛选使用的分类；重命名分类会同步更新已关联接口</p>
        </div>
    </div>

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '分类数据表未就绪，请前往「系统管理 → 系统升级」执行数据库结构更新。', array('compact' => true)); ?>
    <?php else: ?>
        <form class="vs-api-cat-add" id="apiCategoryAddForm" autocomplete="off">
            <div class="vs-api-cat-add__fields">
                <label class="vs-label" for="apiCatAddName">分类名称</label>
                <input type="text" class="vs-input" id="apiCatAddName" name="name" maxlength="50" placeholder="例如：图片、工具、娱乐" required>
                <label class="vs-label" for="apiCatAddSort">排序</label>
                <input type="number" class="vs-input vs-input--narrow" id="apiCatAddSort" name="sort_order" value="0" step="1">
                <button type="submit" class="vs-btn vs-btn--primary">添加分类</button>
            </div>
        </form>

        <?php if (count($categories) === 0): ?>
            <?php vs_render_notice('info', '', '暂无分类，请在上方添加；也可在接口审核通过后从接口记录中自动出现未归类名称。', array('compact' => true)); ?>
        <?php else: ?>
            <div class="vs-table-wrap">
                <table class="vs-table vs-api-cat-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>分类名称</th>
                            <th>排序</th>
                            <th>关联接口</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="apiCategoryTableBody">
                        <?php foreach ($categories as $row): ?>
                            <?php
                            $catId = (int) $row['id'];
                            $enabled = (int) $row['status'] === 1;
                            $apiCount = (int) (isset($row['api_count']) ? $row['api_count'] : 0);
                            ?>
                            <tr data-category-row="<?php echo $catId; ?>" data-category-status="<?php echo $enabled ? '1' : '0'; ?>">
                                <td><?php echo $catId; ?></td>
                                <td>
                                    <span class="vs-api-cat-name" data-field="name"><?php echo vs_e($row['name']); ?></span>
                                </td>
                                <td>
                                    <span class="vs-api-cat-sort" data-field="sort_order"><?php echo (int) $row['sort_order']; ?></span>
                                </td>
                                <td><?php echo $apiCount; ?></td>
                                <td class="vs-api-cat-status-cell">
                                    <span class="vs-api-cat-status<?php echo $enabled ? ' is-on' : ' is-off'; ?>" data-field="status_label">
                                        <?php echo $enabled ? '启用' : '禁用'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="vs-api-cat-actions">
                                        <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action"
                                                data-cat-action="edit" data-category-id="<?php echo $catId; ?>">编辑</button>
                                        <?php if ($enabled): ?>
                                            <button type="button" class="vs-btn vs-btn--pill vs-api-cat-action"
                                                    data-cat-action="disable" data-category-id="<?php echo $catId; ?>">禁用</button>
                                        <?php else: ?>
                                            <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary vs-api-cat-action"
                                                    data-cat-action="enable" data-category-id="<?php echo $catId; ?>">启用</button>
                                        <?php endif; ?>
                                        <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger vs-api-cat-action"
                                                data-cat-action="delete" data-category-id="<?php echo $catId; ?>"
                                                data-api-count="<?php echo $apiCount; ?>">删除</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="vs-modal" id="apiCategoryEditModal" hidden>
    <div class="vs-modal__backdrop" data-modal-close="1"></div>
    <div class="vs-modal__dialog" role="dialog" aria-labelledby="apiCategoryEditTitle" aria-modal="true">
        <div class="vs-modal__header">
            <h3 class="vs-modal__title" id="apiCategoryEditTitle">编辑分类</h3>
            <button type="button" class="vs-modal__close" data-modal-close="1" aria-label="关闭">&times;</button>
        </div>
        <form id="apiCategoryEditForm" class="vs-modal__body">
            <input type="hidden" id="apiCatEditId" name="category_id" value="">
            <div class="vs-form-row">
                <label class="vs-label" for="apiCatEditName">分类名称</label>
                <input type="text" class="vs-input" id="apiCatEditName" name="name" maxlength="50" required>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="apiCatEditSort">排序</label>
                <input type="number" class="vs-input vs-input--narrow" id="apiCatEditSort" name="sort_order" step="1" value="0">
                <p class="vs-form-hint">数值越小越靠前</p>
            </div>
        </form>
        <div class="vs-modal__footer">
            <button type="button" class="vs-btn" data-modal-close="1">取消</button>
            <button type="submit" form="apiCategoryEditForm" class="vs-btn vs-btn--primary" id="apiCategoryEditSaveBtn">保存</button>
        </div>
    </div>
</div>

<?php vs_admin_layout_end(array('api-categories.js')); ?>
