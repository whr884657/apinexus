<?php
/**
 * 文件：core/captcha/helper.php
 * 作用：认证页验证码挂载与脚本输出
 */

/**
 * 输出验证码挂载点
 *
 * @param string      $scene    Captcha::SCENE_*
 * @param string|null $only     null=任意；local=仅本地图；gt=仅小组件类（极验3/4、Turnstile）
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
    if ($only === 'gt'
        && $mode !== Captcha::MODE_GT3
        && $mode !== Captcha::MODE_GT4
        && $mode !== Captcha::MODE_CF
    ) {
        return;
    }
    $GLOBALS['vs_captcha_scene'] = (string) $scene;
    if ($mode === Captcha::MODE_LOCAL) {
        $base = function_exists('vs_site_base_path') ? vs_site_base_path() : '';
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
    $aria = ($mode === Captcha::MODE_CF) ? 'Cloudflare 人机验证' : '行为验证';
    $modClass = ($mode === Captcha::MODE_CF) ? 'vs-captcha-field--cf' : 'vs-captcha-field--gt';
    echo '<div class="field vs-captcha-field ' . $modClass . '" id="vsCaptchaBox" data-captcha-mode="'
        . vs_e($mode) . '" aria-label="' . vs_e($aria) . '"></div>' . "\n";
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
    $base = vs_site_base_path();
    $mode = isset($boot['mode']) ? (string) $boot['mode'] : 'local';
    // 极验 / Turnstile：提前建连，缩短组件出现等待
    if ($mode === Captcha::MODE_CF) {
        echo '<link rel="dns-prefetch" href="https://challenges.cloudflare.com">' . "\n";
        echo '<link rel="preconnect" href="https://challenges.cloudflare.com" crossorigin>' . "\n";
    } elseif ($mode === Captcha::MODE_GT3 || $mode === Captcha::MODE_GT4) {
        echo '<link rel="dns-prefetch" href="https://static.geetest.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://static.geevisit.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://api.geetest.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://api.geevisit.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://monitor.geetest.com">' . "\n";
        echo '<link rel="preconnect" href="https://static.geetest.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://static.geevisit.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://api.geetest.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://api.geevisit.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://monitor.geetest.com" crossorigin>' . "\n";
        if ($mode === Captcha::MODE_GT4) {
            echo '<link rel="dns-prefetch" href="https://gcaptcha4.geetest.com">' . "\n";
            echo '<link rel="dns-prefetch" href="https://gcaptcha4.geevisit.com">' . "\n";
            echo '<link rel="dns-prefetch" href="https://gcaptcha4.gsensebot.com">' . "\n";
            echo '<link rel="preconnect" href="https://gcaptcha4.geetest.com" crossorigin>' . "\n";
            echo '<link rel="preconnect" href="https://gcaptcha4.geevisit.com" crossorigin>' . "\n";
            echo '<link rel="preconnect" href="https://gcaptcha4.gsensebot.com" crossorigin>' . "\n";
            $localGt = $base . '/assets/js/geetest/gt4.js';
            if (is_file(VS_ROOT . '/assets/js/geetest/gt4.js')) {
                echo '<link rel="preload" href="' . vs_e($localGt) . '" as="script">' . "\n";
            }
        } else {
            $localGt = $base . '/assets/js/geetest/gt.js';
            if (is_file(VS_ROOT . '/assets/js/geetest/gt.js')) {
                echo '<link rel="preload" href="' . vs_e($localGt) . '" as="script">' . "\n";
            }
        }
    }
    echo '<script>window.VS_CAPTCHA_BOOT='
        . vs_json_script($boot)
        . ';</script>' . "\n";
    // 全站公共：验证码逻辑仅根目录 assets/js（管理员 + 三主题认证页统一加载）
    $captchaSrc = $base . '/assets/js/captcha.js?v=' . VS_VERSION;
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
