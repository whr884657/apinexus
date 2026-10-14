<?php
/**
 * 文件：core/ApiKeyManager.php
 * 作用：用户 API 调用密钥 CRUD（每用户上限由系统设置 apikey_max 配置，默认 3、最大 20）；
 *       含 pointsspent 累计消耗、配额 quota/quotaused/quotafallback、到期 expiretime、
 *       接口范围 scopejson（c/a JSON）
 */

class ApiKeyManager
{
    /** 默认每用户密钥上限（与配置缺省一致） */
    const DEFAULT_MAX_PER_USER = 3;

    /** 管理员可配置的绝对上限 */
    const ABSOLUTE_MAX_PER_USER = 20;

    /** 管理员可配置的下限 */
    const MIN_PER_USER = 1;

    /** 范围：分类 ID 上限 */
    const SCOPE_MAX_C = 64;
    /** 范围：接口 ID 上限 */
    const SCOPE_MAX_A = 512;
    /** 范围：整串字节上限（落库 JSON；满勾写 NULL 不走此帽） */
    const SCOPE_MAX_BYTES = 8192;
    /** 写侧 POST 解析帽（防超大 JSON；满勾对照须先解析再归一 NULL） */
    const SCOPE_POST_MAX_BYTES = 524288;
    /** 写侧解析分类数帽（满勾可超过 SCOPE_MAX_C，归一后再丢弃清单） */
    const SCOPE_PARSE_MAX_C = 4096;
    /** 写侧解析接口数帽 */
    const SCOPE_PARSE_MAX_A = 16384;
    /** 列探测进程缓存 TTL（秒）：升级加列后其它 FPM worker 可在窗口内重探，禁止「无列」永久缓存 */
    const COLUMN_PROBE_TTL = 60;
    /** 坏 scopejson 同 key+原因 error_log 最小间隔（秒） */
    const SCOPE_LOG_INTERVAL = 60;

    /**
     * @deprecated 请用 maxPerUser()；保留常量以免旧代码硬引用炸裂，数值等于默认 3
     */
    const MAX_PER_USER = 3;

    /** 状态：禁用 */
    const STATUS_DISABLED = 0;
    /** 状态：启用 */
    const STATUS_ENABLED = 1;

    /**
     * 当前站点「每账号可创建密钥数」上限（读 config.apikey_max，钳制 1～20）
     *
     * 语义：只限制**新建**；已有密钥即使超过上限仍可继续使用，删除后不可再超限新建。
     *
     * @return int
     */
    public static function maxPerUser()
    {
        $raw = '3';
        if (class_exists('Config', false)) {
            $raw = (string) Config::get('apikey_max', (string) self::DEFAULT_MAX_PER_USER);
        }
        return self::normalizeMaxPerUser($raw);
    }

