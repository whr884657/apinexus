<?php
/**
 * 文件：core/captcha/helper.php
 * 作用：认证页验证码挂载与脚本输出
 */

/**
 * 输出验证码挂载点
 *
 * @param string      $scene    Captcha::SCENE_*
 * @param string|null $only     null=任意；local=仅本地图；gt=仅极验三/四代
 * @return void
 */
function vs_captcha_field($scene, $only = null)
{
    if (!class_exists('Captcha') || !Captcha::sceneEnabled($scene)) {
        return;
    }
    $mode = Captcha::mode($scene);
    if ($only === 'local' && $mode !== Captcha::MODE_LOCAL) {
        return;
    }
    if ($only === 'gt' && $mode !== Captcha::MODE_GT3 && $mode !== Captcha::MODE_GT4) {
        return;
    }
    $GLOBALS['vs_captcha_scene'] = (string) $scene;
    if ($mode === Captcha::MODE_LOCAL) {
        $base = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
        $img = $base . '/core/captcha/image.php?scene=' . rawurlencode((string) $scene)
            . '&t=' . rawurlencode((string) microtime(true));
        echo '<div class="field vs-captcha-field vs-captcha-field--local" id="vsCaptchaBox" data-captcha-mode="local">'
            . '<div class="vs-captcha-local">'
            . '<input type="text" name="captcha_code" id="captchaCode" class="vs-captcha-local__input" '
            . 'autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" '
            . 'maxlength="5" placeholder="请输入图中字符（不区分大小写）" required aria-label="图形验证码">'
            . '<button type="button" class="vs-captcha-local__refresh" id="vsCaptchaRefresh" title="换一张" aria-label="换一张">'
            . '<img src="' . vs_e($img) . '" id="vsCaptchaImg" alt="图形验证码" width="120" height="40">'
            . '</button>'
            . '</div></div>' . "\n";
        return;
    }
    echo '<div class="field vs-captcha-field vs-captcha-field--gt" id="vsCaptchaBox" data-captcha-mode="'
        . vs_e($mode) . '" aria-label="行为验证"></div>' . "\n";
}

/**
 * @param string|null $scene
 * @return void
 */
function vs_captcha_js($scene = null)
{
    if ($scene === null && isset($GLOBALS['vs_captcha_scene'])) {
        $scene = (string) $GLOBALS['vs_captcha_scene'];
    }
    $scene = (string) $scene;
    if ($scene === '' || !class_exists('Captcha') || !Captcha::sceneEnabled($scene)) {
        return;
    }
    $boot = Captcha::publicBoot($scene);
    $base = vs_base_url();
    echo '<script>window.VS_CAPTCHA_BOOT='
        . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . ';</script>' . "\n";
    // 前台/用户主题：优先主题包 shell；管理员等回落根目录 assets/js
    $captchaSrc = '';
    if (class_exists('ThemeManager')) {
        $captchaSrc = ThemeManager::shellUrl('captcha.js');
    }
    if ($captchaSrc === '') {
        $captchaSrc = $base . '/assets/js/captcha.js?v=' . VS_VERSION;
    }
    echo '<script src="' . vs_e($captchaSrc) . '"></script>' . "\n";
}

/**
 * @deprecated
 * @param string $scene Captcha::SCENE_*
 * @return void
 */
function vs_auth_captcha_field($scene)
{
    vs_captcha_field($scene);
}

/**
 * @deprecated
 * @param string|null $scene
 * @return void
 */
function vs_auth_captcha_scripts($scene = null)
{
    vs_captcha_js($scene);
}
