<?php
/**
 * 文件：admin/finance/sponsor.php
 * 作用：赞助管理（与友链/合作伙伴共用 link 表；kind=2）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'create') {
        $result = LinkManager::create(array(
            'kind'        => LinkManager::KIND_SPONSOR,
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'siteurl'     => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'sort'        => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            'enabled'     => isset($_POST['enabled']) ? (int) $_POST['enabled'] : LinkManager::ENABLED_ON,
        ), LinkManager::STATUS_APPROVED);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('赞助已添加', array('link' => $result));
    }

    if ($action === 'update') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $existing = LinkManager::findById($id);
        if (!is_array($existing)
            || LinkManager::normalizeKind(isset($existing['kind']) ? $existing['kind'] : -1) !== LinkManager::KIND_SPONSOR
        ) {
            AjaxResponse::error('记录不存在');
        }
        $result = LinkManager::update($id, array(
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'siteurl'     => isset($_POST['siteurl']) ? (string) $_POST['siteurl'] : '',
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'sort'        => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
            'enabled'     => isset($_POST['enabled']) ? (int) $_POST['enabled'] : LinkManager::ENABLED_ON,
        ));
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = LinkManager::findById($id);
        AjaxResponse::success('赞助已保存', array(
            'link' => is_array($row) ? LinkManager::formatRow($row) : null,
        ));
    }

    if ($action === 'set_enabled') {
        $id = isset($_POST['link_id']) ? (int) $_POST['link_id'] : 0;
        $enabled = isset($_POST['enabled']) ? (int) $_POST['enabled'] : -1;
        $existing = LinkManager::findById($id);
        if (!is_array($existing)
            || LinkManager::normalizeKind(isset($existing['kind']) ? $existing['kind'] : -1) !== LinkManager::KIND_SPONSOR
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
            || LinkManager::normalizeKind(isset($existing['kind']) ? $existing['kind'] : -1) !== LinkManager::KIND_SPONSOR
        ) {
            AjaxResponse::error('记录不存在');
        }
        $result = LinkManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('赞助已删除', array('link_id' => $id));
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = LinkManager::tableReady();
$sponsors = $tableReady ? LinkManager::listAll(null, LinkManager::KIND_SPONSOR) : array();

/**
 * @param array $row
 * @return void
 */
