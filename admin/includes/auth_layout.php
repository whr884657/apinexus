<?php
/**
 * 文件：admin/includes/auth_layout.php
 * 作用：后台认证页统一布局（登录/注册/忘记密码）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

/**
 * 输出 CSRF 隐藏字段
 *
 * @return void
 */
function vs_auth_csrf_field()
{
    echo '<input type="hidden" name="csrf_token" value="' . vs_e(AuthSecurity::csrfToken()) . '">' . "\n";
}

/**
 * 认证 POST 请求安全校验
 *
 * @return void
 */
function vs_auth_require_post()
{
    AuthSecurity::requireAuthPost();
}

/**
 * 背景色预加载脚本
 *
 * @return void
 */
function vs_auth_bg_script()
{
    vs_theme_bg_preload_script();
}

/**
 * 角色动画 HTML
 *
 * @param bool $withEyeIds 登录页使用带 id 的眼球元素
 * @return void
 */
function vs_auth_characters_html($withEyeIds = true)
{
    if ($withEyeIds) {
        echo '<div class="char char-purple" id="purple">';
        echo '<div class="eyes-wrap" id="purple-eyes">';
        echo '<div class="eyeball" id="purple-eye-l" style="width:18px;height:18px;"><div class="pupil" style="width:7px;height:7px;"></div></div>';
        echo '<div class="eyeball" id="purple-eye-r" style="width:18px;height:18px;"><div class="pupil" style="width:7px;height:7px;"></div></div>';
        echo '</div></div>';
        echo '<div class="char char-black" id="black">';
        echo '<div class="eyes-wrap" id="black-eyes">';
        echo '<div class="eyeball" id="black-eye-l" style="width:16px;height:16px;"><div class="pupil" style="width:6px;height:6px;"></div></div>';
        echo '<div class="eyeball" id="black-eye-r" style="width:16px;height:16px;"><div class="pupil" style="width:6px;height:6px;"></div></div>';
        echo '</div></div>';
    } else {
        echo '<div class="char char-purple" id="purple">';
        echo '<div class="eyes-wrap" id="purple-eyes">';
        echo '<div class="eyeball" style="width:18px;height:18px;"><div class="pupil" style="width:7px;height:7px;"></div></div>';
        echo '<div class="eyeball" style="width:18px;height:18px;"><div class="pupil" style="width:7px;height:7px;"></div></div>';
        echo '</div></div>';
        echo '<div class="char char-black" id="black">';
        echo '<div class="eyes-wrap" id="black-eyes">';
        echo '<div class="eyeball" style="width:16px;height:16px;"><div class="pupil" style="width:6px;height:6px;"></div></div>';
        echo '<div class="eyeball" style="width:16px;height:16px;"><div class="pupil" style="width:6px;height:6px;"></div></div>';
        echo '</div></div>';
    }

    echo '<div class="char char-orange" id="orange">';
    echo '<div class="eyes-wrap" id="orange-eyes">';
    echo '<div class="pupil-only" style="width:12px;height:12px;"></div>';
    echo '<div class="pupil-only" style="width:12px;height:12px;"></div>';
    echo '</div></div>';
    echo '<div class="char char-yellow" id="yellow">';
    echo '<div class="eyes-wrap" id="yellow-eyes">';
    echo '<div class="pupil-only" style="width:12px;height:12px;"></div>';
    echo '<div class="pupil-only" style="width:12px;height:12px;"></div>';
    echo '</div>';
    echo '<div class="mouth" id="yellow-mouth"></div>';
    echo '</div>';
}

/**
 * 认证页头部
 *
 * @param string $title
 * @return void
 */
function vs_auth_head($title)
{
    AuthSecurity::sendSecurityHeaders();

    $base = vs_base_url();
    $siteName = SiteContext::siteName();
    $favicon = SiteContext::siteFavicon();

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="zh">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="utf-8">' . "\n";
    echo '<title>' . vs_e(vs_page_title($title, $siteName)) . '</title>' . "\n";
    echo '<meta name="renderer" content="webkit">' . "\n";
    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">' . "\n";
    if ($favicon !== '') {
        echo '<link rel="icon" href="' . vs_e(vs_favicon_href($favicon)) . '" type="image/x-icon">' . "\n";
    }
    vs_auth_bg_script();
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/auth-login.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/toast.css?v=' . VS_VERSION . '">' . "\n";
    echo '<link rel="stylesheet" href="' . vs_e($base) . '/assets/css/theme-picker.css?v=' . VS_VERSION . '">' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
}

/**
 * 认证页左侧角色区
 *
 * @param bool $withEyeIds
 * @return void
 */
function vs_auth_left_panel($withEyeIds = true)
{
    echo '<div class="left">' . "\n";
    echo '<div class="characters-wrap">' . "\n";
    echo '<div class="characters" id="characters">' . "\n";
    vs_auth_characters_html($withEyeIds);
    echo '</div></div></div>' . "\n";
}

/**
 * 认证页底部脚本
 *
 * @param string $characterOptionsJs 可选 CHARACTER_OPTIONS 脚本
 * @return void
 */
function vs_auth_foot($characterOptionsJs = '')
{
    $base = vs_base_url();
    if ($characterOptionsJs !== '') {
        echo '<script>' . $characterOptionsJs . '</script>' . "\n";
    }
    echo '<script src="' . vs_e($base) . '/assets/js/common.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/theme-picker.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '<script src="' . vs_e($base) . '/assets/js/auth-characters.js?v=' . VS_VERSION . '"></script>' . "\n";
    echo '</body></html>';
}

/**
 * JSON 响应
 *
 * @param array $data
 * @param int   $code
 * @return void
 */
function vs_auth_json(array $data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 密码可见性切换按钮 HTML
 *
 * @return string
 */
function vs_auth_toggle_password_html()
{
    return '<button type="button" class="toggle-pw" id="togglePw" aria-label="切换密码可见性">'
        . '<svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"></path>'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"></path>'
        . '</svg>'
        . '<svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"></path>'
        . '</svg>'
        . '</button>';
}

/**
 * 提交按钮 HTML（hover 动效）
 *
 * @param string $label
 * @param string $id
 * @param string $extraClass
 * @return string
 */
function vs_auth_submit_btn($label, $id = '', $extraClass = '')
{
    $idAttr = $id !== '' ? ' id="' . vs_e($id) . '"' : '';
    $class = 'hover-btn' . ($extraClass !== '' ? ' ' . $extraClass : '');

    return '<button type="submit" class="' . vs_e($class) . '"' . $idAttr . '>'
        . '<span class="label">' . vs_e($label) . '</span>'
        . '<div class="overlay">'
        . '<span>' . vs_e($label) . '</span>'
        . '<svg class="arrow-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"></path>'
        . '</svg>'
        . '</div>'
        . '</button>';
}
