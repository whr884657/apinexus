<?php
/**
 * 文件：core/AuthSecurity.php
 * 作用：认证页安全防护（CSRF、频率限制、登录防暴力）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class AuthSecurity
{
    const CSRF_SESSION_KEY = 'vs_csrf_token';

    /** 登录：单 IP 15 分钟内最多失败次数 */
    const LOGIN_IP_MAX = 10;
    const LOGIN_IP_WINDOW = 900;

    /** 登录：单账号 15 分钟内最多失败次数 */
    const LOGIN_USER_MAX = 5;
    const LOGIN_USER_WINDOW = 900;

    /** 发信验证码：单 IP 1 小时内最多次数 */
    const MAIL_IP_MAX = 8;
    const MAIL_IP_WINDOW = 3600;

    /** 发信验证码：单邮箱 1 小时内最多次数 */
    const MAIL_EMAIL_MAX = 5;
    const MAIL_EMAIL_WINDOW = 3600;

    /** 同一邮箱两次发信最短间隔（秒） */
    const MAIL_MIN_INTERVAL = 60;

    /** 重置密码提交：单 IP 1 小时内最多次数 */
    const RESET_IP_MAX = 15;
    const RESET_IP_WINDOW = 3600;

    /** OAuth 授权发起：单 IP 15 分钟内最多次数 */
    const OAUTH_IP_MAX = 20;
    const OAUTH_IP_WINDOW = 900;

    /** OAuth 回调：单 IP 15 分钟内最多次数 */
    const OAUTH_CALLBACK_IP_MAX = 30;
    const OAUTH_CALLBACK_IP_WINDOW = 900;

    /**
     * 配置 Session Cookie 安全属性（须在 session_start 之前调用）
     *
     * @return void
     */
    public static function configureSessionCookies()
    {
        $secure = self::isHttps();

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params(array(
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Strict',
            ));
        } else {
            session_set_cookie_params(0, '/; samesite=Strict', '', $secure, true);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }

    /**
     * 是否 HTTPS 访问
     *
     * @return bool
     */
    public static function isHttps()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * 认证页安全响应头
     *
     * @return void
     */
    public static function sendSecurityHeaders()
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * 初始化 CSRF Token
     *
     * @return void
     */
    public static function ensureCsrfToken()
    {
        if (empty($_SESSION[self::CSRF_SESSION_KEY])) {
            $_SESSION[self::CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        }
    }

    /**
     * 获取 CSRF Token
     *
     * @return string
     */
    public static function csrfToken()
    {
        self::ensureCsrfToken();
        return $_SESSION[self::CSRF_SESSION_KEY];
    }

    /**
     * 校验 CSRF Token
     *
     * @param string $token
     * @return bool
     */
    public static function validateCsrf($token)
    {
        if (!isset($_SESSION[self::CSRF_SESSION_KEY]) || !is_string($token)) {
            return false;
        }
        return hash_equals($_SESSION[self::CSRF_SESSION_KEY], $token);
    }

    /**
     * 校验请求来源（同源）
     *
     * @return bool
     */
    public static function validateSameOrigin()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        if ($host === '') {
            return false;
        }

        $expected = self::isHttps() ? 'https://' . $host : 'http://' . $host;

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            return strcasecmp($origin, $expected) === 0;
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            return stripos($_SERVER['HTTP_REFERER'], $expected) === 0;
        }

        return false;
    }

    /**
     * 客户端 IP
     *
     * @return string
     */
    public static function clientIp()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] !== '') {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidate = trim($parts[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        return $ip;
    }

    /**
     * 频率限制存储路径
     *
     * @return string
     */
    private static function rateLimitFile()
    {
        $dir = VS_ROOT . '/config/.security';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/rate_limit.json';
    }

    /**
     * 读取频率限制数据
     *
     * @return array
     */
    private static function loadRateData()
    {
        $file = self::rateLimitFile();
        if (!file_exists($file)) {
            return array();
        }
        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return array();
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : array();
    }

    /**
     * 保存频率限制数据
     *
     * @param array $data
     * @return void
     */
    private static function saveRateData(array $data)
    {
        $file = self::rateLimitFile();
        $now = time();
        foreach ($data as $key => $timestamps) {
            if (!is_array($timestamps)) {
                unset($data[$key]);
                continue;
            }
            $filtered = array();
            foreach ($timestamps as $t) {
                if (is_int($t) && ($now - $t) < 86400) {
                    $filtered[] = $t;
                }
            }
            if (empty($filtered)) {
                unset($data[$key]);
            } else {
                $data[$key] = $filtered;
            }
        }

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * 记录一次操作并检查是否超限
     *
     * @param string $bucket
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @param bool   $record 是否计入本次（检查发信间隔时可先检查再记录）
     * @return bool true 允许，false 已超限
     */
    public static function rateLimitAllow($bucket, $windowSeconds, $maxAttempts, $record = true)
    {
        $data = self::loadRateData();
        $now = time();
        $key = hash('sha256', $bucket);

        $timestamps = isset($data[$key]) ? $data[$key] : array();
        $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
            return ($now - (int) $t) < $windowSeconds;
        }));

        if (count($timestamps) >= $maxAttempts) {
            return false;
        }

        if ($record) {
            $timestamps[] = $now;
            $data[$key] = $timestamps;
            self::saveRateData($data);
        }

        return true;
    }

    /**
     * 距上次操作经过的秒数（无记录返回 -1）
     *
     * @param string $bucket
     * @return int
     */
    public static function secondsSinceLastHit($bucket)
    {
        $data = self::loadRateData();
        $key = hash('sha256', $bucket);
        if (!isset($data[$key]) || !is_array($data[$key]) || empty($data[$key])) {
            return -1;
        }
        $last = max($data[$key]);
        return time() - (int) $last;
    }

    /**
     * 检查登录是否被限制
     *
     * @param string $username
     * @return string|null 错误信息或 null
     */
    public static function checkLoginAllowed($username)
    {
        $ip = self::clientIp();
        $ipBucket = 'login_fail_ip:' . $ip;
        $userBucket = 'login_fail_user:' . strtolower(trim($username));

        if (!self::rateLimitAllow($ipBucket, self::LOGIN_IP_WINDOW, self::LOGIN_IP_MAX, false)) {
            return '登录尝试过于频繁，请 15 分钟后再试';
        }
        if ($username !== '' && !self::rateLimitAllow($userBucket, self::LOGIN_USER_WINDOW, self::LOGIN_USER_MAX, false)) {
            return '该账号尝试次数过多，请 15 分钟后再试';
        }

        return null;
    }

    /**
     * 记录登录失败
     *
     * @param string $username
     * @return void
     */
    public static function recordLoginFailure($username)
    {
        $ip = self::clientIp();
        self::rateLimitAllow('login_fail_ip:' . $ip, self::LOGIN_IP_WINDOW, self::LOGIN_IP_MAX, true);
        if (trim($username) !== '') {
            self::rateLimitAllow('login_fail_user:' . strtolower(trim($username)), self::LOGIN_USER_WINDOW, self::LOGIN_USER_MAX, true);
        }
    }

    /**
     * 检查发验证码是否允许
     *
     * @param string $email
     * @return string|null
     */
    public static function checkMailCodeAllowed($email)
    {
        $ip = self::clientIp();
        $emailKey = strtolower(trim($email));
        $ipBucket = 'mail_code_ip:' . $ip;
        $emailBucket = 'mail_code_email:' . $emailKey;
        $intervalBucket = 'mail_code_interval:' . $emailKey;

        if (!self::rateLimitAllow($ipBucket, self::MAIL_IP_WINDOW, self::MAIL_IP_MAX, false)) {
            return '发送过于频繁，请稍后再试';
        }
        if (!self::rateLimitAllow($emailBucket, self::MAIL_EMAIL_WINDOW, self::MAIL_EMAIL_MAX, false)) {
            return '该邮箱发送次数过多，请稍后再试';
        }

        $since = self::secondsSinceLastHit($intervalBucket);
        if ($since >= 0 && $since < self::MAIL_MIN_INTERVAL) {
            $wait = self::MAIL_MIN_INTERVAL - $since;
            return '发送过于频繁，请 ' . $wait . ' 秒后再试';
        }

        return null;
    }

    /**
     * 记录一次验证码发信
     *
     * @param string $email
     * @return void
     */
    public static function recordMailCodeSent($email)
    {
        $ip = self::clientIp();
        $emailKey = strtolower(trim($email));
        self::rateLimitAllow('mail_code_ip:' . $ip, self::MAIL_IP_WINDOW, self::MAIL_IP_MAX, true);
        self::rateLimitAllow('mail_code_email:' . $emailKey, self::MAIL_EMAIL_WINDOW, self::MAIL_EMAIL_MAX, true);
        self::rateLimitAllow('mail_code_interval:' . $emailKey, self::MAIL_MIN_INTERVAL, 1, true);
    }

    /**
     * 检查重置密码提交频率
     *
     * @return string|null
     */
    public static function checkResetSubmitAllowed()
    {
        $ip = self::clientIp();
        if (!self::rateLimitAllow('reset_submit_ip:' . $ip, self::RESET_IP_WINDOW, self::RESET_IP_MAX, false)) {
            return '操作过于频繁，请稍后再试';
        }
        return null;
    }

    /**
     * 记录重置密码提交
     *
     * @return void
     */
    public static function recordResetSubmit()
    {
        self::rateLimitAllow('reset_submit_ip:' . self::clientIp(), self::RESET_IP_WINDOW, self::RESET_IP_MAX, true);
    }

    /**
     * OAuth 授权发起频率限制
     *
     * @return string|null
     */
    public static function checkOAuthStartAllowed()
    {
        $ip = self::clientIp();
        if (!self::rateLimitAllow('oauth_start_ip:' . $ip, self::OAUTH_IP_WINDOW, self::OAUTH_IP_MAX, false)) {
            return '第三方登录操作过于频繁，请稍后再试';
        }
        return null;
    }

    /**
     * @return void
     */
    public static function recordOAuthStart()
    {
        self::rateLimitAllow('oauth_start_ip:' . self::clientIp(), self::OAUTH_IP_WINDOW, self::OAUTH_IP_MAX, true);
    }

    /**
     * OAuth 回调频率限制
     *
     * @return string|null
     */
    public static function checkOAuthCallbackAllowed()
    {
        $ip = self::clientIp();
        if (!self::rateLimitAllow('oauth_callback_ip:' . $ip, self::OAUTH_CALLBACK_IP_WINDOW, self::OAUTH_CALLBACK_IP_MAX, false)) {
            return '第三方登录回调过于频繁，请稍后再试';
        }
        return null;
    }

    /**
     * @return void
     */
    public static function recordOAuthCallback()
    {
        self::rateLimitAllow('oauth_callback_ip:' . self::clientIp(), self::OAUTH_CALLBACK_IP_WINDOW, self::OAUTH_CALLBACK_IP_MAX, true);
    }

    /**
     * 认证 POST 统一校验（方法、来源、CSRF）
     *
     * @return void
     */
    public static function requireAuthPost()
    {
        self::sendSecurityHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if (function_exists('vs_auth_json')) {
                vs_auth_json(array('code' => 0, 'msg' => '无效请求'), 405);
            }
            http_response_code(405);
            exit;
        }

        if (!self::validateSameOrigin()) {
            if (function_exists('vs_auth_json')) {
                vs_auth_json(array('code' => 0, 'msg' => '请求来源无效，请从本站页面操作'), 403);
            }
            http_response_code(403);
            exit;
        }

        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!self::validateCsrf($token)) {
            if (function_exists('vs_auth_json')) {
                vs_auth_json(array('code' => 0, 'msg' => '请求无效，请刷新页面后重试'), 403);
            }
            http_response_code(403);
            exit;
        }
    }
}
