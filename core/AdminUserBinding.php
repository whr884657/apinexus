<?php
/**
 * 文件：core/AdminUserBinding.php
 * 作用：管理员账号与用户账号绑定（后台发布内容身份）
 */

class AdminUserBinding
{
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
}
