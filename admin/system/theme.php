<?php
/**
 * 文件：admin/system/theme.php
 * 作用：前台主题设置（切换默认主题）
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

<div class="vs-panel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">前台主题</h2>
        <p class="vs-panel__desc">选择用户访问前台页面时加载的主题包。页面 PHP 路由不变，内容由 <code>core/theme/{主题}/</code> 下的模板动态渲染。</p>
    </div>

    <?php if (empty($themes)): ?>
        <?php vs_render_notice('warning', '', '未找到可用主题，请确认 core/theme/default 目录完整。', array('compact' => true)); ?>
    <?php else: ?>
        <form method="post" action="" class="vs-form" id="themeSettingsForm" data-ajax="1">
            <input type="hidden" name="action" value="save_theme">
            <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">

            <div class="vs-theme-picker-grid">
                <?php foreach ($themes as $theme): ?>
                    <?php $isActive = $theme['id'] === $activeTheme; ?>
                    <label class="vs-theme-picker-card<?php echo $isActive ? ' is-active' : ''; ?>">
                        <input type="radio" name="frontend_theme" value="<?php echo vs_e($theme['id']); ?>"<?php echo $isActive ? ' checked' : ''; ?>>
                        <div class="vs-theme-picker-card__name"><?php echo vs_e($theme['name']); ?></div>
                        <?php if ($theme['description'] !== ''): ?>
                            <p class="vs-theme-picker-card__desc"><?php echo vs_e($theme['description']); ?></p>
                        <?php endif; ?>
                        <div class="vs-theme-picker-card__meta">
                            ID: <?php echo vs_e($theme['id']); ?>
                            <?php if ($theme['version'] !== ''): ?>
                                · v<?php echo vs_e($theme['version']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($isActive): ?>
                            <span class="vs-theme-picker-card__badge">当前使用</span>
                        <?php endif; ?>
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
