<?php
/**
 * 文件：admin/api/categories.php
 * 作用：接口分类管理（电脑表格 + 手机卡片；搜索静态过滤）
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

    if ($action === 'delete_move') {
        $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $targetId = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
        $result = ApiCategoryManager::deleteAndMove($id, $targetId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('分类已删除，接口已转移', array(
            'category_id' => $id,
            'target_id'   => $targetId,
        ));
    }

    AjaxResponse::error('无效操作', 400);
}

$categories = ApiCategoryManager::listAll();
$tableReady = ApiCategoryManager::tableReady();
$defaultIconPaths = ApiCategoryManager::defaultIconPaths();
$iconBase = rtrim(vs_base_url(), '/');
$categoriesForJs = array();
foreach ($categories as $row) {
    $categoriesForJs[] = array(
        'id'        => (int) $row['id'],
        'name'      => (string) $row['name'],
        'api_count' => (int) (isset($row['api_count']) ? $row['api_count'] : 0),
    );
}

/**
 * @param array $row
 * @return array
 */
function vs_api_cat_row_context(array $row)
{
    $catId = (int) $row['id'];
    $enabled = (int) $row['status'] === 1;
    $apiCount = (int) (isset($row['api_count']) ? $row['api_count'] : 0);
    $icon = ApiCategoryManager::resolveIconUrl(isset($row['icon']) ? (string) $row['icon'] : '');
    $desc = trim((string) (isset($row['description']) ? $row['description'] : ''));
    $name = (string) $row['name'];
    $searchHay = mb_strtolower($name . ' ' . $desc . ' #' . $catId, 'UTF-8');

    return array(
        'catId'    => $catId,
        'enabled'  => $enabled,
        'apiCount' => $apiCount,
        'icon'     => $icon,
        'desc'     => $desc,
        'name'     => $name,
        'search'   => $searchHay,
    );
}

/**
 * @param bool $enabled
 * @param int $catId
 * @param int $apiCount
 * @return string
 */
