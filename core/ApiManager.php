<?php
/**
 * 文件：core/ApiManager.php
 * 作用：API 接口数据与审核状态管理
 */

class ApiManager
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_OFFLINE = 'offline';

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('api');
    }

    /**
     * 前台公开展示的已通过接口
     *
     * @return array
     */
    public static function listPublic()
    {
        return self::listAll(self::STATUS_APPROVED);
    }

    /**
     * @return int
     */
    public static function countApproved()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `status` = ?'
            );
            $stmt->execute(array(self::STATUS_APPROVED));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 前台展示的累计调用次数（读取系统配置 api_total_calls，默认 0）
     *
     * @return int
     */
    public static function totalCallCount()
    {
        if (!class_exists('Config')) {
            return 0;
        }
        try {
            return max(0, (int) Config::get('api_total_calls', 0));
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 从接口列表提取去重后的分类名
     *
     * @param array $apis
     * @return array
     */
    public static function categoriesFromList(array $apis)
    {
        $cats = array();
        foreach ($apis as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) (isset($row['category']) ? $row['category'] : ''));
            if ($name !== '' && !in_array($name, $cats, true)) {
                $cats[] = $name;
            }
        }
        sort($cats, SORT_STRING);
        return $cats;
    }

    /**
     * @param string|null $status
     * @return array
     */
    public static function listAll($status = null)
    {
        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.*, u.`username`, u.`email`
                    FROM `' . self::table() . '` AS a
                    LEFT JOIN `' . Database::table('user') . '` AS u ON u.`id` = a.`user_id`';
            $params = array();
            if ($status !== null && $status !== '') {
                $sql .= ' WHERE a.`status` = ?';
                $params[] = (string) $status;
            }
            $sql .= ' ORDER BY a.`id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @return int
     */
    public static function countPending()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `status` = ?'
            );
            $stmt->execute(array(self::STATUS_PENDING));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $apiId
     * @return array|null
     */
    public static function findById($apiId)
    {
        $apiId = (int) $apiId;
        if ($apiId <= 0) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT a.*, u.`username`, u.`email`
                 FROM `' . self::table() . '` AS a
                 LEFT JOIN `' . Database::table('user') . '` AS u ON u.`id` = a.`user_id`
                 WHERE a.`id` = ? LIMIT 1'
            );
            $stmt->execute(array($apiId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int    $apiId
     * @param string $status
     * @param string $rejectReason
     * @return true|string
     */
    public static function setStatus($apiId, $status, $rejectReason = '')
    {
        $apiId = (int) $apiId;
        $allowed = array(
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_OFFLINE,
        );
        if ($apiId <= 0 || !in_array($status, $allowed, true)) {
            return '无效操作';
        }

        $row = self::findById($apiId);
        if (!$row) {
            return '接口不存在';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '`
                 SET `status` = ?, `reject_reason` = ?, `updated_at` = NOW()
                 WHERE `id` = ?'
            );
            $reason = $status === self::STATUS_REJECTED ? trim((string) $rejectReason) : '';
            $stmt->execute(array($status, $reason, $apiId));
            return true;
        } catch (Exception $e) {
            return '操作失败，请稍后重试';
        }
    }

    /**
     * @param string $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $map = array(
            self::STATUS_PENDING  => '待审核',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已拒绝',
            self::STATUS_OFFLINE  => '已下线',
        );
        return isset($map[$status]) ? $map[$status] : (string) $status;
    }
}
