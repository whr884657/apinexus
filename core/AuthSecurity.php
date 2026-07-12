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
    const MAIL_TICKET_SESSION_KEY = 'vs_mail_tickets';

    const MAIL_PURPOSE_USER_FORGOT = 'user_forgot';
    const MAIL_PURPOSE_USER_REGISTER = 'user_register';
    const MAIL_PURPOSE_ADMIN_FORGOT = 'admin_forgot';

    /** 发信一次性票据有效期（秒） */
    const MAIL_TICKET_TTL = 600;

    /** 登录：单 IP 15 分钟内最多失败次数 */
    const LOGIN_IP_MAX = 10;
    const LOGIN_IP_WINDOW = 900;

    /** 登录：单账号 15 分钟内最多失败次数 */
    const LOGIN_USER_MAX = 5;
    const LOGIN_USER_WINDOW = 900;

    /** 发信验证码：单 IP 1 小时内最多次数 */
    const MAIL_IP_MAX = 3;
    const MAIL_IP_WINDOW = 3600;

    /** 发信验证码：单 IP 24 小时内最多次数（防轮换邮箱轰炸） */
    const MAIL_IP_DAILY_MAX = 8;
    const MAIL_IP_DAILY_WINDOW = 86400;

    /** 发信验证码：单邮箱 1 小时内最多次数 */
    const MAIL_EMAIL_MAX = 3;
    const MAIL_EMAIL_WINDOW = 3600;

    /** 发信验证码：单邮箱 24 小时内最多次数 */
    const MAIL_EMAIL_DAILY_MAX = 8;
    const MAIL_EMAIL_DAILY_WINDOW = 86400;

    /** 同一邮箱两次发信最短间隔（秒） */
    const MAIL_MIN_INTERVAL = 120;

    /** 同一 IP 任意验证码发信最短间隔（秒），防接口工具连续轰炸 */
    const MAIL_IP_MIN_INTERVAL = 60;

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
                'samesite' => 'Lax',
            ));
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
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
     * 记录一次操作并检查是否超限（MySQL 存储）
     *
     * @param string $bucket
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @param bool   $record
     * @return bool true 允许，false 已超限
     */
    public static function rateLimitAllow($bucket, $windowSeconds, $maxAttempts, $record = true)
    {
        return RateLimitStore::allow($bucket, $windowSeconds, $maxAttempts, $record);
    }

    /**
     * 距上次操作经过的秒数（无记录返回 -1）
     *
     * @param string $bucket
     * @return int
     */
    public static function secondsSinceLastHit($bucket)
    {
        return RateLimitStore::secondsSinceLastHit($bucket);
    }

    /**
     * 签发发信一次性票据（页面加载时写入表单）
     *
     * @param string $purpose
     * @return string
     */
    public static function issueMailTicket($purpose)
    {
        $purpose = self::normalizeMailPurpose($purpose);
        $token = bin2hex(random_bytes(16));
        if (!isset($_SESSION[self::MAIL_TICKET_SESSION_KEY]) || !is_array($_SESSION[self::MAIL_TICKET_SESSION_KEY])) {
            $_SESSION[self::MAIL_TICKET_SESSION_KEY] = array();
        }
        $_SESSION[self::MAIL_TICKET_SESSION_KEY][$purpose] = array(
            'token'   => $token,
            'expires' => time() + self::MAIL_TICKET_TTL,
        );
        return $token;
    }

    /**
     * 校验并消耗发信票据（一次性，防抓包重放）
     *
     * @param string $purpose
     * @param string $token
     * @return bool
     */
    public static function validateAndConsumeMailTicket($purpose, $token)
    {
        $purpose = self::normalizeMailPurpose($purpose);
        if (!is_string($token) || $token === '') {
            return false;
        }
        if (!isset($_SESSION[self::MAIL_TICKET_SESSION_KEY][$purpose]) || !is_array($_SESSION[self::MAIL_TICKET_SESSION_KEY][$purpose])) {
            return false;
        }
        $saved = $_SESSION[self::MAIL_TICKET_SESSION_KEY][$purpose];
        unset($_SESSION[self::MAIL_TICKET_SESSION_KEY][$purpose]);
        if (!isset($saved['token'], $saved['expires'])) {
            return false;
        }
        if ((int) $saved['expires'] < time()) {
            return false;
        }
        return hash_equals((string) $saved['token'], $token);
    }

    /**
     * JSON 响应附加新的发信票据
     *
     * @param string $purpose
     * @param array  $data
     * @return array
     */
    public static function withMailTicket($purpose, array $data)
    {
        $data['mail_ticket'] = self::issueMailTicket($purpose);
        return $data;
    }

    /**
     * @param string $purpose
     * @return string
     */
    private static function normalizeMailPurpose($purpose)
    {
        $allowed = array(
            self::MAIL_PURPOSE_USER_FORGOT,
            self::MAIL_PURPOSE_USER_REGISTER,
            self::MAIL_PURPOSE_ADMIN_FORGOT,
        );
        return in_array($purpose, $allowed, true) ? $purpose : self::MAIL_PURPOSE_USER_FORGOT;
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
     * 发信限流提示：距下次可发还需等待的秒数
     *
     * @param int $since 距上次经过秒数，-1 表示无记录
     * @param int $minInterval
     * @return int 0 表示无需等待
     */
    private static function mailWaitSeconds($since, $minInterval)
    {
        if ($since < 0 || $since >= $minInterval) {
            return 0;
        }
        return $minInterval - $since;
    }

    /**
     * 检查发验证码是否允许（仅检查，不计入次数）
     *
     * @param string $email
     * @return string|null
     */
    public static function checkMailCodeAllowed($email)
    {
        $ip = self::clientIp();
        $emailKey = strtolower(trim($email));
        $ipBucket = 'mail_code_ip:' . $ip;
        $ipDailyBucket = 'mail_code_ip_daily:' . $ip;
        $emailBucket = 'mail_code_email:' . $emailKey;
        $emailDailyBucket = 'mail_code_email_daily:' . $emailKey;
        $intervalBucket = 'mail_code_interval:' . $emailKey;
        $ipIntervalBucket = 'mail_code_ip_interval:' . $ip;

        $ipWait = self::mailWaitSeconds(self::secondsSinceLastHit($ipIntervalBucket), self::MAIL_IP_MIN_INTERVAL);
        if ($ipWait > 0) {
            return '发送过于频繁，请 ' . $ipWait . ' 秒后再试';
        }

        $emailWait = self::mailWaitSeconds(self::secondsSinceLastHit($intervalBucket), self::MAIL_MIN_INTERVAL);
        if ($emailWait > 0) {
            return '发送过于频繁，请 ' . $emailWait . ' 秒后再试';
        }

        if (!self::rateLimitAllow($ipBucket, self::MAIL_IP_WINDOW, self::MAIL_IP_MAX, false)) {
            return '发送过于频繁，请稍后再试';
        }
        if (!self::rateLimitAllow($ipDailyBucket, self::MAIL_IP_DAILY_WINDOW, self::MAIL_IP_DAILY_MAX, false)) {
            return '当前网络发送次数过多，请稍后再试';
        }
        if (!self::rateLimitAllow($emailBucket, self::MAIL_EMAIL_WINDOW, self::MAIL_EMAIL_MAX, false)) {
            return '该邮箱发送次数过多，请稍后再试';
        }
        if (!self::rateLimitAllow($emailDailyBucket, self::MAIL_EMAIL_DAILY_WINDOW, self::MAIL_EMAIL_DAILY_MAX, false)) {
            return '该邮箱今日发送次数已达上限，请明天再试';
        }

        return null;
    }

    /**
     * 记录一次验证码发信请求（无论是否实际发信、邮箱是否已注册，均计入）
     *
     * @param string $email
     * @return void
     */
    public static function recordMailCodeAttempt($email)
    {
        $ip = self::clientIp();
        $emailKey = strtolower(trim($email));
        self::rateLimitAllow('mail_code_ip:' . $ip, self::MAIL_IP_WINDOW, self::MAIL_IP_MAX, true);
        self::rateLimitAllow('mail_code_ip_daily:' . $ip, self::MAIL_IP_DAILY_WINDOW, self::MAIL_IP_DAILY_MAX, true);
        self::rateLimitAllow('mail_code_email:' . $emailKey, self::MAIL_EMAIL_WINDOW, self::MAIL_EMAIL_MAX, true);
        self::rateLimitAllow('mail_code_email_daily:' . $emailKey, self::MAIL_EMAIL_DAILY_WINDOW, self::MAIL_EMAIL_DAILY_MAX, true);
        self::rateLimitAllow('mail_code_interval:' . $emailKey, self::MAIL_MIN_INTERVAL, 1, true);
        self::rateLimitAllow('mail_code_ip_interval:' . $ip, self::MAIL_IP_MIN_INTERVAL, 1, true);
    }

    /**
     * @deprecated 请改用 recordMailCodeAttempt
     * @param string $email
     * @return void
     */
    public static function recordMailCodeSent($email)
    {
        self::recordMailCodeAttempt($email);
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
