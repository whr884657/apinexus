<?php
/**
 * 文件：core/Auth.php
 * 作用：管理员认证、登录态管理、会话超时
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class Auth
{
    const SESSION_KEY = 'vs_admin_id';
    const ACTIVITY_KEY = 'vs_last_activity';

    /**
     * 管理员登录（支持用户名或邮箱）
     *
     * @param string $account 用户名或邮箱
     * @param string $password
     * @return array|false
     */
    public static function login($account, $password)
    {
        $account = trim((string) $account);
        if ($account === '') {
            return false;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('admin');
            $hash = vs_password_hash($password);

            $stmt = $pdo->prepare(
                'SELECT * FROM `' . $table . '` WHERE (`username` = ? OR `email` = ?) AND `password` = ? AND `status` = 1 LIMIT 1'
            );
            $stmt->execute(array($account, $account, $hash));
            $admin = $stmt->fetch();

            if ($admin) {
                $_SESSION[self::SESSION_KEY] = (int) $admin['id'];
                $_SESSION['vs_admin_username'] = $admin['username'];
                self::touchActivity();
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                return $admin;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * 退出登录
     *
     * @return void
     */
    public static function logout()
    {
        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * 更新最后活动时间
     *
     * @return void
     */
    public static function touchActivity()
    {
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    /**
     * 是否会话超时
     *
     * @return bool
     */
    public static function isSessionExpired()
    {
        if (empty($_SESSION[self::ACTIVITY_KEY])) {
            return true;
        }

        $timeout = Config::sessionTimeout();
        return (time() - (int) $_SESSION[self::ACTIVITY_KEY]) > $timeout;
    }

    /**
     * 是否已登录（含超时检测）
     *
     * @return bool
     */
    public static function check()
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        if (self::isSessionExpired()) {
            self::logout();
            return false;
        }

        self::touchActivity();
        return true;
    }

    /**
     * 获取当前管理员 ID
     *
     * @return int
     */
    public static function id()
    {
        return isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : 0;
    }

    /**
     * 要求登录，未登录或超时跳转 login.php
     *
     * @return void
     */
    public static function requireLogin()
    {
        if (!empty($_SESSION[self::SESSION_KEY]) && self::isSessionExpired()) {
            self::logout();
            vs_redirect(vs_base_url() . '/admin/login.php?expired=1');
        }

        if (!self::check()) {
            vs_redirect(vs_base_url() . '/admin/login.php');
        }
    }

    /**
     * 已登录时跳转后台首页
     *
     * @return void
     */
    public static function redirectIfLoggedIn()
    {
        if (self::check()) {
            vs_redirect(vs_base_url() . '/admin/index.php');
        }
    }

    /**
     * 获取当前管理员信息
     *
     * @return array|null
     */
    public static function user()
    {
        if (!self::check()) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('admin');
            $stmt = $pdo->prepare('SELECT `id`, `username`, `email`, `avatar`, `binduid`, `createtime` FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array(self::id()));
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 更新当前账号（邮箱 / 密码）
     *
     * @param string      $email
     * @param string|null $newPassword
     * @param string|null $oldPassword
     * @param string|null $avatarUrl
     * @param string|null $username
     * @return true|string true 成功，string 为错误信息
     */
    public static function updateAccount($email, $newPassword = null, $oldPassword = null, $avatarUrl = null, $username = null)
    {
        if (!self::check()) {
            return '请先登录';
        }

        if ($username !== null) {
            $username = trim((string) $username);
            if ($username === '') {
                return '用户名不能为空';
            }
            if (strlen($username) < 3) {
                return '用户名至少 3 个字符';
            }
            if (strlen($username) > 50) {
                return '用户名不能超过 50 个字符';
            }
        }

        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }

        $avatarUrl = $avatarUrl === null ? null : trim((string) $avatarUrl);
        if ($avatarUrl !== null && $avatarUrl !== '' && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            return '头像链接格式不正确';
        }

        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 6) {
                return '新密码至少 6 个字符';
            }
            if ($oldPassword === null || $oldPassword === '') {
                return '修改密码需输入当前密码';
            }

            try {
                $pdo = Database::connect();
                $table = Database::table('admin');
                $stmt = $pdo->prepare('SELECT `password` FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
                $stmt->execute(array(self::id()));
                $row = $stmt->fetch();
                if (!$row || $row['password'] !== vs_password_hash($oldPassword)) {
                    return '当前密码不正确';
                }
            } catch (Exception $e) {
                return '验证失败，请稍后再试';
            }
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('admin');

            if ($username !== null) {
                $check = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `username` = ? AND `id` != ? LIMIT 1');
                $check->execute(array($username, self::id()));
                if ($check->fetch()) {
                    return '用户名已被占用';
                }
            }

            $savedAvatar = $avatarUrl !== null ? $avatarUrl : '';

            if ($newPassword !== null && $newPassword !== '') {
                if ($username !== null) {
                    $stmt = $pdo->prepare(
                        'UPDATE `' . $table . '` SET `username` = ?, `email` = ?, `avatar` = ?, `password` = ? WHERE `id` = ?'
                    );
                    $stmt->execute(array($username, $email, $savedAvatar, vs_password_hash($newPassword), self::id()));
                } else {
                    $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `email` = ?, `avatar` = ?, `password` = ? WHERE `id` = ?');
                    $stmt->execute(array($email, $savedAvatar, vs_password_hash($newPassword), self::id()));
                }
            } elseif ($username !== null) {
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `username` = ?, `email` = ?, `avatar` = ? WHERE `id` = ?');
                $stmt->execute(array($username, $email, $savedAvatar, self::id()));
            } else {
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `email` = ?, `avatar` = ? WHERE `id` = ?');
                $stmt->execute(array($email, $savedAvatar, self::id()));
            }

            if ($username !== null && isset($_SESSION['vs_admin_username'])) {
                $_SESSION['vs_admin_username'] = $username;
            }

            return true;
        } catch (Exception $e) {
            return '保存失败：' . $e->getMessage();
        }
    }

    /**
     * 通过管理员 ID 重置密码（验证码流程）
     *
     * @param int    $adminId
     * @param string $newPassword
     * @return bool
     */
    public static function resetPasswordById($adminId, $newPassword)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0 || strlen($newPassword) < 6) {
            return false;
        }

        try {
            $pdo = Database::connect();
            $adminTable = Database::table('admin');

            $stmt = $pdo->prepare('UPDATE `' . $adminTable . '` SET `password` = ? WHERE `id` = ? AND `status` = 1');
            $stmt->execute(array(vs_password_hash($newPassword), $adminId));

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
