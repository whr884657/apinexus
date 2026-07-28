<?php
/**
 * 文件：core/captcha/helper.php
 * 作用：认证页验证码挂载与脚本输出
 */

/**
 * @param string $scene
 * @return void
 */
function vs_captcha_field($scene)
{
    if (!class_exists('Captcha') || !Captcha::sceneEnabled($scene)) {
        return;
    }
    $GLOBALS['vs_captcha_scene'] = (string) $scene;
    $mode = Captcha::mode();
    if ($mode === Captcha::MODE_LOCAL) {
        $base = function_exists('vs_base_url') ? rtrim(vs_base_url(), '/') : '';
        $img = $base . '/core/captcha/image.php?t=' . rawurlencode((string) microtime(true));
        echo '<div class="field vs-captcha-field vs-captcha-field--local" id="vsCaptchaBox" data-captcha-mode="local">'
            . '<label class="field-label" for="captchaCode">验证码</label>'
            . '<div class="vs-captcha-local">'
            . '<input type="text" name="captcha_code" id="captchaCode" class="vs-captcha-local__input" '
            . 'autocomplete="off" maxlength="8" placeholder="请输入图中字符" required>'
            . '<button type="button" class="vs-captcha-local__refresh" id="vsCaptchaRefresh" title="换一张" aria-label="换一张">'
            . '<img src="' . vs_e($img) . '" id="vsCaptchaImg" alt="验证码" width="120" height="40">'
            . '</button>'
            . '</div></div>' . "\n";
        return;
    }
    echo '<div class="field vs-captcha-field" id="vsCaptchaBox" data-captcha-mode="'
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
    echo '<script src="' . vs_e($base) . '/assets/js/captcha.js?v=' . VS_VERSION . '"></script>' . "\n";
}

/** @deprecated 别名，请用 vs_captcha_field */
function vs_auth_captcha_field($scene)
{
    vs_captcha_field($scene);
}

/** @deprecated 别名，请用 vs_captcha_js */
function vs_auth_captcha_scripts($scene = null)
{
    vs_captcha_js($scene);
}
