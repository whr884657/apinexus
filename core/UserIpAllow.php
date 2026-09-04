<?php
/**
 * 文件：core/UserIpAllow.php
 * 作用：用户调用 IP 白名单（空=不限制；仅对「密钥必须」接口硬拦）
 */

class UserIpAllow
{
    /** 单用户最多白名单条数 */
    const MAX_COUNT = 32;

    /** 库字段最大字符（含逗号） */
    const MAX_RAW_LEN = 2000;

    /**
     * @return bool
     */
    public static function columnReady()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = class_exists('DatabaseMigrator')
                ? DatabaseMigrator::tableColumnExists('user', 'ipallow')
                : false;
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 读取原始字符串
     *
     * @param int $userId
     * @return string
     */
    public static function rawForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::columnReady()) {
            return '';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `ipallow` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $v = $stmt->fetchColumn();
            return $v === false || $v === null ? '' : trim((string) $v);
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * 解析为 IP 列表（已规范化、去重）
     *
     * @param string $raw
     * @return array<int,string>
     */
    public static function parseList($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $parts = preg_split('/[,，;\s]+/u', $raw);
        if (!is_array($parts)) {
            return array();
        }
        $out = array();
        $seen = array();
        foreach ($parts as $p) {
            $ip = self::normalizeIp($p);
            if ($ip === '' || isset($seen[$ip])) {
                continue;
            }
            $seen[$ip] = true;
            $out[] = $ip;
        }
        return $out;
    }

    /**
     * @param string $ip
     * @return string 合法则返回规范串，否则空
     */
    public static function normalizeIp($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return '';
        }
        // 去掉包裹方括号的 IPv6 写法
        if (isset($ip[0]) && $ip[0] === '[' && substr($ip, -1) === ']') {
            $ip = substr($ip, 1, -1);
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }
        // 规范为 inet 标准文本（IPv6 压缩形式一致，便于比较）
        if (function_exists('inet_pton') && function_exists('inet_ntop')) {
            $bin = @inet_pton($ip);
            if ($bin !== false) {
                $canon = @inet_ntop($bin);
                if (is_string($canon) && $canon !== '') {
                    return $canon;
                }
            }
        }
        return $ip;
    }

    /**
     * @param array<int,string> $list
     * @return string
     */
    public static function serializeList(array $list)
    {
        $clean = array();
        $seen = array();
        foreach ($list as $item) {
            $ip = self::normalizeIp($item);
            if ($ip === '' || isset($seen[$ip])) {
                continue;
            }
            $seen[$ip] = true;
            $clean[] = $ip;
            if (count($clean) >= self::MAX_COUNT) {
                break;
            }
        }
        return implode(',', $clean);
    }

    /**
     * 管理员：有白名单和/或出口代理的用户概览（不含代理密码）
     *
     * @param int $limit
     * @return array{ok:bool,msg:string,list?:array,truncated?:bool}
     */
    public static function adminOverview($limit = 500)
    {
        $limit = max(1, min(1000, (int) $limit));
        if (!self::columnReady()) {
            return array('ok' => false, 'msg' => 'IP 白名单尚未就绪');
        }
        $proxyReady = class_exists('UserIpProxy') && UserIpProxy::tableReady();
        try {
            $pdo = Database::connect();
            $userTable = Database::table('user');
            $proxyCountExpr = '0';
            if ($proxyReady) {
                $proxyTable = Database::table('ipproxy');
                $proxyCountExpr = '(SELECT COUNT(*) FROM `' . $proxyTable . '` p WHERE p.`userid` = u.`id`)';
            }
            $sql = 'SELECT u.`id`, u.`username`, u.`email`, u.`avatar`, u.`ipallow`'
                . ($proxyReady && class_exists('UserIpProxy') && UserIpProxy::strategyColumnReady()
                    ? ', u.`proxystrategy`'
                    : ', 0 AS `proxystrategy`')
                . ', ' . $proxyCountExpr . ' AS `proxycount`'
                . ' FROM `' . $userTable . '` u'
                . ' WHERE ('
                . ' (u.`ipallow` IS NOT NULL AND TRIM(u.`ipallow`) <> \'\')'
                . ($proxyReady ? ' OR ' . $proxyCountExpr . ' > 0' : '')
                . ' )'
                . ' ORDER BY u.`id` DESC'
                . ' LIMIT ' . ((int) $limit + 1);
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = array();
            }
            $truncated = count($rows) > $limit;
            if ($truncated) {
                $rows = array_slice($rows, 0, $limit);
            }
            $list = array();
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $uid = (int) (isset($row['id']) ? $row['id'] : 0);
                $raw = isset($row['ipallow']) ? trim((string) $row['ipallow']) : '';
                $allowList = self::parseList($raw);
                $proxyCount = (int) (isset($row['proxycount']) ? $row['proxycount'] : 0);
                $strategy = (int) (isset($row['proxystrategy']) ? $row['proxystrategy'] : 0);
                $username = isset($row['username']) ? trim((string) $row['username']) : '';
                if ($username === '') {
                    $username = '用户#' . $uid;
                }
                $list[] = array(
                    'userid'         => $uid,
                    'username'       => $username,
                    'email'          => isset($row['email']) ? trim((string) $row['email']) : '',
                    'avatar'         => class_exists('UserAvatar')
                        ? UserAvatar::resolve($row)
                        : '',
                    'allow_count'    => count($allowList),
                    'allow_preview'  => count($allowList) > 0
                        ? implode(', ', array_slice($allowList, 0, 3))
                            . (count($allowList) > 3 ? '…' : '')
                        : '—',
                    'proxy_count'    => $proxyCount,
                    'strategy'       => $strategy,
                    'strategy_label' => self::proxyStrategyLabel($strategy),
                );
            }
            return array(
                'ok'         => true,
                'msg'        => 'ok',
                'list'       => $list,
                'truncated'  => $truncated,
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '读取失败，请稍后重试');
        }
    }

    /**
     * 代理策略文案（委托 UserIpProxy，避免静态分析误报未定义方法）
     *
     * @param int $strategy
     * @return string
     */
    private static function proxyStrategyLabel($strategy)
    {
        if (class_exists('UserIpProxy') && is_callable(array('UserIpProxy', 'strategyLabel'))) {
            return (string) call_user_func(array('UserIpProxy', 'strategyLabel'), $strategy);
        }
        $n = (int) $strategy;
        if ($n === 1) {
            return '随机';
        }
        if ($n === 2) {
            return '优先首条';
        }
        return '轮询';
    }

    /**
     * 密钥必须接口的守卫：用户白名单是否放行当前客户端 IP
     *
     * 注意：调用方应仅在 needkey=必须（含收费强制必须）时调用本方法。
     * 无需/可选接口允许游客访问，白名单拦截无意义，勿在此场景硬拦。
     *
     * @param int $userId
     * @return true|array{errcode:int,msg:string}
     */
    public static function checkUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::columnReady()) {
            return true;
        }
        $list = self::parseList(self::rawForUser($userId));
        if ($list === array()) {
            return true;
        }
        $ip = class_exists('AuthSecurity') ? AuthSecurity::clientIp() : '0.0.0.0';
        $ip = self::normalizeIp($ip);
        if ($ip === '' || !self::ipInList($ip, $list)) {
            return array(
                'errcode' => ApiError::IP_DENY,
                'msg'     => '当前IP不在白名单内',
            );
        }
        return true;
    }

    /**
     * @param string            $ip
     * @param array<int,string> $list
     * @return bool
     */
    public static function ipInList($ip, array $list)
    {
        $ip = self::normalizeIp($ip);
        if ($ip === '') {
            return false;
        }
        $bin = (function_exists('inet_pton')) ? @inet_pton($ip) : false;
        foreach ($list as $allowed) {
            $allowed = self::normalizeIp($allowed);
            if ($allowed === '') {
                continue;
            }
            if ($bin !== false && function_exists('inet_pton')) {
                $ab = @inet_pton($allowed);
                if ($ab !== false && $bin === $ab) {
                    return true;
                }
            }
            if (strcasecmp($ip, $allowed) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 保存完整列表（覆盖写）
     *
     * @param int   $userId
     * @param array $list
     * @return true|string
     */
    public static function saveList($userId, array $list)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '用户无效';
        }
        if (!self::columnReady()) {
            return 'IP 配置尚未就绪，请联系管理员完成系统升级';
        }
        $raw = self::serializeList($list);
        if (strlen($raw) > self::MAX_RAW_LEN) {
            return '白名单过长，请减少 IP 数量';
        }
        try {
            $pdo = Database::connect();
            $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `ipallow` = ? WHERE `id` = ?'
            )->execute(array($raw, $userId));
            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * 添加一条
     *
     * @param int    $userId
     * @param string $ip
     * @return array{ok:bool,msg:string,list?:array}
     */
    public static function addIp($userId, $ip)
    {
        $norm = self::normalizeIp($ip);
        if ($norm === '') {
            return array('ok' => false, 'msg' => '请输入有效的 IP 地址');
        }
        $list = self::parseList(self::rawForUser($userId));
        if (self::ipInList($norm, $list)) {
            return array('ok' => false, 'msg' => '该 IP 已在白名单中');
        }
        if (count($list) >= self::MAX_COUNT) {
            return array('ok' => false, 'msg' => '白名单最多 ' . self::MAX_COUNT . ' 条');
        }
        $list[] = $norm;
        $saved = self::saveList($userId, $list);
        if ($saved !== true) {
            return array('ok' => false, 'msg' => (string) $saved);
        }
        return array('ok' => true, 'msg' => '已添加', 'list' => self::parseList(self::rawForUser($userId)));
    }

    /**
     * 删除一条
     *
     * @param int    $userId
     * @param string $ip
     * @return array{ok:bool,msg:string,list?:array}
     */
    public static function removeIp($userId, $ip)
    {
        $norm = self::normalizeIp($ip);
        if ($norm === '') {
            return array('ok' => false, 'msg' => 'IP 无效');
        }
        $list = self::parseList(self::rawForUser($userId));
        $next = array();
        $found = false;
        foreach ($list as $item) {
            if (strcasecmp($item, $norm) === 0) {
                $found = true;
                continue;
            }
            $next[] = $item;
        }
        if (!$found) {
            return array('ok' => false, 'msg' => '白名单中无此 IP');
        }
        $saved = self::saveList($userId, $next);
        if ($saved !== true) {
            return array('ok' => false, 'msg' => (string) $saved);
        }
        return array('ok' => true, 'msg' => '已移除', 'list' => $next);
    }
}
