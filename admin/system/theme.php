<?php
/**
 * 文件：admin/system/theme.php
 * 作用：前台主题设置（主题切换 + 主题配置）
 */

require_once dirname(__DIR__) . '/init.php';

/**
 * 渲染主题配置表单字段
 *
 * @param string $themeId
 * @return void
 */
function vs_admin_render_theme_config_fields($themeId)
{
    $schema = ThemeManager::getSettingsSchema($themeId);
    $values = ThemeManager::readThemeData($themeId);

    if (empty($schema)) {
        echo '<p class="vs-theme-config-empty">当前主题暂无可调整的项目</p>';
        return;
    }

    $statKeys = array();
    $otherFields = array();
    foreach ($schema as $field) {
        $key = $field['key'];
        if ($field['type'] === 'checkbox' && strpos($key, 'show_stat_') === 0) {
            $statKeys[] = $field;
        } else {
            $otherFields[] = $field;
        }
    }

    $renderField = function ($field) use ($values) {
        $key = $field['key'];
        $label = $field['label'];
        $type = $field['type'];
        $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';
        $val = array_key_exists($key, $values) ? $values[$key] : (isset($field['default']) ? $field['default'] : '');

        echo '<div class="vs-theme-config-field' . ($type === 'checkbox' ? ' vs-theme-config-field--check' : '') . '">';
        if ($type === 'checkbox') {
            $checked = $val === true || $val === 1 || $val === '1' || $val === 'true';
            echo '<label class="vs-theme-config-check">';
            echo '<input type="checkbox" name="settings[' . vs_e($key) . ']" value="1"' . ($checked ? ' checked' : '') . '>';
            echo '<span>' . vs_e($label) . '</span></label>';
        } elseif ($type === 'textarea') {
            echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
            echo '<textarea class="vs-textarea" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" rows="3" placeholder="' . vs_e($placeholder) . '">';
            echo vs_e($val === null ? '' : (string) $val);
            echo '</textarea>';
        } elseif ($type === 'select') {
            echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
            echo '<select class="vs-input" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']">';
            $options = !empty($field['options']) ? $field['options'] : array();
            foreach ($options as $opt) {
                if (!is_array($opt) || !isset($opt['value'])) {
                    continue;
                }
                $optVal = (string) $opt['value'];
                $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
                $selected = ((string) $val === $optVal) ? ' selected' : '';
                echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
            }
            echo '</select>';
        } else {
            $inputType = $type === 'number' ? 'number' : 'text';
            echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
            echo '<input type="' . vs_e($inputType) . '" class="vs-input" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" value="' . vs_e($val === null ? '' : (string) $val) . '" placeholder="' . vs_e($placeholder) . '">';
        }
        echo '</div>';
    };

    foreach ($otherFields as $field) {
        if ($field['key'] === 'show_stats' && count($statKeys) > 0) {
            $renderField($field);
            echo '<div class="vs-theme-config-group">';
            echo '<p class="vs-theme-config-group__title">首页统计项</p>';
            echo '<div class="vs-theme-config-checks">';
            foreach ($statKeys as $sf) {
                $renderField($sf);
            }
            echo '</div></div>';
            $statKeys = array();
            continue;
        }
        $renderField($field);
    }
    if (count($statKeys) > 0) {
        echo '<div class="vs-theme-config-group">';
        echo '<p class="vs-theme-config-group__title">首页统计项</p>';
        echo '<div class="vs-theme-config-checks">';
        foreach ($statKeys as $sf) {
            $renderField($sf);
        }
        echo '</div></div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'save_theme') {
        $themeId = isset($_POST['frontend_theme']) ? (string) $_POST['frontend_theme'] : '';
        $result = ThemeManager::setActive($themeId);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $meta = ThemeManager::readMeta(ThemeManager::activeId());
        AjaxResponse::success('前台主题已保存', array(
            'theme_id'   => ThemeManager::activeId(),
            'theme_name' => isset($meta['name']) ? (string) $meta['name'] : ThemeManager::activeId(),
        ));
    }

    if ($action === 'load_theme_settings') {
        $themeId = ThemeManager::activeId();
        if (!ThemeManager::isValidTheme($themeId)) {
            AjaxResponse::error('无效的主题');
        }
        ob_start();
        vs_admin_render_theme_config_fields($themeId);
        $html = ob_get_clean();
        $meta = ThemeManager::readMeta($themeId);
        AjaxResponse::success('ok', array(
            'theme_id'   => $themeId,
            'theme_name' => isset($meta['name']) ? (string) $meta['name'] : $themeId,
            'html'       => $html,
            'has_schema' => count(ThemeManager::getSettingsSchema($themeId)) > 0,
        ));
    }

    if ($action === 'save_theme_settings') {
        $themeId = ThemeManager::activeId();
        if (!ThemeManager::isValidTheme($themeId)) {
            AjaxResponse::error('无效的主题');
        }
        $raw = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : array();
        $data = ThemeManager::sanitizeThemeSettingsInput($themeId, $raw);
        if (!empty($data['show_runtime']) && !vs_site_has_runtime()) {
            AjaxResponse::error('当前系统并没有配置网站运行时间，请到系统设置当中进行配置。');
        }
        $result = ThemeManager::writeThemeData($themeId, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('主题设置已保存', array('theme_id' => $themeId));
    }

    AjaxResponse::error('未知操作', 400);
}

