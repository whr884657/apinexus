<?php
/**
 * 文件：admin/content/partners.php
 * 作用：合作伙伴管理（与友链共用 link 表；无审核；编辑 / 启禁 / 删除）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create') {
        $result = LinkManager::create(array(
            'kind'    => LinkManager::KIND_PARTNER,
            'name'    => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'siteurl' => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
            'icon'    => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'sort'    => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            'enabled' => isset($_POST['enabled']) ? (int) $_POST['enabled'] : LinkManager::ENABLED_ON,
        ), LinkManager::STATUS_APPROVED);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('合作伙伴已添加', array('link' => $result));
    }

    if ($action === 'update') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $existing = LinkManager::findById($id);
        if (!is_array($existing)
            || LinkManager::normalizeKind(isset($existing['kind']) ? $existing['kind'] : -1) !== LinkManager::KIND_PARTNER
        ) {
            AjaxResponse::error('记录不存在');
        }
        $result = LinkManager::update($id, array(
            'name'    => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'siteurl' => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
            'icon'    => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'sort'    => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            'enabled' => isset($_POST['enabled']) ? (int) $_POST['enabled'] : LinkManager::ENABLED_ON,
        ));
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = LinkManager::findById($id);
        AjaxResponse::success('合作伙伴已保存', array(
            'link' => is_array($row) ? LinkManager::formatRow($row) : null,
        ));
    }

    if ($action === 'set_enabled') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $enabled = isset($_POST['enabled']) ? (int) $_POST['enabled'] : -1;
        $existing = LinkManager::findById($id);
        if (!is_array($existing)
            || LinkManager::normalizeKind(isset($existing['kind']) ? $existing['kind'] : -1) !== LinkManager::KIND_PARTNER
        ) {
            AjaxResponse::error('记录不存在');
        }
        if (!in_array($enabled, array(LinkManager::ENABLED_OFF, LinkManager::ENABLED_ON), true)) {
            AjaxResponse::error('无效操作');
        }
        $result = LinkManager::setEnabled($id, $enabled);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success(
            $enabled === LinkManager::ENABLED_ON ? '已启用' : '已禁用',
            array(
                'link_id'       => $id,
                'enabled'       => $enabled,
                'enabled_label' => LinkManager::enabledLabel($enabled),
            )
        );
    }

    if ($action === 'delete') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $existing = LinkManager::findById($id);
        if (!is_array($existing)
            || LinkManager::normalizeKind(isset($existing['kind']) ? $existing['kind'] : -1) !== LinkManager::KIND_PARTNER
        ) {
            AjaxResponse::error('记录不存在');
        }
        $result = LinkManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('合作伙伴已删除', array('link_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = LinkManager::tableReady();
$partners = $tableReady ? LinkManager::listAll(null, LinkManager::KIND_PARTNER) : array();

/**
 * @param array $row
 * @return void
 */
function vs_render_partner_item(array $row)
{
    $id = (int) $row['id'];
    $enabled = (int) $row['enabled'];
    $icon = !empty($row['icon_url']) ? (string) $row['icon_url'] : '';
    $name = (string) $row['name'];
    $siteurl = (string) $row['siteurl'];
    $statusClass = $enabled === LinkManager::ENABLED_ON ? 'is-on' : 'is-off';
    $label = $enabled === LinkManager::ENABLED_ON ? '启用' : '禁用';
    ?>
    <div class="vs-link-row"
         data-partner-row="<?php echo $id; ?>"
         data-link-enabled="<?php echo $enabled; ?>"
         data-name="<?php echo vs_e($name); ?>"
         data-siteurl="<?php echo vs_e($siteurl); ?>"
         data-icon="<?php echo vs_e(isset($row['icon']) ? (string) $row['icon'] : ''); ?>"
         data-sort="<?php echo (int) $row['sort']; ?>"
         data-enabled="<?php echo $enabled; ?>">
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
        </div>
        <div class="vs-link-row__status">
            <span class="vs-link-status <?php echo vs_e($statusClass); ?>" data-field="enabled_label"><?php echo vs_e($label); ?></span>
        </div>
        <div class="vs-link-row__actions">
            <button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-partner-action="edit" data-link-id="<?php echo $id; ?>">编辑</button>
            <?php if ($enabled === LinkManager::ENABLED_ON): ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-partner-action="disable" data-link-id="<?php echo $id; ?>">禁用</button>
            <?php else: ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary" data-partner-action="enable" data-link-id="<?php echo $id; ?>">启用</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger" data-partner-action="delete" data-link-id="<?php echo $id; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <button type="button" class="vs-btn vs-btn--primary" id="partnerOpenAddBtn">添加合作伙伴</button>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('合作伙伴', 'partners', $headerActions);
?>

<div class="vs-panel vs-link-panel" id="adminPartnersPage">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '合作伙伴功能尚未就绪，请前往「系统管理 → 系统升级」完成数据库结构更新。', array('compact' => true)); ?>
    <?php else: ?>
        <div class="vs-link-empty" id="partnerEmpty"<?php echo count($partners) > 0 ? ' hidden' : ''; ?>>
            <?php vs_render_notice('info', '', '暂无合作伙伴。添加后将显示在默认主题首页「合作伙伴」区域；禁用后前台不再展示。', array('compact' => true)); ?>
        </div>
        <div class="vs-link-list" id="partnerList"<?php echo count($partners) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($partners as $row): ?>
                <?php vs_render_partner_item($row); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--form" id="partnerFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="partnerFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="partnerFormTitle">添加合作伙伴</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">×</button>
        </header>
        <form id="partnerForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" name="action" id="partnerFormAction" value="create">
            <input type="hidden" name="link_id" id="partnerFormId" value="0">
            <div class="vs-field">
                <label class="vs-label" for="partnerName">名称</label>
                <input type="text" class="vs-input" id="partnerName" name="name" maxlength="50" required>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="partnerUrl">跳转链接</label>
                <input type="url" class="vs-input" id="partnerUrl" name="siteurl" maxlength="255" required placeholder="https://">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="partnerIcon">图标链接（选填）</label>
                <input type="text" class="vs-input" id="partnerIcon" name="icon" maxlength="255" placeholder="https://…/icon.png">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="partnerSort">排序</label>
                <input type="number" class="vs-input" id="partnerSort" name="sort" value="0">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="partnerEnabled">前台显示</label>
                <select class="vs-input" id="partnerEnabled" name="enabled" data-vs-pick>
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="partnerForm" class="vs-btn vs-btn--primary">保存</button>
        </footer>
    </div>
</div>

<?php
vs_admin_layout_end(array('vs-pick.js', 'admin-partners.js'));
