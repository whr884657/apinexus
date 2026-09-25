<?php
/**
 * 主题3 后台设置面板（分组布局）
 */

if (!defined('VS_IN_ADMIN') && !defined('VS_ROOT')) {
    exit;
}

/**
 * @param array<int, array<string, mixed>> $schema
 * @param array<string, mixed> $values
 * @return void
 */
function vs_theme_admin_render_settings_three($schema, $values)
{
    $fields = array();
    foreach ($schema as $field) {
        if (!empty($field['key'])) {
            $fields[(string) $field['key']] = $field;
        }
    }

    $val = function ($key) use ($fields, $values) {
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        if (isset($fields[$key]['default'])) {
            return $fields[$key]['default'];
        }
        return '';
    };

    $renderSelect = function ($key) use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = isset($field['label']) ? (string) $field['label'] : $key;
        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
        echo '<select class="vs-input vs-select" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" data-vs-pick>';
        $options = !empty($field['options']) && is_array($field['options']) ? $field['options'] : array();
        foreach ($options as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ((string) $current === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select>';
        if ($key === 'stats_num_format') {
            echo '<p class="vs-form-hint">完整数字：实时有多少显示多少；单位转换：达到千/万后显示为 K+、W+</p>';
        }
        if ($key === 'footer_friend_links_display') {
            echo '<p class="vs-form-hint">仅在勾选「底部友情链接」时生效</p>';
        }
        if ($key === 'home_price_featured') {
            echo '<p class="vs-form-hint">首页三张展示卡中哪一张用推荐高亮样式；与真实充值套餐「荐」无关</p>';
        }
        echo '</div>';
    };

    $renderCheckbox = function ($key) use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = isset($field['label']) ? (string) $field['label'] : $key;
        $checked = $current === true || $current === 1 || $current === '1' || $current === 'true';
        echo '<div class="vs-theme-config-field vs-theme-config-field--check">';
        echo '<label class="vs-theme-config-check">';
        echo '<input type="checkbox" name="settings[' . vs_e($key) . ']" value="1"' . ($checked ? ' checked' : '') . '>';
        echo '<span>' . vs_e($label) . '</span></label>';
        echo '</div>';
    };

    $renderText = function ($key, $shortLabel = '') use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = $shortLabel !== '' ? $shortLabel : (isset($field['label']) ? (string) $field['label'] : $key);
        $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';
        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
        echo '<input type="text" class="vs-input" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" value="' . vs_e($current === null ? '' : (string) $current) . '" placeholder="' . vs_e($placeholder) . '"';
        if (preg_match('/^footer_social_\d+_value$/', $key)) {
            echo ' maxlength="512"';
        }
        echo '>';
        if (preg_match('/^footer_social_\d+_value$/', $key)) {
            echo '<p class="vs-form-hint">最多 512 个字符（链接、邮箱或二维码图片地址）</p>';
        }
        echo '</div>';
    };

    $renderTextarea = function ($key) use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = isset($field['label']) ? (string) $field['label'] : $key;
        $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';
        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
        echo '<textarea class="vs-textarea" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" rows="4" placeholder="' . vs_e($placeholder) . '">';
        echo vs_e($current === null ? '' : (string) $current);
        echo '</textarea>';
        if ($key === 'hero_lead') {
            echo '<p class="vs-form-hint">显示在首页大标题「全网接口一站调用」下方。留空则用默认介绍文案。</p>';
        }
        if (preg_match('/^home_price_\d+_features$/', $key)) {
            echo '<p class="vs-form-hint">每行一条卖点，最多取前 5 条显示在卡片底部。</p>';
        }
        if (preg_match('/^home_price_\d+_desc$/', $key)) {
            echo '<p class="vs-form-hint">显示在「到账积分」下方的说明文案，可写活动赠送等纯展示信息。</p>';
        }
        echo '</div>';
    };

    $socialTypes = isset($fields['footer_social_1_type']['options']) ? $fields['footer_social_1_type']['options'] : array();
    $qqModes = isset($fields['footer_social_1_qq_mode']['options']) ? $fields['footer_social_1_qq_mode']['options'] : array();

    echo '<div class="th3-admin-settings">';

    echo '<section class="th3-admin-settings__section">';
    echo '<h3 class="th3-admin-settings__title">主导航显示</h3>';
    echo '<p class="th3-admin-settings__hint">控制顶栏 / 侧栏 / 抽屉里是否显示对应入口。关闭后仅隐藏入口，页面 URL 仍可直接访问（不影响 SEO 收录）。默认全部开启。</p>';
    echo '<div class="th3-admin-settings__checks">';
    $renderCheckbox('nav_show_home');
    $renderCheckbox('nav_show_apis');
    $renderCheckbox('nav_show_articles');
    $renderCheckbox('nav_show_contributors');
    $renderCheckbox('nav_show_links');
    $renderCheckbox('nav_show_sponsor');
    $renderCheckbox('nav_show_about');
    echo '</div>';
    echo '</section>';

    echo '<section class="th3-admin-settings__section">';
    echo '<h3 class="th3-admin-settings__title">首页展示</h3>';
    echo '<div class="th3-admin-settings__grid th3-admin-settings__grid--2">';
    $renderSelect('stats_num_format');
    $renderSelect('home_preview_limit');
    echo '</div>';
    $renderTextarea('hero_lead');
    echo '</section>';

    echo '<section class="th3-admin-settings__section">';
    echo '<h3 class="th3-admin-settings__title">首页充值展示卡</h3>';
    echo '<p class="th3-admin-settings__hint">仅用于首页营销展示，与系统「充值套餐」完全独立，互不影响。按钮仍跳转用户中心充值页。名称、价格、积分、描述、卖点均可自定义。</p>';
    echo '<div class="th3-admin-settings__grid th3-admin-settings__grid--2">';
    $renderSelect('home_price_featured');
    echo '</div>';
    for ($slot = 1; $slot <= 3; $slot++) {
        echo '<div class="th3-admin-settings__slot" style="margin-top:12px;">';
        echo '<div class="th3-admin-settings__slot-head">展示卡 ' . $slot . '</div>';
        echo '<div class="th3-admin-settings__slot-body">';
        echo '<div class="th3-admin-settings__grid th3-admin-settings__grid--2">';
        $renderText('home_price_' . $slot . '_name');
        $renderText('home_price_' . $slot . '_money');
        $renderText('home_price_' . $slot . '_points');
        echo '</div>';
        $renderTextarea('home_price_' . $slot . '_desc');
        $renderTextarea('home_price_' . $slot . '_features');
        echo '</div></div>';
    }
    echo '</section>';

    echo '<section class="th3-admin-settings__section">';
    echo '<h3 class="th3-admin-settings__title">功能开关</h3>';
    echo '<div class="th3-admin-settings__checks">';
    $renderCheckbox('show_home_announce');
    $renderCheckbox('show_runtime');
    $renderCheckbox('show_footer_friend_links');
    $renderCheckbox('show_footer_qr');
    echo '</div>';
    echo '<div class="th3-admin-settings__grid th3-admin-settings__grid--2" style="margin-top:12px;">';
    $renderSelect('footer_friend_links_display');
    echo '</div>';
    echo '<p class="th3-admin-settings__hint">勾选「底部友情链接」后，页脚站名与描述整块换成友链；取消勾选则恢复站名与描述。</p>';
    echo '</section>';

    echo '<section class="th3-admin-settings__section">';
    echo '<h3 class="th3-admin-settings__title">页脚社交图标</h3>';
    echo '<p class="th3-admin-settings__hint">最多配置 3 个图标。微信填二维码图片地址；邮箱填地址；其它类型填跳转链接。QQ 类型可选链接跳转或二维码悬停。系统设置里的「页脚二维码」由上方「显示页脚二维码」控制，与这里的社交槽不是同一套。</p>';
    echo '<div class="th3-admin-settings__slots">';

    for ($slot = 1; $slot <= 3; $slot++) {
        $typeKey = 'footer_social_' . $slot . '_type';
        $modeKey = 'footer_social_' . $slot . '_qq_mode';
        $valueKey = 'footer_social_' . $slot . '_value';
        if (!isset($fields[$typeKey])) {
            continue;
        }

        $typeVal = (string) $val($typeKey);
        $modeVal = (string) $val($modeKey);
        $contentVal = $val($valueKey);

        echo '<div class="th3-admin-settings__slot">';
        echo '<div class="th3-admin-settings__slot-head">图标 ' . $slot . '</div>';
        echo '<div class="th3-admin-settings__slot-body">';

        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($typeKey) . '">类型</label>';
        echo '<select class="vs-input vs-select" id="ts_' . vs_e($typeKey) . '" name="settings[' . vs_e($typeKey) . ']" data-vs-pick>';
        foreach ($socialTypes as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ($typeVal === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($modeKey) . '">QQ 方式</label>';
        echo '<select class="vs-input vs-select" id="ts_' . vs_e($modeKey) . '" name="settings[' . vs_e($modeKey) . ']" data-vs-pick>';
        foreach ($qqModes as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ($modeVal === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select>';
        echo '<p class="vs-form-hint">仅在选择 QQ 类型时生效</p>';
        echo '</div>';

        $renderText($valueKey, '内容');

        echo '</div></div>';
    }

    echo '</div></section>';
    echo '</div>';
}