$themes = ThemeManager::listThemes();
$activeTheme = ThemeManager::activeId();
$activeMeta = ThemeManager::readMeta($activeTheme);
$activeThemeName = isset($activeMeta['name']) ? (string) $activeMeta['name'] : $activeTheme;

vs_admin_layout_start('主题设置', 'theme');
?>

<div class="vs-panel vs-theme-settings" id="themeSettingsPage" data-active-theme="<?php echo vs_e($activeTheme); ?>" data-active-name="<?php echo vs_e($activeThemeName); ?>">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">前台主题</h2>
    </div>

    <nav class="vs-product-tabs vs-theme-settings__tabs" aria-label="主题设置导航">
        <button type="button" class="vs-product-tabs__btn is-active" data-tab="switch" aria-selected="true">主题切换</button>
        <button type="button" class="vs-product-tabs__btn" data-tab="config" aria-selected="false">主题设置</button>
    </nav>

    <?php if (empty($themes)): ?>
        <?php vs_render_notice('warning', '', '未找到可用主题，请稍后重试或联系管理员。', array('compact' => true)); ?>
    <?php else: ?>
        <div class="vs-theme-settings-desk">
            <div class="vs-product-tab-panel is-active" data-panel="switch" id="themeSwitchPanel">
                <h3 class="vs-theme-desk-title">主题切换</h3>
                <form method="post" action="" class="vs-form" id="themeSettingsForm" data-ajax="1">
                    <input type="hidden" name="action" value="save_theme">
                    <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">

                    <div class="vs-theme-gallery-wrap">
                        <div class="vs-theme-gallery">
                            <?php foreach ($themes as $theme): ?>
                                <?php $isActive = $theme['id'] === $activeTheme; ?>
                                <label class="vs-theme-card<?php echo $isActive ? ' is-active' : ''; ?>" data-theme-id="<?php echo vs_e($theme['id']); ?>">
                                    <input type="radio" name="frontend_theme" value="<?php echo vs_e($theme['id']); ?>"<?php echo $isActive ? ' checked' : ''; ?>>
                                    <div class="vs-theme-card__preview">
                                        <?php if ($theme['preview_url'] !== ''): ?>
                                            <img src="<?php echo vs_e($theme['preview_url']); ?>" alt="<?php echo vs_e($theme['name']); ?>" class="vs-theme-card__img" loading="lazy">
                                        <?php else: ?>
                                            <div class="vs-theme-card__placeholder">预览</div>
                                        <?php endif; ?>
                                        <?php if ($isActive): ?>
                                            <span class="vs-theme-card__status">当前使用</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vs-theme-card__body">
                                        <div class="vs-theme-card__name"><?php echo vs_e($theme['name']); ?></div>
                                        <?php if ($theme['version'] !== ''): ?>
                                            <div class="vs-theme-card__version">v<?php echo vs_e($theme['version']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="vs-form-actions">
                        <button type="submit" class="vs-btn vs-btn--primary">保存主题</button>
                    </div>
                </form>
            </div>

            <div class="vs-product-tab-panel" data-panel="config" id="themeConfigPanel" hidden>
                <h3 class="vs-theme-desk-title">主题设置</h3>
                <div class="vs-theme-config-head">
                    <span class="vs-theme-config-head__label">正在配置</span>
                    <strong class="vs-theme-config-head__name" id="themeConfigActiveName"><?php echo vs_e($activeThemeName); ?></strong>
                </div>
                <form method="post" action="" class="vs-form vs-theme-config-form" id="themeConfigForm" data-ajax="1">
                    <input type="hidden" name="action" value="save_theme_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo vs_e(AuthSecurity::csrfToken()); ?>">
                    <div class="vs-theme-config-form__body" id="themeConfigFormBody">
                        <?php vs_admin_render_theme_config_fields($activeTheme); ?>
                    </div>
                    <div class="vs-form-actions">
                        <button type="submit" class="vs-btn vs-btn--primary" id="themeConfigSaveBtn"<?php echo count(ThemeManager::getSettingsSchema($activeTheme)) === 0 ? ' disabled' : ''; ?>>保存设置</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php vs_admin_layout_end(array('theme-settings.js')); ?>
