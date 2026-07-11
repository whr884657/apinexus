<?php
/**
 * 文件：admin/system/theme.php
 * 作用：前台主题设置（预览、选择、保存）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'save_theme') {
        $themeId = isset($_POST['frontend_theme']) ? (string) $_POST['frontend_theme'] : '';
        $result = ThemeManager::setActive($themeId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('前台主题已保存', array('theme_id' => ThemeManager::activeId()));
    }

    AjaxResponse::error('未知操作', 400);
}

$themes = ThemeManager::listThemes();
$activeTheme = ThemeManager::activeId();

vs_admin_layout_start('主题设置', 'theme');
?>

<div class="vs-panel vs-theme-settings">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">前台主题</h2>
        <p class="vs-panel__desc">系统自动扫描 <code>core/theme/</code> 下含 <code>theme.json</code> 的文件夹并识别为可用主题。各主题样式与脚本完全独立。</p>
    </div>

    <?php if (empty($themes)): ?>
        <?php vs_render_notice('warning', '', '未找到可用主题，请确认 core/theme/ 目录完整。', array('compact' => true)); ?>
    <?php else: ?>
        <form method="post" action="" class="vs-form" id="themeSettingsForm" data-ajax="1" data-active-theme="<?php echo vs_e($activeTheme); ?>">
            <input type="hidden" name="action" value="save_theme">
            <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">

            <div class="vs-theme-gallery">
                <?php foreach ($themes as $theme): ?>
                    <?php $isActive = $theme['id'] === $activeTheme; ?>
                    <label class="vs-theme-card<?php echo $isActive ? ' is-active' : ''; ?>">
                        <input type="radio" name="frontend_theme" value="<?php echo vs_e($theme['id']); ?>"<?php echo $isActive ? ' checked' : ''; ?>>
                        <div class="vs-theme-card__preview">
                            <?php if ($theme['preview_url'] !== ''): ?>
                                <img src="<?php echo vs_e($theme['preview_url']); ?>" alt="<?php echo vs_e($theme['name']); ?> 预览" class="vs-theme-card__img" loading="lazy">
                            <?php else: ?>
                                <div class="vs-theme-card__placeholder">无预览图</div>
                            <?php endif; ?>
                            <?php if ($isActive): ?>
                                <span class="vs-theme-card__status">当前使用</span>
                            <?php endif; ?>
                        </div>
                        <div class="vs-theme-card__body">
                            <div class="vs-theme-card__name"><?php echo vs_e($theme['name']); ?></div>
                            <?php if ($theme['description'] !== ''): ?>
                                <p class="vs-theme-card__desc"><?php echo vs_e($theme['description']); ?></p>
                            <?php endif; ?>
                            <div class="vs-theme-card__meta">
                                <span class="vs-theme-card__id"><?php echo vs_e($theme['id']); ?></span>
                                <?php if ($theme['version'] !== ''): ?>
                                    <span>v<?php echo vs_e($theme['version']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="vs-form-actions">
                <button type="submit" class="vs-btn vs-btn--primary">保存主题</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php vs_admin_layout_end(array('theme-settings.js')); ?>
