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
            $stmt = $pdo->query(
                'SELECT `id`, `username`, `email`, `avatar_url`, `oauth_qq_openid`, `oauth_gitee_id`,
                        `status`, `role`, `created_at`, `last_login_at`
                 FROM `' . $table . '`
                 ORDER BY `id` DESC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
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
                'SELECT `id`, `username`, `email`, `avatar_url`, `status`, `role`, `created_at`
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
                'SELECT `id`, `username`, `email`, `avatar_url`, `status`, `role`, `created_at`
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
