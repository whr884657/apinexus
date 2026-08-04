<?php
/**
 * 文件：core/UserManager.php
 * 作用：管理员用户列表查询
 */

class UserManager
{
    /**
     * @return array
     */
    public static function all()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $pointsCol = class_exists('PointsManager') && PointsManager::hasPointsColumn()
                ? ', `points`'
                : '';
            $stmt = $pdo->query(
                'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`,
                        `status`, `role`' . $pointsCol . ', `createtime`, `lastlogin`
                 FROM `' . $table . '`
                 ORDER BY `id` DESC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return array();
            }
            return self::attachListStats($rows);
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 列表附加：发布接口数（开发者用）+ 本人累计调用次数（apilog.userid）
     *
     * @param array $rows
     * @return array
     */
    public static function attachListStats(array $rows)
    {
        if ($rows === array()) {
            return $rows;
        }
        $ids = array();
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === array()) {
            return $rows;
        }

        $apiCounts = array();
        $callCounts = array();
        try {
            $pdo = Database::connect();
            $in = implode(',', array_fill(0, count($ids), '?'));

            if (class_exists('ApiManager') && ApiManager::tableReady()) {
                $apiTable = Database::table('api');
                $stmt = $pdo->prepare(
                    'SELECT `userid`, COUNT(*) AS `cnt` FROM `' . $apiTable . '`
                     WHERE `userid` IN (' . $in . ') GROUP BY `userid`'
                );
                $stmt->execute($ids);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $apiCounts[(int) $r['userid']] = (int) $r['cnt'];
                }
            }

            if (class_exists('ApiLogManager') && ApiLogManager::tableReady()) {
                $logTable = Database::table('apilog');
                $stmt = $pdo->prepare(
                    'SELECT `userid`, COUNT(*) AS `cnt` FROM `' . $logTable . '`
                     WHERE `userid` IN (' . $in . ') GROUP BY `userid`'
                );
                $stmt->execute($ids);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $callCounts[(int) $r['userid']] = (int) $r['cnt'];
                }
            }
        } catch (Exception $e) {
            // 统计失败不阻断列表
        }

        foreach ($rows as $i => $row) {
            $uid = isset($row['id']) ? (int) $row['id'] : 0;
            $rows[$i]['api_count'] = isset($apiCounts[$uid]) ? $apiCounts[$uid] : 0;
            $rows[$i]['call_count'] = isset($callCounts[$uid]) ? $callCounts[$uid] : 0;
        }
        return $rows;
    }

    /**
     * @param string $account 用户名或邮箱
     * @return array|null
     */
    public static function findByAccount($account)
    {
        $account = trim((string) $account);
        if ($account === '') {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `status`, `role`, `createtime`
                 FROM `' . $table . '`
                 WHERE `username` = ? OR `email` = ?
                 LIMIT 1'
            );
            $stmt->execute(array($account, $account));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public static function findById($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `status`, `role`, `createtime`
                 FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return int
     */
    public static function count()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            return (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $userId
     * @param int $status 1=正常 0=封禁
     * @return true|string
     */
    public static function setStatus($userId, $status)
    {
        $userId = (int) $userId;
        $status = (int) $status;
        if ($userId <= 0) {
            return '无效用户';
        }
        if ($status !== 0 && $status !== 1) {
            return '无效状态';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `status` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($status, $userId));
            if ($stmt->rowCount() === 0 && !self::exists($userId)) {
                return '用户不存在';
            }
            return true;
        } catch (Exception $e) {
            return '操作失败：' . $e->getMessage();
        }
    }

    /**
     * @param int    $userId
     * @param string $role user|developer
     * @return true|string
     */
    public static function setRole($userId, $role)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '无效用户';
        }

        $role = UserRole::normalize($role);

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `role` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($role, $userId));
            if ($stmt->rowCount() === 0 && !self::exists($userId)) {
                return '用户不存在';
            }
            return true;
        } catch (Exception $e) {
            return '操作失败：' . $e->getMessage();
        }
    }

    /**
     * @param int $userId
     * @return true|string
     */
    public static function delete($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '无效用户';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($userId));
            if ($stmt->rowCount() === 0) {
                return '用户不存在';
            }
            return true;
        } catch (Exception $e) {
            return '删除失败：' . $e->getMessage();
        }
    }

    /**
     * @param int $userId
     * @return bool
     */
    public static function exists($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($userId));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }
}
