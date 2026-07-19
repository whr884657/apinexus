<?php
/**
 * 文件：admin/about.php
 * 作用：ApiNexus 后台关于页面（系统与环境信息）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

$systemInfo = SystemInfo::collect();
$updateCheck = Updater::checkForUpdate();

vs_admin_layout_start('关于', 'about');
?>

<div class="vs-panel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title"><?php echo vs_e(SiteContext::siteName()); ?></h2>
        <p class="vs-panel__desc">ApiNexus 管理系统框架</p>
    </div>

    <div class="vs-info-grid">
        <?php foreach ($systemInfo as $item): ?>
            <div class="vs-info-item">
                <span class="vs-info-item__label"><?php echo vs_e($item['label']); ?></span>
                <span class="vs-info-item__value">
                    <?php if ($item['label'] === '系统版本'): ?>
                        <?php echo vs_render_version_display($updateCheck); ?>
                    <?php else: ?>
                        <?php echo vs_e($item['value']); ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php vs_admin_layout_end(); ?>
