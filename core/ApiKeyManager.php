<?php
/**
 * 文件：core/ApiKeyManager.php
 * 作用：用户 API 调用密钥 CRUD（每用户最多 3 条）
 */

class ApiKeyManager
{
    /** 每用户密钥上限 */
    const MAX_PER_USER = 3;

    /** 状态：禁用 */
    const STATUS_DISABLED = 0;
    /** 状态：启用 */
    const STATUS_ENABLED = 1;

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
                           u.`username` AS `username`
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
        return array(
            'id'           => (int) $row['id'],
            'userid'       => (int) $row['userid'],
            'remark'       => (string) $row['remark'],
            'secret'       => (string) $row['secret'],
            'status'       => $status,
            'status_label' => self::statusLabel($status),
            'calls'        => isset($row['calls']) ? (int) $row['calls'] : 0,
            'pointsspent'  => isset($row['pointsspent']) ? (float) $row['pointsspent'] : 0.0,
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'username'     => isset($row['username']) ? (string) $row['username'] : '',
        );
    }

    /**
     * @param int    $userId
     * @param string $remark
     * @return array|string 成功返回 formatRow，失败返回错误文案
     */
    public static function create($userId, $remark)
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
        if (self::countByUser($userId) >= self::MAX_PER_USER) {
            return '每个账号最多 ' . self::MAX_PER_USER . ' 个令牌，请先删除不用的令牌';
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
            $row = self::findById($id);
            $formatted = self::formatRow($row);
            return $formatted ? $formatted : '创建失败';
        } catch (Exception $e) {
            return '创建失败，请稍后重试';
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
        $id = (int) $id;
        $userId = (int) $userId;
        $remark = self::normalizeRemark($remark);
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
     * SELECT 列清单（兼容未迁移站点无 pointsspent）
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
        return $cols;
    }

    /** @var bool|null */
    private static $hasPointsspentCol = null;

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
