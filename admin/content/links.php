<?php
/**
 * 文件：admin/content/links.php
 * 作用：友情链接管理（审核 / 增删改）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create') {
        $result = LinkManager::create(array(
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'siteurl'     => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'contact'     => isset($_POST['contact']) ? (string) $_POST['contact'] : '',
            'sort'        => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            'status'      => isset($_POST['status']) ? (int) $_POST['status'] : LinkManager::STATUS_APPROVED,
        ), LinkManager::STATUS_APPROVED);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('友链已添加', array('link' => $result));
    }

    if ($action === 'update') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $before = LinkManager::findById($id);
        $newStatus = isset($_POST['status']) ? (int) $_POST['status'] : LinkManager::STATUS_APPROVED;
        $result = LinkManager::update($id, array(
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'siteurl'     => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'contact'     => isset($_POST['contact']) ? (string) $_POST['contact'] : '',
            'sort'        => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            'status'      => $newStatus,
        ));
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = LinkManager::findById($id);
        if ($newStatus === LinkManager::STATUS_APPROVED && is_array($row) && class_exists('LinkNotify')) {
            $prev = is_array($before)
                ? LinkManager::normalizeStatus(isset($before['status']) ? $before['status'] : 0)
                : LinkManager::STATUS_PENDING;
            if ($prev !== LinkManager::STATUS_APPROVED) {
                LinkNotify::notifyApplicantApproved(LinkManager::formatRow($row));
            }
        }
        AjaxResponse::success('友链已保存', array(
            'link' => is_array($row) ? LinkManager::formatRow($row) : null,
        ));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $status = isset($_POST['status']) ? (int) $_POST['status'] : -1;
        if (!in_array($status, array(
            LinkManager::STATUS_PENDING,
            LinkManager::STATUS_APPROVED,
            LinkManager::STATUS_REJECTED,
        ), true)) {
            AjaxResponse::error('无效状态');
        }
        $before = LinkManager::findById($id);
        $result = LinkManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        if ($status === LinkManager::STATUS_APPROVED && class_exists('LinkNotify')) {
            $row = LinkManager::findById($id);
            if (is_array($row)) {
                $prev = is_array($before)
                    ? LinkManager::normalizeStatus(isset($before['status']) ? $before['status'] : 0)
                    : LinkManager::STATUS_PENDING;
                if ($prev !== LinkManager::STATUS_APPROVED) {
                    LinkNotify::notifyApplicantApproved(LinkManager::formatRow($row));
                }
            }
        }
        AjaxResponse::success(LinkManager::statusLabel($status), array(
            'link_id'      => $id,
            'status'       => $status,
            'status_label' => LinkManager::statusLabel($status),
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $result = LinkManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('友链已删除', array('link_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = LinkManager::tableReady();
$links = $tableReady ? LinkManager::listAll() : array();

/**
 * @param array $row
 * @return void
 */
