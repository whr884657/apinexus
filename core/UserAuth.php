<?php
/**
 * 文件：core/UserAuth.php
 * 作用：用户认证、登录态管理、注册与密码重置
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class UserAuth
{
    const SESSION_KEY = 'vs_user_id';
    const ACTIVITY_KEY = 'vs_user_last_activity';

    /**
     * 用户登录（支持用户名或邮箱）
     *
     * @param string $account
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
            $table = Database::table('user');
            $hash = vs_password_hash($password);

            $stmt = $pdo->prepare(
                'SELECT * FROM `' . $table . '` WHERE (`username` = ? OR `email` = ?) AND `password` = ? AND `status` = 1 LIMIT 1'
            );
            $stmt->execute(array($account, $account, $hash));
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION[self::SESSION_KEY] = (int) $user['id'];
                $_SESSION['vs_user_username'] = $user['username'];
                self::touchActivity();

                $upd = $pdo->prepare('UPDATE `' . $table . '` SET `lastlogin` = NOW() WHERE `id` = ?');
                $upd->execute(array((int) $user['id']));

                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                return $user;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * 退出登录（仅清除用户会话，不影响管理员）
     *
     * @return void
     */
    public static function logout()
    {
        unset(
            $_SESSION[self::SESSION_KEY],
            $_SESSION['vs_user_username'],
            $_SESSION[self::ACTIVITY_KEY]
        );
    }

    /**
     * @return void
     */
    public static function touchActivity()
    {
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    /**
     * @return bool
     */
    public static function isSessionExpired()
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return true;
        }

        if (empty($_SESSION[self::ACTIVITY_KEY])) {
            return false;
        }

        $timeout = Config::sessionTimeout();
        return (time() - (int) $_SESSION[self::ACTIVITY_KEY]) > $timeout;
    }

    /**
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
     * @return int
     */
    public static function id()
    {
        return isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : 0;
    }

    /**
     * @return void
     */
    public static function requireLogin()
    {
        if (!empty($_SESSION[self::SESSION_KEY]) && self::isSessionExpired()) {
            self::logout();
            vs_redirect(vs_base_url() . '/user/login.php?expired=1');
        }

        if (!self::check()) {
            vs_redirect(vs_base_url() . '/user/login.php');
        }
    }

    /**
     * @return void
     */
    public static function redirectIfLoggedIn()
    {
        if (self::check()) {
            vs_redirect(vs_base_url() . '/user/index.php');
            return;
        }

        // OAuth 回调后可能仅有会话键尚未写入活动时间，尝试恢复后跳转
        if (!empty($_SESSION[self::SESSION_KEY])) {
            self::touchActivity();
            if (self::check()) {
                vs_redirect(vs_base_url() . '/user/index.php');
            }
        }
    }

    /**
     * @return array|null
     */
    public static function user()
    {
        if (!self::check()) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `role`, `createtime`, `lastlogin` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array(self::id()));
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 校验账号密码并返回用户行（不写入会话）
     *
     * @param string $account
     * @param string $password
     * @return array|null
     */
    public static function verifyCredentials($account, $password)
    {
        $account = trim((string) $account);
        if ($account === '') {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $hash = vs_password_hash($password);
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . $table . '` WHERE (`username` = ? OR `email` = ?) AND `password` = ? AND `status` = 1 LIMIT 1'
            );
            $stmt->execute(array($account, $account, $hash));
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 凭据正确但账号已封禁
     *
     * @param string $account
     * @param string $password
     * @return bool
     */
    public static function isBannedAccount($account, $password)
    {
        $account = trim((string) $account);
        if ($account === '') {
            return false;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $hash = vs_password_hash($password);
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '`
                 WHERE (`username` = ? OR `email` = ?) AND `password` = ? AND `status` = 0 LIMIT 1'
            );
            $stmt->execute(array($account, $account, $hash));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 按用户 ID 登录（OAuth 等场景）
     *
     * @param int $userId
     * @return bool
     */
    public static function loginById($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `id` = ? AND `status` = 1 LIMIT 1');
            $stmt->execute(array($userId));
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return false;
            }

            $_SESSION[self::SESSION_KEY] = $userId;
            $_SESSION['vs_user_username'] = $user['username'];
            self::touchActivity();

            $upd = $pdo->prepare('UPDATE `' . $table . '` SET `lastlogin` = NOW() WHERE `id` = ?');
            $upd->execute(array($userId));

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 注册新用户
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string $role user|developer，默认普通用户
     * @return true|string
     */
    public static function register($username, $email, $password, $role = UserRole::ROLE_USER)
    {
        $username = trim((string) $username);
        $email = trim((string) $email);
        $role = UserRole::normalize($role);

        if ($username === '') {
            return '用户名不能为空';
        }
        if (strlen($username) < 3) {
            return '用户名至少 3 个字符';
        }
        if (strlen($username) > 50) {
            return '用户名不能超过 50 个字符';
        }
        if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
            return '用户名仅支持中文、字母、数字和下划线';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }
        $suffixMsg = RegisterPolicy::validateEmailSuffix($email);
        if ($suffixMsg !== null) {
            return $suffixMsg;
        }
        if (strlen($password) < 6) {
            return '密码至少 6 位';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');

            $stmt = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `username` = ? LIMIT 1');
            $stmt->execute(array($username));
            if ($stmt->fetch()) {
                return '用户名已被占用';
            }

            $stmt = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `email` = ? LIMIT 1');
            $stmt->execute(array($email));
            if ($stmt->fetch()) {
                return '该邮箱已注册';
            }

            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '` (`username`, `password`, `email`, `status`, `role`, `createtime`) VALUES (?, ?, ?, 1, ?, NOW())'
            );
            $stmt->execute(array($username, vs_password_hash($password), $email, $role));

            return true;
        } catch (Exception $e) {
            return '注册失败：' . $e->getMessage();
        }
    }

    /**
     * 通过用户 ID 重置密码
     *
     * @param int    $userId
     * @param string $newPassword
     * @return bool
     */
    public static function resetPasswordById($userId, $newPassword)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || strlen($newPassword) < 6) {
            return false;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `password` = ? WHERE `id` = ? AND `status` = 1');
            $stmt->execute(array(vs_password_hash($newPassword), $userId));

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 按邮箱查找活跃用户
     *
     * @param string $email
     * @return array|null
     */
    public static function findByEmail($email)
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('SELECT `id`, `username`, `email` FROM `' . $table . '` WHERE `email` = ? AND `status` = 1 LIMIT 1');
            $stmt->execute(array($email));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 用户名或邮箱是否已存在
     *
     * @param string $username
     * @param string $email
     * @return string|null 错误信息
     */
    public static function checkRegisterDuplicate($username, $email)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('user');

            $stmt = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `username` = ? LIMIT 1');
            $stmt->execute(array(trim($username)));
            if ($stmt->fetch()) {
                return '用户名已被占用';
            }

            $stmt = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `email` = ? LIMIT 1');
            $stmt->execute(array(trim($email)));
            if ($stmt->fetch()) {
                return '该邮箱已注册';
            }
        } catch (Exception $e) {
            return '系统繁忙，请稍后再试';
        }

        return null;
    }

    /**
     * 更新当前用户账号（用户名、邮箱、头像、密码）
     *
     * @param string      $email
     * @param string|null $newPassword
     * @param string|null $oldPassword
     * @param string|null $avatarUrl
     * @param string|null $username
     * @return true|string
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
            if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
                return '用户名仅支持中文、字母、数字和下划线';
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
                $table = Database::table('user');
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
            $table = Database::table('user');

            if ($username !== null) {
                $check = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `username` = ? AND `id` != ? LIMIT 1');
                $check->execute(array($username, self::id()));
                if ($check->fetch()) {
                    return '用户名已被占用';
                }
            }

            $checkEmail = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `email` = ? AND `id` != ? LIMIT 1');
            $checkEmail->execute(array($email, self::id()));
            if ($checkEmail->fetch()) {
                return '该邮箱已被其他账号使用';
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

            if ($username !== null && isset($_SESSION['vs_user_username'])) {
                $_SESSION['vs_user_username'] = $username;
            }

            return true;
        } catch (Exception $e) {
            return '保存失败：' . $e->getMessage();
        }
    }
}