function vs_api_cat_action_buttons_html($enabled, $catId, $apiCount)
{
    $catId = (int) $catId;
    $apiCount = (int) $apiCount;
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-api-cat-action" data-cat-action="edit" data-category-id="'
        . $catId . '">编辑</button>';
    if ($enabled) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-cat-action" data-cat-action="disable" data-category-id="'
            . $catId . '">禁用</button>';
    } else {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-cat-action" data-cat-action="enable" data-category-id="'
            . $catId . '">启用</button>';
    }
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-cat-action" data-cat-action="delete" data-category-id="'
        . $catId . '" data-api-count="' . $apiCount . '">删除</button>';
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_api_cat_desktop_row(array $ctx)
{
    $attrs = ' data-category-row="' . (int) $ctx['catId'] . '"'
        . ' data-category-status="' . ($ctx['enabled'] ? '1' : '0') . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"';
    ?>
    <tr class="vs-api-cat-row"<?php echo $attrs; ?>>
        <td>
            <div class="cat-name-cell">
                <div class="cat-icon">
                    <img src="<?php echo vs_e($ctx['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
                </div>
                <span class="cat-name-text" data-field="name"><?php echo vs_e($ctx['name']); ?></span>
            </div>
        </td>
        <td>
            <span class="cat-desc" data-field="description"><?php
                echo $ctx['desc'] !== '' ? vs_e($ctx['desc']) : '—';
            ?></span>
        </td>
        <td><span class="cat-count" data-field="api_count"><?php echo (int) $ctx['apiCount']; ?></span></td>
        <td>
            <span class="vs-badge <?php echo $ctx['enabled'] ? 'vs-badge--success' : 'vs-badge--default'; ?>" data-field="status_label">
                <?php echo $ctx['enabled'] ? '启用' : '禁用'; ?>
            </span>
        </td>
        <td class="vs-api-cat-actions-cell" data-field="actions">
            <?php echo vs_api_cat_action_buttons_html($ctx['enabled'], $ctx['catId'], $ctx['apiCount']); ?>
        </td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_api_cat_mobile_card(array $ctx)
{
    $attrs = ' data-category-row="' . (int) $ctx['catId'] . '"'
        . ' data-category-status="' . ($ctx['enabled'] ? '1' : '0') . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"';
    ?>
    <div class="cat-card vs-api-cat-row"<?php echo $attrs; ?>>
        <div class="cat-card__header">
            <div class="cat-card__header-left">
                <div class="cat-card__icon">
                    <img src="<?php echo vs_e($ctx['icon']); ?>" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
                </div>
                <span class="cat-card__name" data-field="name"><?php echo vs_e($ctx['name']); ?></span>
            </div>
            <div class="cat-card__header-right">
                <span class="vs-badge <?php echo $ctx['enabled'] ? 'vs-badge--success' : 'vs-badge--default'; ?>" data-field="status_label">
                    <?php echo $ctx['enabled'] ? '启用' : '禁用'; ?>
                </span>
                <span class="cat-card__count"><span data-field="api_count"><?php echo (int) $ctx['apiCount']; ?></span> 个</span>
            </div>
        </div>
        <div class="cat-card__desc" data-field="description"><?php
            echo $ctx['desc'] !== '' ? vs_e($ctx['desc']) : '暂无描述';
        ?></div>
        <div class="cat-card__actions" data-field="actions">
            <?php echo vs_api_cat_action_buttons_html($ctx['enabled'], $ctx['catId'], $ctx['apiCount']); ?>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="apiCatSearchInput"
                   placeholder="搜索分类名称..." autocomplete="off">
        </div>
        <button type="button" class="vs-btn vs-btn--primary" id="apiCatOpenAddBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            添加分类
        </button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口分类', 'api-categories', $headerActions);
?>

<div id="apiCategoriesPage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>"
     data-categories="<?php echo vs_e(json_encode($categoriesForJs, JSON_UNESCAPED_UNICODE)); ?>">

    <?php if (!$tableReady): ?>
        <div class="vs-panel vs-api-cat-panel">
            <?php vs_render_notice('warning', '', '分类管理功能尚未就绪，请前往「系统管理 → 系统升级」完成更新。', array('compact' => true)); ?>
        </div>
    <?php else: ?>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiCategoryEmpty"<?php echo count($categories) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无分类</h3>
                <p class="vs-api-list-empty__desc">点击右上角「添加分类」创建。分类用于前台筛选与接口归属。</p>
            </div>
        </div>
        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiCategorySearchEmpty" hidden>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无匹配项</h3>
                <p class="vs-api-list-empty__desc">当前搜索下没有分类，可清空关键词重试。</p>
            </div>
        </div>

        <div class="vs-api-list-table-card vs-api-list-table-wrap" id="apiCatTableWrap"<?php echo count($categories) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-table-responsive">
                <table class="vs-table vs-api-cat-table">
                    <thead>
                        <tr>
                            <th>分类名称</th>
                            <th>描述</th>
                            <th>接口数量</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="apiCategoryBody">
                        <?php foreach ($categories as $row): ?>
                            <?php vs_render_api_cat_desktop_row(vs_api_cat_row_context($row)); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-cat-cards" id="apiCatMobile"<?php echo count($categories) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($categories as $row): ?>
                <?php vs_render_api_cat_mobile_card(vs_api_cat_row_context($row)); ?>
            <?php endforeach; ?>
        </div>

        <div class="vs-api-list-footer" id="apiCatFooter"<?php echo count($categories) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-pager" id="apiCatPager">
                <label class="vs-api-list-pagesize" for="apiCatPageSize">
                    <span class="vs-api-list-pagesize__label">每页</span>
                    <select class="vs-input vs-select vs-api-list-pagesize__select" id="apiCatPageSize" data-vs-pick="sheet">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <button type="button" class="vs-api-pager__nav" id="apiCatPrevBtn" aria-label="上一页">上一页</button>
                <div class="vs-api-pager__nums" id="apiCatPagerNums" role="navigation" aria-label="页码"></div>
                <button type="button" class="vs-api-pager__nav" id="apiCatNextBtn" aria-label="下一页">下一页</button>
            </div>
            <p class="vs-api-list-stats" id="apiCatStats">共 <?php echo (int) count($categories); ?> 条</p>
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
                <p class="vs-form-hint" id="apiCatIconCountHint">系统内置数字编号 SVG 图标库，点选即可</p>
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
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="apiCategoryForm" class="vs-btn vs-btn--primary" id="apiCatFormSubmitBtn">保存</button>
        </footer>
    </div>
</div>

<div class="vs-overlay vs-overlay--form" id="apiCategoryTransferOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-transfer-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="apiCategoryTransferTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="apiCategoryTransferTitle">删除分类</h3>
            <button type="button" class="vs-overlay__close" data-transfer-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="apiCategoryTransferForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" id="apiCatTransferId" name="category_id" value="">
            <input type="hidden" id="apiCatTransferTarget" name="target_id" value="" required>
            <p class="vs-form-hint" id="apiCatTransferHint"></p>
            <div class="vs-form-row">
                <span class="vs-label" id="apiCatTransferOptionsLabel">选择目标分类</span>
                <div class="vs-cat-transfer-options" id="apiCatTransferOptions" role="radiogroup" aria-labelledby="apiCatTransferOptionsLabel"></div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-transfer-overlay-close="1">取消</button>
            <button type="submit" form="apiCategoryTransferForm" class="vs-btn vs-btn--danger" id="apiCatTransferSubmitBtn">确认删除</button>
        </footer>
    </div>
</div>

<?php vs_admin_layout_end(array('icon-picker.js', 'api-categories.js')); ?>