function vs_render_link_item(array $row)
{
    $id = (int) $row['id'];
    $status = (int) $row['status'];
    $icon = !empty($row['icon_url']) ? (string) $row['icon_url'] : '';
    $name = (string) $row['name'];
    $siteurl = (string) $row['siteurl'];
    $desc = (string) $row['description'];
    $label = (string) $row['status_label'];
    $statusClass = 'is-pending';
    if ($status === LinkManager::STATUS_APPROVED) {
        $statusClass = 'is-on';
    } elseif ($status === LinkManager::STATUS_REJECTED) {
        $statusClass = 'is-off';
    }
    ?>
    <div class="vs-link-row"
         data-link-row="<?php echo $id; ?>"
         data-link-status="<?php echo $status; ?>"
         data-name="<?php echo vs_e($name); ?>"
         data-siteurl="<?php echo vs_e($siteurl); ?>"
         data-icon="<?php echo vs_e(isset($row['icon']) ? (string) $row['icon'] : ''); ?>"
         data-description="<?php echo vs_e($desc); ?>"
         data-contact="<?php echo vs_e(isset($row['contact']) ? (string) $row['contact'] : ''); ?>"
         data-sort="<?php echo (int) $row['sort']; ?>">
        <div class="vs-link-row__icon">
            <?php if ($icon !== ''): ?>
                <img src="<?php echo vs_e($icon); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer">
            <?php else: ?>
                <span class="vs-link-row__initial"><?php echo vs_e(mb_substr($name, 0, 1, 'UTF-8')); ?></span>
            <?php endif; ?>
        </div>
        <div class="vs-link-row__main">
            <div class="vs-link-row__name" data-field="name"><?php echo vs_e($name); ?></div>
            <a class="vs-link-row__url" data-field="siteurl" href="<?php echo vs_e($siteurl); ?>" target="_blank" rel="noopener noreferrer"><?php echo vs_e($siteurl); ?></a>
            <?php if ($desc !== ''): ?>
                <div class="vs-link-row__desc" data-field="description"><?php echo vs_e($desc); ?></div>
            <?php endif; ?>
        </div>
        <div class="vs-link-row__status">
            <span class="vs-link-status <?php echo vs_e($statusClass); ?>" data-field="status_label"><?php echo vs_e($label); ?></span>
        </div>
        <div class="vs-link-row__actions">
            <button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-link-action="edit" data-link-id="<?php echo $id; ?>">编辑</button>
            <?php if ($status !== LinkManager::STATUS_APPROVED): ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary" data-link-action="approve" data-link-id="<?php echo $id; ?>">通过</button>
            <?php endif; ?>
            <?php if ($status !== LinkManager::STATUS_REJECTED): ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-link-action="reject" data-link-id="<?php echo $id; ?>">拒绝</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger" data-link-action="delete" data-link-id="<?php echo $id; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <button type="button" class="vs-btn vs-btn--primary" id="linkOpenAddBtn">添加友链</button>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('友情链接', 'links', $headerActions);
?>

<div class="vs-panel vs-link-panel" id="adminLinksPage">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '友情链接功能尚未就绪，请前往「系统管理 → 系统升级」完成数据库结构更新。', array('compact' => true)); ?>
    <?php else: ?>
        <div class="vs-link-empty" id="linkEmpty"<?php echo count($links) > 0 ? ' hidden' : ''; ?>>
            <?php vs_render_notice('info', '', '暂无友链。可手动添加，或等待访客在前台「申请友链」提交。', array('compact' => true)); ?>
        </div>
        <div class="vs-link-list" id="linkList"<?php echo count($links) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($links as $row): ?>
                <?php vs_render_link_item($row); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--lg" id="linkFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="linkFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="linkFormTitle">添加友链</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">×</button>
        </header>
        <form id="linkForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" name="action" id="linkFormAction" value="create">
            <input type="hidden" name="link_id" id="linkFormId" value="0">
            <div class="vs-field">
                <label class="vs-label" for="linkName">网站名称</label>
                <input type="text" class="vs-input" id="linkName" name="name" maxlength="50" required>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="linkUrl">网站链接</label>
                <input type="url" class="vs-input" id="linkUrl" name="siteurl" maxlength="255" required placeholder="https://">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="linkIcon">头像链接（选填）</label>
                <input type="text" class="vs-input" id="linkIcon" name="icon" maxlength="255" placeholder="https://…/icon.png">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="linkDesc">简介（选填）</label>
                <input type="text" class="vs-input" id="linkDesc" name="description" maxlength="200">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="linkContact">联系方式（选填）</label>
                <input type="text" class="vs-input" id="linkContact" name="contact" maxlength="100">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="linkSort">排序</label>
                <input type="number" class="vs-input" id="linkSort" name="sort" value="0">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="linkStatus">状态</label>
                <select class="vs-input" id="linkStatus" name="status">
                    <option value="1">已通过</option>
                    <option value="0">待审核</option>
                    <option value="2">已拒绝</option>
                </select>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="linkForm" class="vs-btn vs-btn--primary" id="linkFormSubmit">保存</button>
        </footer>
    </div>
</div>

<?php
vs_admin_layout_end(array('admin-links.js'));
