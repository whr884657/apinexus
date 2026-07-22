<?php
/**
 * 文件：core/AdminUserBinding.php
 * 作用：管理员账号与用户账号绑定（后台发布内容身份）
 */

class AdminUserBinding
{
    /**
     * 前台用户是否已绑定某管理员（可代管本地接口投稿）
     *
     * @param int $userId
     * @return bool
     */
    public static function isUserBoundToAdmin($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . Database::table('admin') . '`
                 WHERE `binduid` = ? AND `status` = 1 LIMIT 1'
            );
            $stmt->execute(array($userId));
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取当前管理员绑定的用户
     *
     * @param int $adminId
     * @return array|null
     */
    public static function getBoundUser($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $adminTable = Database::table('admin');
            $userTable = Database::table('user');

            $stmt = $pdo->prepare(
                'SELECT u.`id`, u.`username`, u.`email`, u.`status`, u.`avatar`, u.`createtime`
                 FROM `' . $adminTable . '` a
                 INNER JOIN `' . $userTable . '` u ON u.`id` = a.`binduid`
                 WHERE a.`id` = ? AND u.`status` = 1
                 LIMIT 1'
            );
            $stmt->execute(array($adminId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 管理员后台发布内容时使用的用户身份 ID
     *
     * @param int $adminId
     * @return int|string 成功返回 user_id，失败返回错误信息
     */
    public static function publishUserId($adminId)
    {
        $bound = self::getBoundUser($adminId);
        if (!$bound) {
            return '请先在账号设置中绑定用户账号，后台发布接口/文章等内容须使用绑定身份';
        }
        return (int) $bound['id'];
    }

    /**
     * 绑定用户账号
     *
     * @param int    $adminId
     * @param string $account 用户名或邮箱
     * @return true|array|string true 或错误信息；成功时也可返回 array 用户信息
     */
    public static function bind($adminId, $account)
    {
        $adminId = (int) $adminId;
        $account = trim((string) $account);

        if ($adminId <= 0) {
            return '无效管理员';
        }
        if ($account === '') {
            return '请输入要绑定的用户名或邮箱';
        }

        $user = UserManager::findByAccount($account);
        if (!$user) {
            return '用户不存在，请确认用户名或邮箱';
        }
        if ((int) $user['status'] !== 1) {
            return '该用户已被封禁，无法绑定';
        }

        $userId = (int) $user['id'];

        try {
            $pdo = Database::connect();
            $adminTable = Database::table('admin');

            $check = $pdo->prepare(
                'SELECT `id`, `username` FROM `' . $adminTable . '` WHERE `binduid` = ? AND `id` != ? LIMIT 1'
            );
            $check->execute(array($userId, $adminId));
            $other = $check->fetch(PDO::FETCH_ASSOC);
            if ($other) {
                return '该用户已绑定其他管理员账号';
            }

            $stmt = $pdo->prepare('UPDATE `' . $adminTable . '` SET `binduid` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($userId, $adminId));

            return array(
                'id' => $userId,
                'username' => $user['username'],
                'email' => $user['email'],
            );
        } catch (Exception $e) {
            return '绑定失败：' . $e->getMessage();
        }
    }

    /**
     * 解除绑定
     *
     * @param int $adminId
     * @return true|string
     */
    public static function unbind($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return '无效管理员';
        }

        try {
            $pdo = Database::connect();
            $adminTable = Database::table('admin');
            $stmt = $pdo->prepare('UPDATE `' . $adminTable . '` SET `binduid` = NULL WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($adminId));
            return true;
        } catch (Exception $e) {
            return '解绑失败：' . $e->getMessage();
        }
    }

    /**
     * 全站有效绑定身份数量（去重 binduid）
     *
     * @return int
     */
    public static function activeBindUserCount()
    {
        try {
            $pdo = Database::connect();
            $n = $pdo->query(
                'SELECT COUNT(DISTINCT `binduid`) FROM `' . Database::table('admin') . '`'
                . ' WHERE `binduid` IS NOT NULL AND `binduid` > 0 AND `status` = 1'
            )->fetchColumn();
            return (int) $n;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 接口是否归属该前台用户
     * - 直接：api.userid = 用户
     * - 历史：api.userid = 0，且该用户为当前唯一绑定身份（多绑定时无法判断历史归属，不计入）
     *
     * @param int $userId
     * @param int $apiUserId
     * @return bool
     */
    public static function userOwnsApi($userId, $apiUserId)
    {
        $userId = (int) $userId;
        $apiUserId = (int) $apiUserId;
        if ($userId <= 0) {
            return false;
        }
        if ($apiUserId === $userId) {
            return true;
        }
        if ($apiUserId === 0
            && self::isUserBoundToAdmin($userId)
            && self::activeBindUserCount() === 1) {
            return true;
        }
        return false;
    }

    /**
     * SQL：接口归属指定用户（含唯一绑定身份下的历史 userid=0）
     * 调用方需绑定两个相同的 userId 参数
     *
     * @param string $apiAlias
     * @return string
     */
    public static function sqlApiOwnedByUser($apiAlias = 'a')
    {
        $apiAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $apiAlias);
        if ($apiAlias === '') {
            $apiAlias = 'a';
        }
        $adminTable = Database::table('admin');
        return '(' . $apiAlias . '.`userid` = ? OR (' . $apiAlias . '.`userid` = 0 AND EXISTS ('
            . 'SELECT 1 FROM `' . $adminTable . '` ad'
            . ' WHERE ad.`binduid` = ? AND ad.`status` = 1 LIMIT 1'
            . ') AND (SELECT COUNT(DISTINCT ad2.`binduid`) FROM `' . $adminTable . '` ad2'
            . ' WHERE ad2.`binduid` IS NOT NULL AND ad2.`binduid` > 0 AND ad2.`status` = 1) = 1'
            . '))';
    }
}
