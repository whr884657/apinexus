<?php
/**
 * 文件：core/ApiKeyManager.php
 * 作用：用户 API 调用密钥 CRUD（每用户上限由系统设置 apikey_max 配置，默认 3、最大 20）；
 *       含 pointsspent 累计消耗、配额 quota/quotaused/quotafallback、到期 expiretime
 */

class ApiKeyManager
{
    /** 默认每用户密钥上限（与配置缺省一致） */
    const DEFAULT_MAX_PER_USER = 3;

    /** 管理员可配置的绝对上限 */
    const ABSOLUTE_MAX_PER_USER = 20;

    /** 管理员可配置的下限 */
    const MIN_PER_USER = 1;

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
     * @param array  $extra 可选 quota / quotafallback / expiretime
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

            if (self::hasQuotaColumns() && is_array($extra) && $extra !== array()) {
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
                        // INSERT 已成功：配额写入失败须删掉孤儿令牌，避免占满名额却不可见
                        try {
                            $del = $pdo->prepare(
                                'DELETE FROM `' . $table . '` WHERE `id` = ? AND `userid` = ? LIMIT 1'
                            );
                            $del->execute(array($id, $userId));
                        } catch (Exception $eDel) {
                            // 删除失败仍返回原错误，便于排查
                        }
                        return is_string($saved) ? $saved : '创建失败';
                    }
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
     * 保存令牌设置（名称 / 配额 / 回退 / 到期）
     *
     * @param int   $id
     * @param int   $userId 0=管理员不校验归属
     * @param array $input  remark(必填)；quota / quotafallback / expiretime 可选（缺省保留原值）
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
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `remark` = ? WHERE `id` = ? LIMIT 1');
                $stmt->execute(array($remark, $id));
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
                $stmt = $pdo->prepare(
                    'UPDATE `' . $table . '`
                     SET `remark` = ?, `quota` = ?, `quotaused` = 0, `quotafallback` = ?, `expiretime` = ?
                     WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($remark, $quota, $quotafallback, $expiretime, $id));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . $table . '`
                     SET `remark` = ?, `quota` = ?, `quotafallback` = ?, `expiretime` = ?
                     WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($remark, $quota, $quotafallback, $expiretime, $id));
            }

            // 配额抬高、改为不限，或不再处于耗尽态时，清掉配额耗尽通知去重标记
            if ($quota <= 0 || $quota > $oldQuota || $quota > $oldUsed) {
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
        return $cols;
    }

    /** @var bool|null */
    private static $hasPointsspentCol = null;

    /** @var bool|null */
    private static $hasQuotaCol = null;

    /**
     * @return bool
     */
    public static function hasPointsspentColumn()
    {
        if (self::$hasPointsspentCol !== null) {
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
        return self::$hasPointsspentCol;
    }

    /**
     * @return void
     */
    public static function resetPointsspentColumnCache()
    {
        self::$hasPointsspentCol = null;
    }

    /**
     * 是否已具备配额相关列（以 quota 列为探测代表）
     *
     * @return bool
     */
    public static function hasQuotaColumns()
    {
        if (self::$hasQuotaCol !== null) {
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
        return self::$hasQuotaCol;
    }

    /**
     * @return void
     */
    public static function resetQuotaColumnCache()
    {
        self::$hasQuotaCol = null;
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
}
