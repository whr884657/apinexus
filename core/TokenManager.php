<?php
/**
 * 文件：core/TokenManager.php
 * 作用：用户 API 调用令牌 CRUD（每用户最多 3 条）
 */

class TokenManager
{
    /** 每用户令牌上限 */
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
            return DatabaseMigrator::tableExists('token');
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
     * 生成 SK- + 32 位随机十六进制字符
     *
     * @return string
     */
    public static function generateSecret()
    {
        return 'SK-' . bin2hex(random_bytes(16));
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
            $table = Database::table('token');
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
            $table = Database::table('token');
            $stmt = $pdo->prepare(
                'SELECT `id`, `userid`, `remark`, `secret`, `status`, `calls`, `createtime`
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
            $tokenTable = Database::table('token');
            $userTable = Database::table('user');
            $sql = 'SELECT t.`id`, t.`userid`, t.`remark`, t.`secret`, t.`status`, t.`calls`, t.`createtime`,
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
            $table = Database::table('token');
            $stmt = $pdo->prepare(
                'SELECT `id`, `userid`, `remark`, `secret`, `status`, `calls`, `createtime`
                 FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
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
            $table = Database::table('token');
            $stmt = $pdo->prepare(
                'SELECT `id`, `userid`, `remark`, `secret`, `status`, `calls`, `createtime`
                 FROM `' . $table . '` WHERE `secret` = ? LIMIT 1'
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
            $table = Database::table('token');
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
            $table = Database::table('token');
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
            $table = Database::table('token');
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
            $table = Database::table('token');
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
            $table = Database::table('token');
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * 调用成功后累加次数（后续密钥校验接入时使用）
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
            $table = Database::table('token');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `calls` = `calls` + 1 WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
        } catch (Exception $e) {
            // ignore
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
}