    /**
     * 规范化配置值：1～20，非法回落默认 3
     *
     * @param mixed $value
     * @return int
     */
    public static function normalizeMaxPerUser($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null || !is_numeric($value)) {
            return self::DEFAULT_MAX_PER_USER;
        }
        $n = (int) $value;
        if ($n < self::MIN_PER_USER) {
            return self::MIN_PER_USER;
        }
        if ($n > self::ABSOLUTE_MAX_PER_USER) {
            return self::ABSOLUTE_MAX_PER_USER;
        }
        return $n;
    }

    /**
     * 该用户是否还能再建密钥（已有数量已达/超过上限则 false；已有密钥不因此失效）
     *
     * @param int $userId
     * @return bool
     */
    public static function canCreateMore($userId)
    {
        return self::countByUser($userId) < self::maxPerUser();
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        try {
            return DatabaseMigrator::tableExists('apikey');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int $status
     * @return string
     */
    public static function statusLabel($status)
    {
        return ((int) $status === self::STATUS_ENABLED) ? '启用' : '禁用';
    }

    /**
     * 生成 sk- + 32 位随机十六进制字符（小写前缀）
     *
     * @return string
     */
    public static function generateSecret()
    {
        return 'sk-' . bin2hex(random_bytes(16));
    }

    /**
     * @param int $userId
     * @return int
     */
    public static function countByUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `userid` = ?');
            $stmt->execute(array($userId));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function listByUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare(
                'SELECT ' . self::selectColumnSql('') . '
                 FROM `' . $table . '`
                 WHERE `userid` = ?
                 ORDER BY `id` DESC'
            );
            $stmt->execute(array($userId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 管理员：全部令牌（含用户名）
     *
     * @return array
     */
    public static function listAll()
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $tokenTable = Database::table('apikey');
            $userTable = Database::table('user');
            $cols = self::selectColumnSql('t');
            $sql = 'SELECT ' . $cols . ',
                           u.`username` AS `username`,
                           u.`email` AS `email`,
                           u.`avatar` AS `avatar`
                    FROM `' . $tokenTable . '` t
                    LEFT JOIN `' . $userTable . '` u ON u.`id` = t.`userid`
                    ORDER BY t.`id` DESC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 后台真分页（独立 COUNT，页长 1～50）
     *
     * @param array $opts q?, status?, page, pagesize
     * @return array{list:array,total:int,page:int,pagesize:int}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        if (function_exists('mb_substr') && mb_strlen($q, 'UTF-8') > 64) {
            $q = mb_substr($q, 0, 64, 'UTF-8');
        } elseif (strlen($q) > 64) {
            $q = substr($q, 0, 64);
        }
        $status = array_key_exists('status', $opts) && $opts['status'] !== '' && $opts['status'] !== null
            ? (int) $opts['status']
            : null;
        $empty = array('list' => array(), 'total' => 0, 'page' => $page, 'pagesize' => $pagesize);
        if (!self::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $tokenTable = Database::table('apikey');
            $userTable = Database::table('user');
            $where = array('1=1');
            $bind = array();
            if ($status !== null) {
                $where[] = 't.`status` = ?';
                $bind[] = $status;
            }
            if ($q !== '') {
                $like = function_exists('vs_sql_like_contains')
                    ? vs_sql_like_contains($q)
                    : ('%' . addcslashes($q, "\\%_") . '%');
                $where[] = '(t.`remark` LIKE ? ESCAPE \'\\\\\' OR t.`secret` LIKE ? ESCAPE \'\\\\\' OR u.`username` LIKE ? ESCAPE \'\\\\\' OR u.`email` LIKE ? ESCAPE \'\\\\\')';
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
            }
            $whereSql = implode(' AND ', $where);
            $cnt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . $tokenTable . '` t
                 LEFT JOIN `' . $userTable . '` u ON u.`id` = t.`userid`
                 WHERE ' . $whereSql
            );
            $cnt->execute($bind);
            $total = (int) $cnt->fetchColumn();
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            if ($total === 0) {
                return array('list' => array(), 'total' => 0, 'page' => 1, 'pagesize' => $pagesize);
            }
            $offset = (int) (($page - 1) * $pagesize);
            $cols = self::selectColumnSql('t');
            $sql = 'SELECT ' . $cols . ',
                           u.`username` AS `username`,
                           u.`email` AS `email`,
                           u.`avatar` AS `avatar`
                    FROM `' . $tokenTable . '` t
                    LEFT JOIN `' . $userTable . '` u ON u.`id` = t.`userid`
                    WHERE ' . $whereSql . '
                    ORDER BY t.`id` DESC
                    LIMIT ' . $pagesize . ' OFFSET ' . $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = is_array($rows) ? $rows : array();
            return array(
                'list'     => $list,
                'total'    => $total,
                'page'     => $page,
                'pagesize' => $pagesize,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function findById($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare(
                'SELECT ' . self::selectColumnSql('') . ' FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $secret
     * @return array|null
     */
    public static function findBySecret($secret)
    {
        $secret = trim((string) $secret);
        if ($secret === '' || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare(
                'SELECT ' . self::selectColumnSql('') . ' FROM `' . $table . '` WHERE `secret` = ? LIMIT 1'
            );
            $stmt->execute(array($secret));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param array|null $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row) || !isset($row['id'])) {
            return null;
        }
        $status = ((int) $row['status'] === self::STATUS_ENABLED)
            ? self::STATUS_ENABLED
            : self::STATUS_DISABLED;
        $quota = isset($row['quota']) ? (float) $row['quota'] : 0.0;
        $quotaused = isset($row['quotaused']) ? (float) $row['quotaused'] : 0.0;
        $expireRaw = isset($row['expiretime']) ? $row['expiretime'] : null;
        if ($expireRaw === '' || $expireRaw === null) {
            $expiretime = null;
        } else {
            $expiretime = (string) $expireRaw;
        }
        $scopeRaw = array_key_exists('scopejson', $row) ? $row['scopejson'] : null;
        $parsed = self::parseScope($scopeRaw, isset($row['id']) ? (int) $row['id'] : 0);
        $scopeOpen = !empty($parsed['open']) && empty($parsed['bad']);
        $scopeC = isset($parsed['c']) && is_array($parsed['c']) ? $parsed['c'] : array();
        $scopeA = isset($parsed['a']) && is_array($parsed['a']) ? $parsed['a'] : array();
        if (!empty($parsed['bad'])) {
            $scopeOpen = false;
            $scopeC = array();
            $scopeA = array();
        }
        return array(
            'id'            => (int) $row['id'],
            'userid'        => (int) $row['userid'],
            'remark'        => (string) $row['remark'],
            'secret'        => (string) $row['secret'],
            'status'        => $status,
            'status_label'  => self::statusLabel($status),
            'calls'         => isset($row['calls']) ? (int) $row['calls'] : 0,
            'pointsspent'   => isset($row['pointsspent']) ? (float) $row['pointsspent'] : 0.0,
            'quota'         => $quota,
            'quotaused'     => $quotaused,
            'quotafallback' => isset($row['quotafallback']) ? (((int) $row['quotafallback'] === 1) ? 1 : 0) : 0,
            'expiretime'    => $expiretime,
            'quotaleft'     => ($quota > 0) ? max(0.0, round($quota - $quotaused, 4)) : null,
            'expire_label'  => self::expireLabel($expiretime),
            'scope_open'    => $scopeOpen,
            'scope_c'       => $scopeC,
            'scope_a'       => $scopeA,
            'scope_label'   => self::scopeLabelFromParsed($parsed),
            'createtime'    => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'username'      => isset($row['username']) ? (string) $row['username'] : '',
        );
    }

    /**
     * 到期文案：null/空=永不过期；已到期=已过期；否则原时间
     *
     * @param string|null $expiretime
     * @return string
     */
    public static function expireLabel($expiretime)
    {
        if ($expiretime === null || $expiretime === '') {
            return '永不过期';
        }
        $ts = strtotime((string) $expiretime);
        if ($ts === false) {
            return (string) $expiretime;
        }
        if ($ts <= time()) {
            return '已过期';
        }
        return (string) $expiretime;
    }

    /**
     * 是否已过期（expiretime 为空则永不过期）
     *
     * @param array|null $row
     * @return bool
     */
    public static function isExpired($row)
    {
        if (!is_array($row)) {
            return false;
        }
        if (!isset($row['expiretime']) || $row['expiretime'] === null || $row['expiretime'] === '') {
            return false;
        }
        $ts = strtotime((string) $row['expiretime']);
        if ($ts === false) {
            return false;
        }
        return $ts <= time();
    }

    /**
     * @param int    $userId
     * @param string $remark
     * @param array  $extra 可选 quota / quotafallback / expiretime / scope_mode / scopejson / scope_visible
     * @return array|string 成功返回 formatRow，失败返回错误文案
     */
    public static function create($userId, $remark, $extra = array())
    {
        $userId = (int) $userId;
        $remark = self::normalizeRemark($remark);
        if ($userId <= 0) {
            return '无效用户';
        }
        if ($remark === '') {
            return '请填写令牌名称';
        }
        if (!self::tableReady()) {
            return '令牌功能尚未就绪，请联系管理员完成系统升级';
        }
        if (self::countByUser($userId) >= self::maxPerUser()) {
            return '每个账号最多 ' . self::maxPerUser() . ' 个令牌，请先删除不用的令牌';
        }

        $secret = self::makeUniqueSecret();
        if ($secret === '') {
            return '令牌生成失败，请稍后重试';
        }

        if (!is_array($extra)) {
            $extra = array();
        }

        // 范围：独立于配额列探测（有范围列、无配额列的站点也须能写入）
        $scopeErr = '';
        $scopeMode = array_key_exists('scope_mode', $extra) ? $extra['scope_mode'] : null;
        $scopeRaw = array_key_exists('scopejson', $extra) ? $extra['scopejson'] : null;
        $visibleOpt = isset($extra['scope_visible']) ? $extra['scope_visible'] : null;
        // 新建无「本钥已有分类」可保留
        $scopeNorm = self::normalizeScopeInput($scopeMode, $scopeRaw, $visibleOpt, $scopeErr, array());
        if ($scopeNorm === false) {
            return ($scopeErr !== '') ? $scopeErr : '接口范围数据无效，请重新选择';
        }
        if ($scopeNorm !== '__keep__' && !self::hasScopeColumn()) {
            return '接口范围功能尚未就绪，请先完成系统升级';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '`
                 (`userid`, `remark`, `secret`, `status`, `calls`, `createtime`)
                 VALUES (?, ?, ?, ?, 0, NOW())'
            );
            $stmt->execute(array($userId, $remark, $secret, self::STATUS_ENABLED));
            $id = (int) $pdo->lastInsertId();

            $deleteOrphan = function () use ($pdo, $table, $id, $userId) {
                try {
                    $del = $pdo->prepare(
                        'DELETE FROM `' . $table . '` WHERE `id` = ? AND `userid` = ? LIMIT 1'
                    );
                    $del->execute(array($id, $userId));
                } catch (Exception $eDel) {
                    // 删除失败仍返回原错误，便于排查
                }
            };

            if (self::hasQuotaColumns() && $extra !== array()) {
                $settings = array('remark' => $remark);
                if (array_key_exists('quota', $extra)) {
                    $settings['quota'] = $extra['quota'];
                }
                if (array_key_exists('quotafallback', $extra)) {
                    $settings['quotafallback'] = $extra['quotafallback'];
                }
                if (array_key_exists('expiretime', $extra)) {
                    $settings['expiretime'] = $extra['expiretime'];
                }
                if (count($settings) > 1) {
                    $saved = self::saveSettings($id, $userId, $settings);
                    if ($saved !== true) {
                        $deleteOrphan();
                        return is_string($saved) ? $saved : '创建失败';
                    }
                }
            }

            if ($scopeNorm !== '__keep__') {
                try {
                    $upd = $pdo->prepare(
                        'UPDATE `' . $table . '` SET `scopejson` = ? WHERE `id` = ? AND `userid` = ? LIMIT 1'
                    );
                    self::bindScopeParam($upd, 1, $scopeNorm);
                    $upd->bindValue(2, $id, PDO::PARAM_INT);
                    $upd->bindValue(3, $userId, PDO::PARAM_INT);
                    $upd->execute();
                } catch (Exception $eScope) {
                    $deleteOrphan();
                    return '创建失败，请稍后重试';
                }
            }

            $row = self::findById($id);
            $formatted = self::formatRow($row);
            return $formatted ? $formatted : '创建失败';
        } catch (Exception $e) {
            return '创建失败，请稍后重试';
        }
    }

    /**
     * 保存令牌设置（名称 / 配额 / 回退 / 到期 / 接口范围）
     *
     * @param int   $id
     * @param int   $userId 0=管理员不校验归属
     * @param array $input  remark(必填)；quota / quotafallback / expiretime / scope_mode / scopejson / scope_visible 可选
     * @return true|string
     */
    public static function saveSettings($id, $userId, array $input)
    {
        $id = (int) $id;
        $userId = (int) $userId;
        $remark = self::normalizeRemark(isset($input['remark']) ? $input['remark'] : '');
        if ($id <= 0) {
            return '无效令牌';
        }
        if ($remark === '') {
            return '请填写令牌名称';
        }
        $row = self::findById($id);
        if (!$row) {
            return '令牌不存在';
        }
        if ($userId > 0 && (int) $row['userid'] !== $userId) {
            return '无权操作该令牌';
        }

        $scopeErr = '';
        $scopeMode = array_key_exists('scope_mode', $input) ? $input['scope_mode'] : null;
        $scopeRaw = array_key_exists('scopejson', $input) ? $input['scopejson'] : null;
        $visibleOpt = isset($input['scope_visible']) ? $input['scope_visible'] : null;
        // 本钥已有 c：停用分类可保留；未勾过的停用分类不可新写
        $keepC = array();
        if (self::hasScopeColumn() && array_key_exists('scopejson', $row)) {
            $prev = self::parseScope($row['scopejson'], $id);
            if (empty($prev['bad']) && empty($prev['open']) && isset($prev['c']) && is_array($prev['c'])) {
                $keepC = $prev['c'];
            }
        }
        $scopeNorm = self::normalizeScopeInput($scopeMode, $scopeRaw, $visibleOpt, $scopeErr, $keepC);
        if ($scopeNorm === false) {
            return ($scopeErr !== '') ? $scopeErr : '接口范围数据无效，请重新选择';
        }
        $writeScope = ($scopeNorm !== '__keep__');
        if ($writeScope && !self::hasScopeColumn()) {
            return '接口范围功能尚未就绪，请先完成系统升级';
        }

        if (!self::hasQuotaColumns()) {
            $wantsQuota = array_key_exists('quota', $input)
                || array_key_exists('quotafallback', $input)
                || array_key_exists('expiretime', $input);
            if ($wantsQuota) {
                return '令牌配额功能尚未就绪，请先完成数据库结构更新';
            }
            try {
                $pdo = Database::connect();
                $table = Database::table('apikey');
                if ($writeScope) {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . $table . '` SET `remark` = ?, `scopejson` = ? WHERE `id` = ? LIMIT 1'
                    );
                    $stmt->bindValue(1, $remark, PDO::PARAM_STR);
                    self::bindScopeParam($stmt, 2, $scopeNorm);
                    $stmt->bindValue(3, $id, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `remark` = ? WHERE `id` = ? LIMIT 1');
                    $stmt->execute(array($remark, $id));
                }
                return true;
            } catch (Exception $e) {
                return '保存失败，请稍后重试';
            }
        }

        $oldQuota = isset($row['quota']) ? (float) $row['quota'] : 0.0;
        $oldUsed = isset($row['quotaused']) ? (float) $row['quotaused'] : 0.0;

        if (array_key_exists('quota', $input)) {
            if ($input['quota'] === '' || $input['quota'] === null || !is_numeric($input['quota'])) {
                return '分配积分无效';
            }
            $quota = round((float) $input['quota'], 4);
        } else {
            $quota = $oldQuota;
        }
        if ($quota < 0) {
            return '分配积分不能为负数';
        }
        if ($quota > 999999999) {
            return '分配积分过大';
        }

        if (array_key_exists('quotafallback', $input)) {
            $quotafallback = ((int) $input['quotafallback'] === 1) ? 1 : 0;
        } else {
            $quotafallback = isset($row['quotafallback']) ? (((int) $row['quotafallback'] === 1) ? 1 : 0) : 0;
        }

        if (array_key_exists('expiretime', $input)) {
            $rawExpire = $input['expiretime'];
            if ($rawExpire === null || $rawExpire === '') {
                $expiretime = null;
            } else {
                $expiretime = self::normalizeExpiretime($rawExpire);
                if ($expiretime === false) {
                    return '到期时间格式无效，请使用 Y-m-d H:i:s 或 Y-m-d H:i';
                }
            }
        } else {
            $expireRaw = isset($row['expiretime']) ? $row['expiretime'] : null;
            $expiretime = ($expireRaw === null || $expireRaw === '') ? null : (string) $expireRaw;
        }

        $resetUsed = ($quota == 0.0);
        if ($resetUsed) {
            $quotafallback = 0;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            if ($resetUsed) {
                if ($writeScope) {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . $table . '`
                         SET `remark` = ?, `quota` = ?, `quotaused` = 0, `quotafallback` = ?, `expiretime` = ?, `scopejson` = ?
                         WHERE `id` = ? LIMIT 1'
                    );
                    $stmt->bindValue(1, $remark, PDO::PARAM_STR);
                    $stmt->bindValue(2, $quota);
                    $stmt->bindValue(3, $quotafallback, PDO::PARAM_INT);
                    self::bindNullableString($stmt, 4, $expiretime);
                    self::bindScopeParam($stmt, 5, $scopeNorm);
                    $stmt->bindValue(6, $id, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . $table . '`
                         SET `remark` = ?, `quota` = ?, `quotaused` = 0, `quotafallback` = ?, `expiretime` = ?
                         WHERE `id` = ? LIMIT 1'
                    );
                    $stmt->execute(array($remark, $quota, $quotafallback, $expiretime, $id));
                }
            } else {
                if ($writeScope) {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . $table . '`
                         SET `remark` = ?, `quota` = ?, `quotafallback` = ?, `expiretime` = ?, `scopejson` = ?
                         WHERE `id` = ? LIMIT 1'
                    );
                    $stmt->bindValue(1, $remark, PDO::PARAM_STR);
                    $stmt->bindValue(2, $quota);
                    $stmt->bindValue(3, $quotafallback, PDO::PARAM_INT);
                    self::bindNullableString($stmt, 4, $expiretime);
                    self::bindScopeParam($stmt, 5, $scopeNorm);
                    $stmt->bindValue(6, $id, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . $table . '`
                         SET `remark` = ?, `quota` = ?, `quotafallback` = ?, `expiretime` = ?
                         WHERE `id` = ? LIMIT 1'
                    );
                    $stmt->execute(array($remark, $quota, $quotafallback, $expiretime, $id));
                }
            }

            // 仅抬高配额或改为不限时，才允许再次发「配额用尽」邮件
            if ($quota <= 0 || $quota > $oldQuota) {
                self::clearQuotaNoticeFlag($id);
            }

            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * @param int    $id
     * @param int    $userId 0=管理员不校验归属
     * @param string $remark
     * @return true|string
     */
    public static function updateRemark($id, $userId, $remark)
    {
        return self::saveSettings($id, $userId, array('remark' => $remark));
    }

    /**
     * 重置密钥明文
     *
     * @param int $id
     * @param int $userId 0=管理员
     * @return array|string 成功返回 formatRow
     */
    public static function resetSecret($id, $userId)
    {
        $id = (int) $id;
        $userId = (int) $userId;
        $row = self::findById($id);
        if (!$row) {
            return '令牌不存在';
        }
        if ($userId > 0 && (int) $row['userid'] !== $userId) {
            return '无权操作该令牌';
        }

        $secret = self::makeUniqueSecret();
        if ($secret === '') {
            return '令牌生成失败，请稍后重试';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `secret` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($secret, $id));
            $fresh = self::findById($id);
            $formatted = self::formatRow($fresh);
            return $formatted ? $formatted : '重置失败';
        } catch (Exception $e) {
            return '重置失败，请稍后重试';
        }
    }

    /**
     * @param int $id
     * @param int $userId 0=管理员
     * @param int $status
     * @return true|string
     */
    public static function setStatus($id, $userId, $status)
    {
        $id = (int) $id;
        $userId = (int) $userId;
        $status = ((int) $status === self::STATUS_ENABLED)
            ? self::STATUS_ENABLED
            : self::STATUS_DISABLED;
        $row = self::findById($id);
        if (!$row) {
            return '令牌不存在';
        }
        if ($userId > 0 && (int) $row['userid'] !== $userId) {
            return '无权操作该令牌';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `status` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($status, $id));
            return true;
        } catch (Exception $e) {
            return '状态更新失败';
        }
    }

    /**
     * @param int $id
     * @param int $userId 0=管理员
     * @return true|string
     */
    public static function delete($id, $userId)
    {
        $id = (int) $id;
        $userId = (int) $userId;
        $row = self::findById($id);
        if (!$row) {
            return '令牌不存在';
        }
        if ($userId > 0 && (int) $row['userid'] !== $userId) {
            return '无权操作该令牌';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * 调用成功后累加次数
     *
     * @param int $id
     * @return void
     */
    public static function incrementCalls($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return;
        }
        try {
            $pdo = Database::connect();
            $keyTable = Database::table('apikey');
            $userTable = Database::table('user');
            // 令牌 calls + 用户 keycalls 同事务口径（用户列 v13.22.2+；缺列时仅更令牌）
            if (self::userHasKeycallsColumn()) {
                // 多表 UPDATE 不带 LIMIT（兼容 MySQL 5.7 / MariaDB）
                $stmt = $pdo->prepare(
                    'UPDATE `' . $keyTable . '` k
                     INNER JOIN `' . $userTable . '` u ON u.`id` = k.`userid`
                     SET k.`calls` = k.`calls` + 1, u.`keycalls` = u.`keycalls` + 1
                     WHERE k.`id` = ?'
                );
                $stmt->execute(array($id));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . $keyTable . '` SET `calls` = `calls` + 1 WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($id));
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * @return bool
     */
    public static function userHasKeycallsColumn()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . Database::table('user') . '` LIKE ' . $pdo->quote('keycalls')
            );
            $ok = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 用户累计密钥调用（优先 user.keycalls，缺列时汇总令牌）
     *
     * @param int $userId
     * @return int
     */
    public static function userKeyCallsTotal($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return 0;
        }
        if (self::userHasKeycallsColumn()) {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare(
                    'SELECT `keycalls` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($userId));
                $v = $stmt->fetchColumn();
                if ($v !== false) {
                    return (int) $v;
                }
            } catch (Exception $e) {
                // fall through
            }
        }
        if (!self::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(`calls`), 0) FROM `' . Database::table('apikey') . '` WHERE `userid` = ?'
            );
            $stmt->execute(array($userId));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param string $remark
     * @return string
     */
    private static function normalizeRemark($remark)
    {
        $remark = trim((string) $remark);
        if (function_exists('mb_substr')) {
            return mb_substr($remark, 0, 100, 'UTF-8');
        }
        return substr($remark, 0, 100);
    }

    /**
     * 规范化到期时间：支持 Y-m-d H:i:s / Y-m-d H:i；非法返回 false
     *
     * @param mixed $raw
     * @return string|false|null
     */
    private static function normalizeExpiretime($raw)
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        // 有效期提交为 Y-m-d H:i 或带 T 的变体，统一为空格分隔
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            $ts = strtotime($raw);
            return ($ts !== false) ? date('Y-m-d H:i:s', $ts) : false;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            $ts = strtotime($raw . ':00');
            return ($ts !== false) ? date('Y-m-d H:i:s', $ts) : false;
        }
        return false;
    }

    /**
     * @return string 失败返回空串
     */
    private static function makeUniqueSecret()
    {
        for ($i = 0; $i < 8; $i++) {
            $secret = self::generateSecret();
            if (!self::findBySecret($secret)) {
                return $secret;
            }
        }
        return '';
    }

    /**
     * SELECT 列清单（兼容未迁移站点无 pointsspent / quota 列）
     *
     * @param string $alias 表别名，空则无前缀
     * @return string
     */
    private static function selectColumnSql($alias = '')
    {
        $p = $alias !== '' ? (rtrim((string) $alias, '.') . '.') : '';
        $cols = $p . '`id`, ' . $p . '`userid`, ' . $p . '`remark`, ' . $p . '`secret`, '
            . $p . '`status`, ' . $p . '`calls`, ' . $p . '`createtime`';
        if (self::hasPointsspentColumn()) {
            $cols .= ', ' . $p . '`pointsspent`';
        }
        if (self::hasQuotaColumns()) {
            $cols .= ', ' . $p . '`quota`, ' . $p . '`quotaused`, '
                . $p . '`quotafallback`, ' . $p . '`expiretime`';
        }
        if (self::hasScopeColumn()) {
            $cols .= ', ' . $p . '`scopejson`';
        }
        return $cols;
    }

    /** @var bool|null */
    private static $hasPointsspentCol = null;

    /** @var int Unix 秒；与 $hasPointsspentCol 成对 */
    private static $hasPointsspentColAt = 0;

    /** @var bool|null */
    private static $hasQuotaCol = null;

    /** @var int */
    private static $hasQuotaColAt = 0;

    /** @var bool|null */
    private static $hasScopeCol = null;

    /** @var int */
    private static $hasScopeColAt = 0;

    /** @var array<string,int> keyId:reason => 上次打日志时间 */
    private static $scopeLogAt = array();

    /**
     * @return bool
     */
    public static function hasPointsspentColumn()
    {
        $now = time();
        if (self::$hasPointsspentCol !== null
            && ($now - self::$hasPointsspentColAt) < self::COLUMN_PROBE_TTL
        ) {
            return self::$hasPointsspentCol;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . Database::table('apikey') . '` LIKE ' . $pdo->quote('pointsspent')
            );
            self::$hasPointsspentCol = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            self::$hasPointsspentCol = false;
        }
        self::$hasPointsspentColAt = $now;
        return self::$hasPointsspentCol;
    }

    /**
     * @return void
     */
    public static function resetPointsspentColumnCache()
    {
        self::$hasPointsspentCol = null;
        self::$hasPointsspentColAt = 0;
    }

    /**
     * 是否已具备配额相关列（以 quota 列为探测代表）
     *
     * @return bool
     */
    public static function hasQuotaColumns()
    {
        $now = time();
        if (self::$hasQuotaCol !== null
            && ($now - self::$hasQuotaColAt) < self::COLUMN_PROBE_TTL
        ) {
            return self::$hasQuotaCol;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . Database::table('apikey') . '` LIKE ' . $pdo->quote('quota')
            );
            self::$hasQuotaCol = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            self::$hasQuotaCol = false;
        }
        self::$hasQuotaColAt = $now;
        return self::$hasQuotaCol;
    }

    /**
     * @return void
     */
    public static function resetQuotaColumnCache()
    {
        self::$hasQuotaCol = null;
        self::$hasQuotaColAt = 0;
    }

    /**
     * 是否已具备接口范围列 scopejson
     *
     * @return bool
     */
    public static function hasScopeColumn()
    {
        $now = time();
        if (self::$hasScopeCol !== null
            && ($now - self::$hasScopeColAt) < self::COLUMN_PROBE_TTL
        ) {
            return self::$hasScopeCol;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . Database::table('apikey') . '` LIKE ' . $pdo->quote('scopejson')
            );
            self::$hasScopeCol = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            self::$hasScopeCol = false;
        }
        self::$hasScopeColAt = $now;
        return self::$hasScopeCol;
    }

    /**
     * @return void
     */
    public static function resetScopeColumnCache()
    {
        self::$hasScopeCol = null;
        self::$hasScopeColAt = 0;
    }

    /**
     * 扣费前配额判定（不写库）
     *
     * @param int   $keyId
     * @param float $amount
     * @return array{ok:bool,use_quota:bool,msg:string,crossed_exhausted:bool,errcode?:int}
     */
    public static function prepareCharge($keyId, $amount)
    {
        $out = array(
            'ok'                => true,
            'use_quota'         => false,
            'msg'               => '',
            'crossed_exhausted' => false,
        );
        $keyId = (int) $keyId;
        $amount = round((float) $amount, 4);
        if ($amount < 0) {
            $amount = 0.0;
        }
        if ($keyId <= 0 || !self::hasQuotaColumns()) {
            return $out;
        }
        $row = self::findById($keyId);
        if (!$row) {
            return $out;
        }

        $quota = isset($row['quota']) ? (float) $row['quota'] : 0.0;
        if ($quota <= 0) {
            return $out;
        }

        $quotaused = isset($row['quotaused']) ? (float) $row['quotaused'] : 0.0;
        $quotafallback = isset($row['quotafallback']) ? (int) $row['quotafallback'] : 0;
        $epsilon = 0.00005;
        $after = $quotaused + $amount;

        if ($after <= $quota + $epsilon) {
            $out['use_quota'] = true;
            $out['crossed_exhausted'] = ($quotaused < $quota) && ($after >= $quota);
            return $out;
        }

        if ($quotafallback === 1) {
            // 回退账号积分扣费：不计入 quotaused；首次走回落时提醒（Redis 去重）
            $out['crossed_exhausted'] = true;
            return $out;
        }

        $out['ok'] = false;
        $out['errcode'] = class_exists('ApiError', false) ? ApiError::KEY_QUOTA : 11024;
        $out['msg'] = '令牌分配积分不足';
        return $out;
    }

    /**
     * 密钥配额已用量加减（正数累加 / 负数不低于 0）
     *
     * @param int      $keyId
     * @param float    $delta
     * @param PDO|null $pdo 传入则加入外层事务；独立调用失败静默并返回 false
     * @param bool     $respectQuota 为正增量时要求 quotaused+delta ≤ quota，防止并发超用
     * @return bool 是否写库成功（respectQuota 时 rowCount=0 亦为 false）
     */
    public static function adjustQuotaused($keyId, $delta, $pdo = null, $respectQuota = false)
    {
        $keyId = (int) $keyId;
        $delta = round((float) $delta, 4);
        if ($keyId <= 0 || $delta == 0.0 || !self::tableReady() || !self::hasQuotaColumns()) {
            return $delta == 0.0;
        }
        $own = ($pdo === null);
        try {
            if ($own) {
                $pdo = Database::connect();
            }
            if ($delta > 0) {
                if ($respectQuota) {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . Database::table('apikey') . '`
                         SET `quotaused` = `quotaused` + ?
                         WHERE `id` = ?
                           AND `quota` > 0
                           AND (`quotaused` + ?) <= (`quota` + 0.00005)
                         LIMIT 1'
                    );
                    $stmt->execute(array($delta, $keyId, $delta));
                    return $stmt->rowCount() > 0;
                }
                $stmt = $pdo->prepare(
                    'UPDATE `' . Database::table('apikey') . '`
                     SET `quotaused` = `quotaused` + ?
                     WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($delta, $keyId));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . Database::table('apikey') . '`
                     SET `quotaused` = GREATEST(0, `quotaused` + ?)
                     WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($delta, $keyId));
            }
            return true;
        } catch (Exception $e) {
            if (!$own) {
                throw $e;
            }
            return false;
        }
    }

    /** Redis 逻辑键前缀：令牌配额耗尽通知去重 */
    const REDIS_KEY_QUOTA_NOTICE_PREFIX = 'notify:key_quota:';

    /**
     * 清除令牌配额耗尽通知去重标记（配额抬高后可再次提醒）
     *
     * @param int $keyId
     * @return void
     */
    public static function clearQuotaNoticeFlag($keyId)
    {
        $keyId = (int) $keyId;
        if ($keyId <= 0 || !class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        try {
            RedisCache::forget(self::REDIS_KEY_QUOTA_NOTICE_PREFIX . $keyId);
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * 密钥累计消耗加减（接口扣费正数 / 退回负数；不低于 0）
     *
     * @param int      $keyId
     * @param float    $delta
     * @param PDO|null $pdo 传入则加入外层事务；独立调用失败静默
     * @return void
     */
    public static function adjustPointsspent($keyId, $delta, $pdo = null)
    {
        $keyId = (int) $keyId;
        $delta = round((float) $delta, 4);
        if ($keyId <= 0 || $delta == 0.0 || !self::tableReady() || !self::hasPointsspentColumn()) {
            return;
        }
        $own = ($pdo === null);
        try {
            if ($own) {
                $pdo = Database::connect();
            }
            if ($delta > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . Database::table('apikey') . '`
                     SET `pointsspent` = `pointsspent` + ?
                     WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($delta, $keyId));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . Database::table('apikey') . '`
                     SET `pointsspent` = GREATEST(0, `pointsspent` + ?)
                     WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($delta, $keyId));
            }
        } catch (Exception $e) {
            if (!$own) {
                throw $e;
            }
        }
    }

    /**
     * 解析库内 scopejson（读侧契约）
     *
     * @param mixed $raw
     * @param int   $keyId 仅用于坏数据日志
     * @return array{open:bool,bad:bool,c:int[],a:int[]}
     */
    public static function parseScope($raw, $keyId = 0)
    {
        $empty = array('open' => true, 'bad' => false, 'c' => array(), 'a' => array());
        if ($raw === null) {
            return $empty;
        }
        if (!is_string($raw)) {
            self::logScopeBad($keyId, 'non_string');
            return array('open' => false, 'bad' => true, 'c' => array(), 'a' => array());
        }
        $raw = trim($raw);
        if ($raw === '') {
            return $empty;
        }
        if (strlen($raw) > self::SCOPE_MAX_BYTES) {
            self::logScopeBad($keyId, 'too_long');
            return array('open' => false, 'bad' => true, 'c' => array(), 'a' => array());
        }
        // 根必须是 JSON 对象（区分 [] 与 {}；禁止数组根）；一次解码避免双重 json_decode
        $decoded = self::decodeScopeObject($raw);
        if ($decoded === false) {
            self::logScopeBad($keyId, 'json');
            return array('open' => false, 'bad' => true, 'c' => array(), 'a' => array());
        }
        if ($decoded === array()) {
            self::logScopeEmptyShape($keyId, 'empty_object');
            return $empty;
        }
        foreach (array_keys($decoded) as $k) {
            if ($k !== 'c' && $k !== 'a') {
                self::logScopeBad($keyId, 'unknown_key');
                return array('open' => false, 'bad' => true, 'c' => array(), 'a' => array());
            }
        }
        $c = array_key_exists('c', $decoded) ? $decoded['c'] : array();
        $a = array_key_exists('a', $decoded) ? $decoded['a'] : array();
        if (!self::isJsonListArray($c) || !self::isJsonListArray($a)) {
            self::logScopeBad($keyId, 'not_list');
            return array('open' => false, 'bad' => true, 'c' => array(), 'a' => array());
        }
        if ($c === array() && $a === array()) {
            self::logScopeEmptyShape($keyId, 'empty_lists');
            return $empty;
        }
        $cNorm = self::validateScopeIdList($c, self::SCOPE_MAX_C);
        $aNorm = self::validateScopeIdList($a, self::SCOPE_MAX_A);
        if ($cNorm === false || $aNorm === false) {
            self::logScopeBad($keyId, 'bad_items');
            return array('open' => false, 'bad' => true, 'c' => array(), 'a' => array());
        }
        return array('open' => false, 'bad' => false, 'c' => $cNorm, 'a' => $aNorm);
    }

    /**
     * 写库规范化：scope_mode 缺失=__keep__；all=NULL；list=JSON 或 NULL（满勾）
     *
     * @param mixed       $mode
     * @param mixed       $raw
     * @param mixed       $visibleOpt 已废弃（防伪造）；满勾对照改由服务端前台可见集计算，参数保留兼容调用方
     * @param string      $err
     * @param int[]|null  $keepCategoryIds 本钥已有分类 ID（停用可保留；新建传 []）
     * @return string|null|false '__keep__'|null|JSON|false
     */
    public static function normalizeScopeInput($mode, $raw, $visibleOpt, &$err, $keepCategoryIds = null)
    {
        unset($visibleOpt);
        $err = '';
        if ($mode === null || $mode === '') {
            return '__keep__';
        }
        $mode = strtolower(trim((string) $mode));
        if ($mode === 'all') {
            return null;
        }
        if ($mode !== 'list') {
            $err = '接口范围数据无效，请重新选择';
            return false;
        }
        if (!is_string($raw)) {
            $err = '接口范围数据无效，请重新选择';
            return false;
        }
        if (strlen($raw) > self::SCOPE_POST_MAX_BYTES) {
            $err = '接口范围数据过大';
            return false;
        }
        $decoded = self::decodeScopeObject($raw);
        if ($decoded === false) {
            $err = '接口范围数据无效，请重新选择';
            return false;
        }
        foreach (array_keys($decoded) as $k) {
            if ($k !== 'c' && $k !== 'a') {
                $err = '接口范围数据无效，请重新选择';
                return false;
            }
        }
        $c = array_key_exists('c', $decoded) ? $decoded['c'] : array();
        $a = array_key_exists('a', $decoded) ? $decoded['a'] : array();
        if (!self::isJsonListArray($c) || !self::isJsonListArray($a)) {
            $err = '接口范围数据无效，请重新选择';
            return false;
        }
        $cNorm = self::validateScopeIdList($c, self::SCOPE_PARSE_MAX_C);
        $aNorm = self::validateScopeIdList($a, self::SCOPE_PARSE_MAX_A);
        if ($cNorm === false || $aNorm === false) {
            if (is_array($c) && count($c) > self::SCOPE_PARSE_MAX_C) {
                $err = '选择的接口或分类过多';
                return false;
            }
            if (is_array($a) && count($a) > self::SCOPE_PARSE_MAX_A) {
                $err = '选择的接口或分类过多';
                return false;
            }
            $err = '接口范围数据无效，请重新选择';
            return false;
        }
        if ($cNorm === array() && $aNorm === array()) {
            $err = '请至少选择一个接口或分类，或选择不限';
            return false;
        }
        $keepC = array();
        if (is_array($keepCategoryIds)) {
            foreach ($keepCategoryIds as $kid) {
                $kid = (int) $kid;
                if ($kid > 0) {
                    $keepC[$kid] = true;
                }
            }
        }
        if (!self::scopeIdsExist($cNorm, $aNorm, $err, $keepC)) {
            if ($err === '') {
                $err = '所选接口或分类不可用，请刷新后重新选择';
            }
            return false;
        }
        $aNorm = self::foldScopeApis($cNorm, $aNorm);
        if ($cNorm === array() && $aNorm === array()) {
            $err = '请至少选择一个接口或分类，或选择不限';
            return false;
        }
        // 满勾→NULL：对照服务端公开接口全集（listPublicLiteAll），忽略客户端 scope_visible
        if (self::isFullVisibleScope($cNorm, $aNorm)) {
            return null;
        }
        if (count($cNorm) > self::SCOPE_MAX_C || count($aNorm) > self::SCOPE_MAX_A) {
            $err = '选择的接口或分类过多';
            return false;
        }
        $json = self::encodeScopeJson($cNorm, $aNorm);
        if ($json === false || strlen($json) > self::SCOPE_MAX_BYTES) {
            $err = '接口范围数据过大';
            return false;
        }
        return $json;
    }

    /**
     * 密钥是否允许调用该接口（读侧；坏 JSON=全拒）
     *
     * @param array $keyRow
     * @param array $apiRow 须含 id；category 名为可选
     * @return bool
     */
    public static function allowsApi(array $keyRow, array $apiRow)
    {
        if (!self::hasScopeColumn()) {
            return true;
        }
        // 已有范围列却未选入行：fail-closed，禁止当成「不限」
        if (!array_key_exists('scopejson', $keyRow)) {
            self::logScopeBad(isset($keyRow['id']) ? (int) $keyRow['id'] : 0, 'missing_col');
            return false;
        }
        $raw = $keyRow['scopejson'];
        $parsed = self::parseScope($raw, isset($keyRow['id']) ? (int) $keyRow['id'] : 0);
        if (!empty($parsed['bad'])) {
            return false;
        }
        if (!empty($parsed['open'])) {
            return true;
        }
        $apiId = isset($apiRow['id']) ? (int) $apiRow['id'] : 0;
        if ($apiId <= 0) {
            // L-A：缺 id / 非法 id 不得跳过范围（fail-closed）
            self::logScopeBad(isset($keyRow['id']) ? (int) $keyRow['id'] : 0, 'missing_api_id');
            return false;
        }
        $a = isset($parsed['a']) && is_array($parsed['a']) ? $parsed['a'] : array();
        if (in_array($apiId, $a, true)) {
            return true;
        }
        $c = isset($parsed['c']) && is_array($parsed['c']) ? $parsed['c'] : array();
        if ($c === array()) {
            return false;
        }
        $catName = isset($apiRow['category']) ? trim((string) $apiRow['category']) : '';
        if ($catName === '') {
            return false;
        }
        $catId = self::resolveCategoryIdByName($catName);
        if ($catId <= 0) {
            return false;
        }
        return in_array($catId, $c, true);
    }

    /**
     * 展示摘要：全部可调用 / 可调用 N 个分类 / … / 范围异常
     *
     * @param array $parsed parseScope 结果
     * @return string
     */
    public static function scopeLabelFromParsed(array $parsed)
    {
        if (!empty($parsed['bad'])) {
            return '范围异常';
        }
        if (!empty($parsed['open'])) {
            return '全部可调用';
        }
        $nc = isset($parsed['c']) && is_array($parsed['c']) ? count($parsed['c']) : 0;
        $na = isset($parsed['a']) && is_array($parsed['a']) ? count($parsed['a']) : 0;
        if ($nc > 0 && $na > 0) {
            return '可调用 ' . $nc . ' 个分类 · ' . $na . ' 个接口';
        }
        if ($nc > 0) {
            return '可调用 ' . $nc . ' 个分类';
        }
        if ($na > 0) {
            return '可调用 ' . $na . ' 个接口';
        }
        return '全部可调用';
    }

    /**
     * @param PDOStatement $stmt
     * @param int          $pos 1-based
     * @param string|null  $value
     * @return void
     */
    private static function bindScopeParam($stmt, $pos, $value)
    {
        if ($value === null) {
            $stmt->bindValue($pos, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($pos, (string) $value, PDO::PARAM_STR);
        }
    }

    /**
     * @param PDOStatement $stmt
     * @param int          $pos
     * @param string|null  $value
     * @return void
     */
    private static function bindNullableString($stmt, $pos, $value)
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($pos, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($pos, (string) $value, PDO::PARAM_STR);
        }
    }

    /**
     * JSON 数组必须是列表（0..n-1），拒绝关联对象冒充
     *
     * @param mixed $arr
     * @return bool
     */
    private static function isJsonListArray($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        if ($arr === array()) {
            return true;
        }
        $i = 0;
        foreach ($arr as $k => $v) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }
        return true;
    }

    /**
     * @param array $list
     * @param int   $max
     * @return int[]|false
     */
    private static function validateScopeIdList($list, $max)
    {
        if (!self::isJsonListArray($list)) {
            return false;
        }
        if (count($list) > $max) {
            return false;
        }
        $out = array();
        $seen = array();
        foreach ($list as $item) {
            if (!is_int($item) || $item < 1 || $item > 2147483647) {
                return false;
            }
            if (isset($seen[$item])) {
                continue;
            }
            $seen[$item] = true;
            $out[] = $item;
        }
        sort($out, SORT_NUMERIC);
        return $out;
    }

    /**
     * @param int[]             $c
     * @param int[]             $a
     * @param string            $err
     * @param array<int,true>   $keepC 本钥已有分类：停用可保留；新勾须 status=1
     * @return bool
     */
    private static function scopeIdsExist(array $c, array $a, &$err, array $keepC = array())
    {
        $err = '';
        try {
            $pdo = Database::connect();
            if ($c !== array()) {
                // 存在即可查；启用可新勾；停用仅当本钥已有（keepC）
                $ph = implode(',', array_fill(0, count($c), '?'));
                $catTable = Database::table('category');
                $stmt = $pdo->prepare(
                    'SELECT `id`, `status` FROM `' . $catTable . '` WHERE `id` IN (' . $ph . ')'
                );
                $stmt->execute(array_values($c));
                $found = array();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $found[(int) $row['id']] = ((int) $row['status'] === 1);
                }
                foreach ($c as $id) {
                    if (!isset($found[$id])) {
                        $err = '所选分类不存在，请刷新后重新选择';
                        return false;
                    }
                    if (!$found[$id] && !isset($keepC[$id])) {
                        $err = '所选分类已停用，不能新勾选；已授权的可保留';
                        return false;
                    }
                }
            }
            if ($a !== array()) {
                // 与 scope_catalog / 选择器一致：只允许前台可见接口（禁止盲写禁用接口 ID）
                $allowed = array();
                if (class_exists('ApiManager') && ApiManager::tableReady()) {
                    foreach (ApiManager::listPublicLiteAll() as $api) {
                        if (is_array($api) && isset($api['id'])) {
                            $aid = (int) $api['id'];
                            if ($aid > 0) {
                                $allowed[$aid] = true;
                            }
                        }
                    }
                }
                foreach ($a as $id) {
                    if (!isset($allowed[$id])) {
                        $err = '所选接口未公开或不存在，请刷新后重新选择';
                        return false;
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            $err = '接口范围数据无效，请重新选择';
            return false;
        }
    }

    /**
     * 所属分类已在 c 中的 a 项折叠删除
     *
     * @param int[] $c
     * @param int[] $a
     * @return int[]
     */
    private static function foldScopeApis(array $c, array $a)
    {
        if ($a === array() || $c === array()) {
            return $a;
        }
        $cSet = array();
        foreach ($c as $cid) {
            $cSet[(int) $cid] = true;
        }
        $map = self::apiIdToCategoryIdMap($a);
        $out = array();
        foreach ($a as $aid) {
            $aid = (int) $aid;
            $catId = isset($map[$aid]) ? (int) $map[$aid] : 0;
            if ($catId > 0 && isset($cSet[$catId])) {
                continue;
            }
            $out[] = $aid;
        }
        sort($out, SORT_NUMERIC);
        return $out;
    }

    /**
     * @param int[] $apiIds
     * @return array<int,int> apiId => categoryId（0=未分类/未知）
     */
    private static function apiIdToCategoryIdMap(array $apiIds)
    {
        $out = array();
        if ($apiIds === array()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            $ph = implode(',', array_fill(0, count($apiIds), '?'));
            $apiTable = Database::table('api');
            $stmt = $pdo->prepare(
                'SELECT a.`id`, a.`category` FROM `' . $apiTable . '` a WHERE a.`id` IN (' . $ph . ')'
            );
            $stmt->execute(array_values($apiIds));
            $nameToId = self::categoryNameIdMap();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $aid = (int) $row['id'];
                $name = isset($row['category']) ? trim((string) $row['category']) : '';
                $out[$aid] = ($name !== '' && isset($nameToId[$name])) ? (int) $nameToId[$name] : 0;
            }
        } catch (Exception $e) {
            // 折叠失败时保守保留 a（存在性已校验）
        }
        return $out;
    }

    /**
     * 分类名 → id（含停用；请求内缓存）
     *
     * @return array<string,int>
     */
    private static function categoryNameIdMap()
    {
        if (self::$categoryNameIdCache !== null) {
            return self::$categoryNameIdCache;
        }
        self::$categoryNameIdCache = array();
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SELECT `id`, `name` FROM `' . Database::table('category') . '`');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($name !== '') {
                    self::$categoryNameIdCache[$name] = (int) $row['id'];
                }
            }
        } catch (Exception $e) {
            self::$categoryNameIdCache = array();
        }
        return self::$categoryNameIdCache;
    }

    /**
     * @param string $name
     * @return int
     */
    public static function resolveCategoryIdByName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 0;
        }
        $map = self::categoryNameIdMap();
        return isset($map[$name]) ? (int) $map[$name] : 0;
    }

    /**
     * 服务端前台可见满勾形态：
     * - 启用且有公开接口的分类 → c
     * - 停用分类下的公开接口 → a（不进 c，避免满勾对不上 / 写侧拒停用新勾）
     * - 未分类公开接口 → a
     *
     * @return array{c:int[],a:int[]}
     */
    private static function buildServerVisibleFolded()
    {
        $c = array();
        $a = array();
        if (!class_exists('ApiManager') || !ApiManager::tableReady()) {
            return array('c' => $c, 'a' => $a);
        }
        $enabledCat = self::enabledCategoryIdSet();
        $enabledHasApi = array();
        foreach (ApiManager::listPublicLiteAll() as $api) {
            if (!is_array($api) || !isset($api['id'])) {
                continue;
            }
            $aid = (int) $api['id'];
            if ($aid <= 0) {
                continue;
            }
            $catName = isset($api['category']) ? trim((string) $api['category']) : '';
            $cid = ($catName !== '') ? self::resolveCategoryIdByName($catName) : 0;
            if ($cid > 0 && isset($enabledCat[$cid])) {
                $enabledHasApi[$cid] = true;
            } else {
                // 未分类、或停用分类下的公开接口：按单接口进 a
                $a[] = $aid;
            }
        }
        $c = array_keys($enabledHasApi);
        sort($c, SORT_NUMERIC);
        $a = array_values(array_unique($a));
        sort($a, SORT_NUMERIC);
        return array('c' => $c, 'a' => $a);
    }

    /**
     * 启用中分类 ID 集合（请求内缓存）
     *
     * @return array<int,true>
     */
    private static function enabledCategoryIdSet()
    {
        if (self::$enabledCategoryIdCache !== null) {
            return self::$enabledCategoryIdCache;
        }
        self::$enabledCategoryIdCache = array();
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT `id` FROM `' . Database::table('category') . '` WHERE `status` = 1'
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) $row['id'];
                if ($id > 0) {
                    self::$enabledCategoryIdCache[$id] = true;
                }
            }
        } catch (Exception $e) {
            self::$enabledCategoryIdCache = array();
        }
        return self::$enabledCategoryIdCache;
    }

    /**
     * @param int[] $c
     * @param int[] $a
     * @return bool
     */
    private static function isFullVisibleScope(array $c, array $a)
    {
        $visible = self::buildServerVisibleFolded();
        $vcNorm = $visible['c'];
        $vaNorm = $visible['a'];
        if ($vcNorm === array() && $vaNorm === array()) {
            return false;
        }
        // 集合比较（两侧再 sort，避免顺序差异误判）
        return self::sortedIdListsEqual($c, $vcNorm) && self::sortedIdListsEqual($a, $vaNorm);
    }

    /**
     * @param int[] $left
     * @param int[] $right
     * @return bool
     */
    private static function sortedIdListsEqual(array $left, array $right)
    {
        if (count($left) !== count($right)) {
            return false;
        }
        $l = array_values($left);
        $r = array_values($right);
        sort($l, SORT_NUMERIC);
        sort($r, SORT_NUMERIC);
        return $l === $r;
    }

    /**
     * 一次解码：根须为 JSON 对象；失败返回 false
     *
     * @param string $raw
     * @return array|false
     */
    private static function decodeScopeObject($raw)
    {
        $probe = json_decode($raw, false, 4);
        if (json_last_error() !== JSON_ERROR_NONE || !($probe instanceof stdClass)) {
            return false;
        }
        $decoded = array();
        foreach (get_object_vars($probe) as $k => $v) {
            $decoded[$k] = $v;
        }
        return $decoded;
    }

    /**
     * @param int[] $c
     * @param int[] $a
     * @return string|false
     */
    private static function encodeScopeJson(array $c, array $a)
    {
        $payload = array('c' => array_values($c), 'a' => array_values($a));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : false;
    }

    /**
     * @param int    $keyId
     * @param string $reason
     * @return void
     */
    private static function logScopeBad($keyId, $reason)
    {
        $keyId = (int) $keyId;
        $reason = preg_replace('/[^a-z0-9_]/i', '', (string) $reason);
        if ($reason === '') {
            $reason = 'unknown';
        }
        if (!self::shouldLogScope($keyId, $reason)) {
            return;
        }
        error_log('ApiKeyManager scopejson bad key=' . $keyId . ' reason=' . $reason);
    }

    /**
     * @param int    $keyId
     * @param string $shape
     * @return void
     */
    private static function logScopeEmptyShape($keyId, $shape)
    {
        $keyId = (int) $keyId;
        $shape = preg_replace('/[^a-z0-9_]/i', '', (string) $shape);
        if ($shape === '') {
            $shape = 'unknown';
        }
        if (!self::shouldLogScope($keyId, 'empty_' . $shape)) {
            return;
        }
        error_log('ApiKeyManager scopejson empty-shape key=' . $keyId . ' shape=' . $shape);
    }

    /**
     * 同 key+原因限频，避免热路径刷爆 error_log
     *
     * @param int    $keyId
     * @param string $slot
     * @return bool
     */
    private static function shouldLogScope($keyId, $slot)
    {
        $key = ((int) $keyId) . ':' . $slot;
        $now = time();
        if (isset(self::$scopeLogAt[$key])
            && ($now - self::$scopeLogAt[$key]) < self::SCOPE_LOG_INTERVAL
        ) {
            return false;
        }
        self::$scopeLogAt[$key] = $now;
        if (count(self::$scopeLogAt) > 256) {
            self::$scopeLogAt = array_slice(self::$scopeLogAt, -128, null, true);
        }
        return true;
    }

    /** @var array<string,int>|null */
    private static $categoryNameIdCache = null;

    /** @var array<int,true>|null 启用中分类 id 集合 */
    private static $enabledCategoryIdCache = null;
}
