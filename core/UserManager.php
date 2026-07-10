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
                        `status`, `created_at`, `last_login_at`
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
}