function vs_render_sponsor_item(array $row)
{
    $id = (int) $row['id'];
    $enabled = (int) $row['enabled'];
    $icon = !empty($row['icon_url']) ? (string) $row['icon_url'] : '';
    $name = (string) $row['name'];
    $siteurl = (string) $row['siteurl'];
    $description = (string) $row['description'];
    $statusClass = $enabled === LinkManager::ENABLED_ON ? 'is-on' : 'is-off';
    $label = $enabled === LinkManager::ENABLED_ON ? '启用' : '禁用';
    ?>
    <div class="vs-link-row"
         data-sponsor-row="<?php echo $id; ?>"
         data-link-enabled="<?php echo $enabled; ?>"
         data-name="<?php echo vs_e($name); ?>"
         data-siteurl="<?php echo vs_e($siteurl); ?>"
         data-icon="<?php echo vs_e(isset($row['icon']) ? (string) $row['icon'] : ''); ?>"
         data-description="<?php echo vs_e($description); ?>"
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
            <?php if ($description !== ''): ?>
                <div class="vs-link-row__desc" data-field="description"><?php echo vs_e($description); ?></div>
            <?php else: ?>
                <div class="vs-link-row__desc" data-field="description" hidden></div>
            <?php endif; ?>
            <?php if ($siteurl !== ''): ?>
                <a class="vs-link-row__url" data-field="siteurl" href="<?php echo vs_e($siteurl); ?>" target="_blank" rel="noopener noreferrer"><?php echo vs_e($siteurl); ?></a>
            <?php else: ?>
                <span class="vs-link-row__url is-empty" data-field="siteurl">未填写链接</span>
            <?php endif; ?>
        </div>
        <div class="vs-link-row__status">
            <span class="vs-link-status <?php echo vs_e($statusClass); ?>" data-field="enabled_label"><?php echo vs_e($label); ?></span>
        </div>
        <div class="vs-link-row__actions">
            <button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-sponsor-action="edit" data-link-id="<?php echo $id; ?>">编辑</button>
            <?php if ($enabled === LinkManager::ENABLED_ON): ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--default" data-sponsor-action="disable" data-link-id="<?php echo $id; ?>">禁用</button>
            <?php else: ?>
                <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-primary" data-sponsor-action="enable" data-link-id="<?php echo $id; ?>">启用</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--pill vs-btn--pill-danger" data-sponsor-action="delete" data-link-id="<?php echo $id; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <button type="button" class="vs-btn vs-btn--primary" id="sponsorOpenAddBtn">添加赞助</button>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('赞助管理', 'sponsor', $headerActions);
?>

<div class="vs-panel vs-link-panel" id="adminSponsorsPage">
    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '赞助功能尚未就绪，请前往「系统管理 → 系统升级」完成数据库结构更新。', array('compact' => true)); ?>
    <?php else: ?>
        <?php vs_render_notice('tip', '', '赞助与友情链接、合作伙伴共用数据表。名称对应赞助人；图片为头像；跳转链接为博客/主页（选填）；赞助说明填写金额或其它支持（如「技术支持」）。收款二维码请在「系统设置 → 站点扩展」配置。', array('compact' => true)); ?>
        <div class="vs-link-empty" id="sponsorEmpty"<?php echo count($sponsors) > 0 ? ' hidden' : ''; ?>>
            <?php vs_render_notice('info', '', '暂无赞助记录。添加并启用后将显示在默认主题「赞助」页。', array('compact' => true)); ?>
        </div>
        <div class="vs-link-list" id="sponsorList"<?php echo count($sponsors) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($sponsors as $row): ?>
                <?php vs_render_sponsor_item($row); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--form" id="sponsorFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="sponsorFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="sponsorFormTitle">添加赞助</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">×</button>
        </header>
        <form id="sponsorForm" class="vs-overlay__body vs-form" autocomplete="off">
            <input type="hidden" name="action" id="sponsorFormAction" value="create">
            <input type="hidden" name="link_id" id="sponsorFormId" value="0">
            <div class="vs-field">
                <label class="vs-label" for="sponsorName">名称</label>
                <input type="text" class="vs-input" id="sponsorName" name="name" maxlength="50" required placeholder="赞助人 / 组织名称">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="sponsorDesc">赞助说明</label>
                <input type="text" class="vs-input" id="sponsorDesc" name="description" maxlength="200" required placeholder="例如：100 元，或「技术支持」">
                <?php vs_render_notice('tip', '', '对应数据表简介字段，用于展示金额或其它赞助内容。', array('field' => true, 'compact' => true)); ?>
            </div>
            <div class="vs-field">
                <label class="vs-label" for="sponsorUrl">博客 / 主页链接（选填）</label>
                <input type="url" class="vs-input" id="sponsorUrl" name="siteurl" maxlength="255" placeholder="https://">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="sponsorIcon">头像 / 图片链接（选填）</label>
                <input type="text" class="vs-input" id="sponsorIcon" name="icon" maxlength="255" placeholder="https://…/avatar.png">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="sponsorSort">排序</label>
                <input type="number" class="vs-input" id="sponsorSort" name="sort" value="0">
            </div>
            <div class="vs-field">
                <label class="vs-label" for="sponsorEnabled">前台显示</label>
                <select class="vs-input" id="sponsorEnabled" name="enabled" data-vs-pick>
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="sponsorForm" class="vs-btn vs-btn--primary">保存</button>
        </footer>
    </div>
</div>

<?php
vs_admin_layout_end(array('admin-sponsors.js'));
