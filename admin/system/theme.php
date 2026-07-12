<?php
/**
 * 文件：admin/system/theme.php
 * 作用：前台主题设置（预览、选择、保存、主题专属配置）
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

    if ($action === 'load_theme_settings') {
        $themeId = isset($_POST['theme_id']) ? (string) $_POST['theme_id'] : '';
        if (!ThemeManager::isValidTheme($themeId)) {
            AjaxResponse::error('无效的主题');
        }
        if (!ThemeManager::isThemeEnabled($themeId)) {
            AjaxResponse::error('该主题未启用，不可以进行设置');
        }
        AjaxResponse::success('ok', array(
            'theme_id' => $themeId,
            'name'     => ThemeManager::readMeta($themeId)['name'] ?? $themeId,
            'schema'   => ThemeManager::getSettingsSchema($themeId),
            'values'   => ThemeManager::readThemeData($themeId),
        ));
    }

    if ($action === 'save_theme_settings') {
        $themeId = isset($_POST['theme_id']) ? (string) $_POST['theme_id'] : '';
        if (!ThemeManager::isValidTheme($themeId)) {
            AjaxResponse::error('无效的主题');
        }
        if (!ThemeManager::isThemeEnabled($themeId)) {
            AjaxResponse::error('该主题未启用，不可以进行设置');
        }
        $raw = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : array();
        $data = ThemeManager::sanitizeThemeSettingsInput($themeId, $raw);
        $result = ThemeManager::writeThemeData($themeId, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('主题设置已保存', array('theme_id' => $themeId, 'values' => $data));
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
        <p class="vs-panel__desc">系统自动扫描 <code>core/theme/</code> 下含 <code>theme.json</code> 的文件夹并识别为可用主题。主题专属配置保存在各主题 <code>data/settings.json</code>，与站点通用 MySQL 配置分离。</p>
    </div>

    <?php if (empty($themes)): ?>
        <?php vs_render_notice('warning', '', '未找到可用主题，请确认 core/theme/ 目录完整。', array('compact' => true)); ?>
    <?php else: ?>
        <form method="post" action="" class="vs-form" id="themeSettingsForm" data-ajax="1" data-active-theme="<?php echo vs_e($activeTheme); ?>">
            <input type="hidden" name="action" value="save_theme">
            <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">

            <div class="vs-theme-gallery">
                <?php foreach ($themes as $theme): ?>
                    <?php
                    $isActive = $theme['id'] === $activeTheme;
                    $schemaCount = count(ThemeManager::getSettingsSchema($theme['id']));
                    ?>
                    <div class="vs-theme-card<?php echo $isActive ? ' is-active' : ''; ?>" data-theme-id="<?php echo vs_e($theme['id']); ?>">
                        <label class="vs-theme-card__select">
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
                        <div class="vs-theme-card__foot">
                            <button type="button"
                                    class="vs-btn vs-btn--default vs-btn--sm vs-theme-card__settings"
                                    data-theme-id="<?php echo vs_e($theme['id']); ?>"
                                    data-theme-name="<?php echo vs_e($theme['name']); ?>"
                                    data-enabled="<?php echo $isActive ? '1' : '0'; ?>"
                                    data-schema-count="<?php echo (int) $schemaCount; ?>">
                                设置
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="vs-form-actions">
                <button type="submit" class="vs-btn vs-btn--primary">保存主题</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="vs-modal-shell vs-modal-shell--theme-settings" id="themeSettingsModal" hidden>
    <div class="vs-modal vs-modal--theme-settings" role="dialog" aria-modal="true" aria-labelledby="themeSettingsModalTitle">
        <div class="vs-modal__head">
            <h3 class="vs-modal__title" id="themeSettingsModalTitle">主题设置</h3>
            <button type="button" class="vs-modal__close" id="themeSettingsModalClose" aria-label="关闭">&times;</button>
        </div>
        <form class="vs-form" id="themeConfigForm" data-ajax="1">
            <input type="hidden" name="action" value="save_theme_settings">
            <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">
            <input type="hidden" name="theme_id" id="themeConfigThemeId" value="">
            <div class="vs-modal__body" id="themeConfigFormBody">
                <p class="vs-theme-config-empty">该主题暂无可配置项，请在 theme.json 中声明 settings 字段。</p>
            </div>
            <div class="vs-modal__foot">
                <button type="button" class="vs-btn vs-btn--default" id="themeSettingsModalCancel">取消</button>
                <button type="submit" class="vs-btn vs-btn--primary" id="themeConfigSaveBtn">保存设置</button>
            </div>
        </form>
    </div>
</div>

<?php vs_admin_layout_end(array('theme-settings.js')); ?>
