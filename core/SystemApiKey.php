<?php
/**
 * 文件：core/SystemApiKey.php
 * 作用：系统级密钥（归档计划任务、卡密对接 API 等共用）；支持 Bearer / Header / Query / JSON 鉴权
 */

class SystemApiKey
{
    const CONFIG_KEY = 'system_api_key';
    /** @deprecated 仅迁移读取，业务禁止再写 */
    const LEGACY_CONFIG_KEY = 'apilog_cron_key';

    /** @var string|null 缓存 php://input，避免鉴权与业务各读一次导致第二次为空 */
    private static $rawBody = null;
    /** @var bool */
    private static $rawBodyLoaded = false;

    /**
     * 读取并缓存请求体（JSON 鉴权与业务共用）
     *
     * @return string
     */
    public static function requestBody()
    {
        if (!self::$rawBodyLoaded) {
            self::$rawBodyLoaded = true;
            $raw = file_get_contents('php://input');
            self::$rawBody = is_string($raw) ? $raw : '';
        }
        return self::$rawBody;
    }

    /**
     * 一次性：旧 apilog_cron_key → system_api_key，并删除旧键
     *
     * @return void
     */
    public static function migrateFromLegacy()
    {
        try {
            $all = Config::all();
            $cur = isset($all[self::CONFIG_KEY]) ? trim((string) $all[self::CONFIG_KEY]) : '';
            $legacy = isset($all[self::LEGACY_CONFIG_KEY]) ? trim((string) $all[self::LEGACY_CONFIG_KEY]) : '';
            if ($cur === '' && $legacy !== '') {
                Config::set(self::CONFIG_KEY, $legacy);
                $cur = $legacy;
            }
            if (!array_key_exists(self::CONFIG_KEY, $all) && $cur === '') {
                Config::set(self::CONFIG_KEY, '');
            }
            if (array_key_exists(self::LEGACY_CONFIG_KEY, $all)) {
                $pdo = Database::connect();
                $table = Database::table('config');
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                $stmt->execute(array(self::LEGACY_CONFIG_KEY));
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * @return string
     */
    public static function get()
    {
        try {
            return trim((string) Config::get(self::CONFIG_KEY, ''));
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @return string 64 位 hex
     */
    public static function generate()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            return hash('sha256', uniqid((string) mt_rand(), true) . microtime(true));
        }
    }

    /**
     * @param string $key
     * @return void
     */
    public static function set($key)
    {
        Config::set(self::CONFIG_KEY, trim((string) $key));
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function validate($key)
    {
        $expected = self::get();
        $key = trim((string) $key);
        if ($expected === '' || $key === '') {
            return false;
        }
        return hash_equals($expected, $key);
    }

    /**
     * 从请求提取密钥（优先级：Bearer → Header Api-Key → Query/POST → JSON body）
     *
     * @return string
     */
    public static function extractFromRequest()
    {
        $auth = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if ($auth !== '' && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
            return trim($m[1]);
        }

        $headerCandidates = array(
            'HTTP_X_API_KEY',
            'HTTP_API_KEY',
            'HTTP_APIKEY',
        );
        foreach ($headerCandidates as $h) {
            if (isset($_SERVER[$h]) && trim((string) $_SERVER[$h]) !== '') {
                return trim((string) $_SERVER[$h]);
            }
        }

        if (isset($_GET['key']) && trim((string) $_GET['key']) !== '') {
            return trim((string) $_GET['key']);
        }
        if (isset($_GET['apikey']) && trim((string) $_GET['apikey']) !== '') {
            return trim((string) $_GET['apikey']);
        }
        if (isset($_POST['key']) && trim((string) $_POST['key']) !== '') {
            return trim((string) $_POST['key']);
        }
        if (isset($_POST['apikey']) && trim((string) $_POST['apikey']) !== '') {
            return trim((string) $_POST['apikey']);
        }

        $raw = self::requestBody();
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                if (isset($json['key']) && trim((string) $json['key']) !== '') {
                    return trim((string) $json['key']);
                }
                if (isset($json['apikey']) && trim((string) $json['apikey']) !== '') {
                    return trim((string) $json['apikey']);
                }
            }
        }

        return '';
    }

    /**
     * 鉴权失败直接输出 JSON 并结束
     *
     * @return void
     */
    public static function requireAuthorized()
    {
        $ip = AuthSecurity::clientIp();
        $failBucket = 'syskey:fail:' . $ip;
        if (!RateLimitStore::allow($failBucket, 60, 30, false)) {
            self::jsonExit(429, 0, '请求过于频繁');
        }

        $key = self::extractFromRequest();
        if (!self::validate($key)) {
            RateLimitStore::recordHit($failBucket, 60);
            self::jsonExit(403, 0, '无权访问');
        }
    }

    /**
     * 业务限流（按 IP + 桶后缀）
     *
     * @param string $bucketSuffix
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @return void
     */
    public static function requireRateLimit($bucketSuffix, $windowSeconds, $maxAttempts)
    {
        $ip = AuthSecurity::clientIp();
        $bucket = 'syskey:' . $bucketSuffix . ':' . $ip;
        if (!RateLimitStore::allow($bucket, $windowSeconds, $maxAttempts, true)) {
            self::jsonExit(429, 0, '请求过于频繁');
        }
    }

    /**
     * @param int    $http
     * @param int    $code
     * @param string $msg
     * @param array  $extra
     * @return never
     */
    public static function jsonExit($http, $code, $msg, array $extra = array())
    {
        if (!headers_sent()) {
            http_response_code((int) $http);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        $payload = array_merge(array(
            'code' => (int) $code,
            'msg'  => (string) $msg,
        ), $extra);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
