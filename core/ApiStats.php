<?php
/**
 * 文件：core/ApiStats.php
 * 作用：本地/代理接口调用统计（次数 + 调用日志）
 *
 * 本地注入（最短）：
 *   require_once dirname(__DIR__, N) . '/core/bootstrap.php';
 *   ApiStats::hit();           // 按脚本路径自动匹配 endpoint
 *   ApiStats::hit(14);         // 或显式传接口 ID
 *
 * 代理：ApiProxy 网关内自动调用，勿在上游文件注入。
 *
 * 密钥：识别 key / api_key / apikey / X-API-Key；校验 apikey 表；归属 userid；成功调用累加密钥 calls。
 */

class ApiStats
{
    /** @var array 本请求已记账的接口 ID，防重复 */
    private static $done = array();

    /**
     * 本请求解析出的密钥上下文
     * @var array|null {raw,keyid,userid,valid}
     */
    private static $keyCtx = null;

    /**
     * 本地接口入口：守卫（含密钥）+ 记账
     *
     * @param int|null $apiId 接口主键；null/0 则按当前脚本路径匹配 endpoint
     * @return void
     */
    public static function hit($apiId = null)
    {
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
            return;
        }

        try {
            if (!InstallChecker::isInstalled() || !ApiManager::tableReady()) {
                return;
            }

            $row = self::resolveApiRow($apiId);
            if (!$row) {
                return;
            }

            $id = (int) $row['id'];
            if (isset(self::$done[$id])) {
                return;
            }

            $gate = self::guardAccess($row);
            if ($gate !== true) {
                self::write($row, false, (int) $gate['http']);
                self::$done[$id] = true;
                self::jsonExit($gate['http'], $gate['msg']);
            }

            self::write($row, true, 200);
            self::$done[$id] = true;
        } catch (Exception $e) {
            // 统计失败不影响业务
        }
    }

    /**
     * 代理网关记账（上游跳转前调用）
     *
     * @param array $row    接口行
     * @param bool  $ok     是否成功放行
     * @param int   $http   HTTP 状态码
     * @return void
     */
    public static function hitProxy(array $row, $ok = true, $http = 302)
    {
        try {
            if (!InstallChecker::isInstalled() || !ApiManager::tableReady()) {
                return;
            }
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0 || isset(self::$done[$id])) {
                return;
            }
            self::write($row, (bool) $ok, (int) $http);
            self::$done[$id] = true;
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * 代理/本地共用：状态 + 审核 + 密钥守卫
     *
     * @param array $row
     * @return true|array{http:int,msg:string}
     */
    public static function guardAccess(array $row)
    {
        return self::lightGate($row);
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $pdo = Database::connect();
            $t = Database::table('apilog');
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
            $ready = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * @param int|null $apiId
     * @return array|null
     */
    private static function resolveApiRow($apiId)
    {
        $apiId = (int) $apiId;
        if ($apiId > 0) {
            return ApiManager::findById($apiId);
        }

        $path = self::currentScriptPath();
        if ($path === '') {
            return null;
        }

        $candidates = array($path, ltrim($path, '/'));
        if (substr($path, -4) === '.php') {
            $candidates[] = substr($path, 0, -4);
            $candidates[] = ltrim(substr($path, 0, -4), '/');
        }

        try {
            $pdo = Database::connect();
            $table = ApiManager::table();
            foreach ($candidates as $ep) {
                $stmt = $pdo->prepare(
                    'SELECT * FROM `' . $table . '` WHERE `endpoint` = ? AND `apitype` = ? LIMIT 1'
                );
                $stmt->execute(array($ep, ApiManager::APITYPE_LOCAL));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $row;
                }
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * 当前业务脚本相对站点根的路径，如 /api/yiyan/v1.php
     *
     * @return string
     */
    private static function currentScriptPath()
    {
        $file = isset($_SERVER['SCRIPT_FILENAME']) ? (string) $_SERVER['SCRIPT_FILENAME'] : '';
        if ($file === '' || !defined('VS_ROOT')) {
            return '';
        }
        $root = str_replace('\\', '/', realpath(VS_ROOT));
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        if ($root === '' || $file === '' || strpos($file, $root) !== 0) {
            return '';
        }
        $rel = substr($file, strlen($root));
        $rel = '/' . ltrim(str_replace('\\', '/', $rel), '/');
        return $rel;
    }

    /**
     * 轻量可调用检查（状态 + 审核 + 密钥）
     *
     * @param array $row
     * @return true|array{http:int,msg:string}
     */
    private static function lightGate(array $row)
    {
        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : 0);
        if ($status === ApiManager::STATUS_DISABLED) {
            return array('http' => 403, 'msg' => '该接口已经被禁用');
        }
        if ($status === ApiManager::STATUS_MAINTENANCE) {
            return array('http' => 503, 'msg' => '该接口维护中');
        }
        if (ApiManager::hasAuditColumn()) {
            $audit = ApiManager::normalizeAuditStatus(isset($row['audit']) ? $row['audit'] : 1);
            if ($audit !== ApiManager::AUDIT_APPROVED) {
                return array('http' => 403, 'msg' => '该接口不可用');
            }
        }
        return self::evaluateKey($row);
    }

    /**
     * 按接口 needkey 识别并校验请求中的密钥
     *
     * @param array $row
     * @return true|array{http:int,msg:string}
     */
    private static function evaluateKey(array $row)
    {
        $need = ApiManager::normalizeRequireKey(isset($row['needkey']) ? $row['needkey'] : ApiManager::KEY_NONE);
        $raw = self::readKey();
        $provided = ($raw !== '');

        self::$keyCtx = array(
            'raw'    => $raw,
            'keyid'  => 0,
            'userid' => 0,
            'valid'  => false,
        );

        if (!$provided) {
            if ($need === ApiManager::KEY_REQUIRED) {
                return array('http' => 401, 'msg' => '请提供调用密钥');
            }
            return true;
        }

        if (!ApiKeyManager::tableReady()) {
            return array('http' => 503, 'msg' => '密钥校验暂不可用');
        }

        $keyRow = ApiKeyManager::findBySecret($raw);
        if (!$keyRow) {
            return array('http' => 401, 'msg' => '密钥错误');
        }
        if ((int) $keyRow['status'] !== ApiKeyManager::STATUS_ENABLED) {
            return array('http' => 403, 'msg' => '密钥已禁用');
        }

        self::$keyCtx = array(
            'raw'    => $raw,
            'keyid'  => (int) $keyRow['id'],
            'userid' => (int) $keyRow['userid'],
            'valid'  => true,
        );
        return true;
    }

    /**
     * @param array $row
     * @param bool  $ok
     * @param int   $http
     * @return void
     */
    private static function write(array $row, $ok, $http)
    {
        $id = (int) $row['id'];
        if ($id <= 0) {
            return;
        }

        ApiManager::incrementCallCount($id, 1);

        $ctx = self::requestContext();
        if ($ok && !empty(self::$keyCtx['valid']) && !empty(self::$keyCtx['keyid'])) {
            ApiKeyManager::incrementCalls((int) self::$keyCtx['keyid']);
        }

        if (!self::tableReady()) {
            return;
        }

        $apitype = ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : 0);
        $name = isset($row['name']) ? (string) $row['name'] : '';

        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO `' . Database::table('apilog') . '` (
                `apiid`, `apiname`, `apitype`, `userid`, `apikey`,
                `method`, `ip`, `iploc`, `host`, `path`, `url`,
                `referer`, `origin`, `domain`, `ua`,
                `ok`, `httpcode`, `charged`, `cost`, `createtime`
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 0, 0, NOW()
            )'
        );
        $stmt->execute(array(
            $id,
            mb_substr($name, 0, 100, 'UTF-8'),
            $apitype,
            $ctx['userid'],
            $ctx['apikey'],
            $ctx['method'],
            $ctx['ip'],
            $ctx['iploc'],
            $ctx['host'],
            $ctx['path'],
            $ctx['url'],
            $ctx['referer'],
            $ctx['origin'],
            $ctx['domain'],
            $ctx['ua'],
            $ok ? 1 : 0,
            (int) $http,
        ));
    }

    /**
     * @return array
     */
    private static function requestContext()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        $ip = AuthSecurity::clientIp();
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        $scheme = $https ? 'https' : 'http';
        $url = $host !== '' ? ($scheme . '://' . $host . $uri) : $uri;

        $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

        $domain = self::extractDomain($referer);
        if ($domain === '') {
            $domain = self::extractDomain($origin);
        }

        $userid = 0;
        $apikey = '';
        if (is_array(self::$keyCtx)) {
            $apikey = isset(self::$keyCtx['raw']) ? (string) self::$keyCtx['raw'] : '';
            if (!empty(self::$keyCtx['valid']) && !empty(self::$keyCtx['userid'])) {
                $userid = (int) self::$keyCtx['userid'];
            }
        }
        if ($apikey === '') {
            $apikey = self::readKey();
        }
        if ($userid <= 0 && class_exists('UserAuth') && UserAuth::check()) {
            $userid = (int) UserAuth::id();
        }

        return array(
            'userid'  => $userid,
            'apikey'  => mb_substr($apikey, 0, 128, 'UTF-8'),
            'method'  => mb_substr($method, 0, 16, 'UTF-8'),
            'ip'      => mb_substr($ip, 0, 45, 'UTF-8'),
            'iploc'   => '',
            'host'    => mb_substr($host, 0, 255, 'UTF-8'),
            'path'    => mb_substr($path, 0, 500, 'UTF-8'),
            'url'     => mb_substr($url, 0, 1000, 'UTF-8'),
            'referer' => mb_substr($referer, 0, 1000, 'UTF-8'),
            'origin'  => mb_substr($origin, 0, 500, 'UTF-8'),
            'domain'  => mb_substr($domain, 0, 255, 'UTF-8'),
            'ua'      => mb_substr($ua, 0, 500, 'UTF-8'),
        );
    }

    /**
     * 读取请求中的密钥（Query / POST / Header）
     *
     * @return string
     */
    private static function readKey()
    {
        foreach (array('key', 'api_key', 'apikey') as $k) {
            if (isset($_GET[$k]) && (string) $_GET[$k] !== '') {
                return trim((string) $_GET[$k]);
            }
            if (isset($_POST[$k]) && (string) $_POST[$k] !== '') {
                return trim((string) $_POST[$k]);
            }
        }
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim((string) $_SERVER['HTTP_X_API_KEY']);
        }
        return '';
    }

    /**
     * @param string $url
     * @return string
     */
    private static function extractDomain($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * @param int    $http
     * @param string $msg
     * @return void
     */
    private static function jsonExit($http, $msg)
    {
        http_response_code((int) $http);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'code' => 0,
            'msg'  => (string) $msg,
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
