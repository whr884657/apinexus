<?php
/**
 * 文件：core/oauth/OAuthService.php
 * 作用：OAuth 聚合登录编排（仅已注册用户可绑定/登录）
 */

class OAuthService
{
    const BIND_SESSION_KEY = 'vs_oauth_bind_pending';

    /**
     * @param string $provider qq|gitee
     * @return string|null
     */
    public static function authorizeUrl($provider)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null || !OAuthConfig::isEnabled($provider)) {
            return null;
        }

        if ($provider === 'qq') {
            return QQOAuth::authorizeUrl();
        }

        return GiteeOAuth::authorizeUrl();
    }

    /**
     * @return array{qq: bool, gitee: bool}
     */
    public static function enabledProviders()
    {
        return array(
            'qq'    => OAuthConfig::isEnabled('qq'),
            'gitee' => OAuthConfig::isEnabled('gitee'),
        );
    }

    /**
     * @param string $provider
     * @param string $code
     * @param string $state
     * @return array{status: string, msg?: string, redirect?: string}
     */
    public static function handleCallback($provider, $code, $state)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return array('status' => 'error', 'msg' => '不支持的登录方式');
        }

        if (!OAuthState::verify($provider, $state)) {
            return array('status' => 'error', 'msg' => '授权状态无效或已过期，请重试');
        }

        $code = trim((string) $code);
        if ($code === '') {
            return array('status' => 'error', 'msg' => '授权失败，未获取到授权码');
        }

        $identity = self::fetchIdentity($provider, $code);
        if ($identity === null) {
            return array('status' => 'error', 'msg' => '获取第三方账号信息失败');
        }

        $user = self::findUserByIdentity($provider, $identity);
        if ($user !== null) {
            UserAuth::loginById((int) $user['id']);
            return array(
                'status'   => 'login',
                'redirect' => vs_base_url() . '/user/index.php',
            );
        }

        self::storeBindPending($provider, $identity);
        return array(
            'status'   => 'bind',
            'redirect' => vs_base_url() . '/user/oauth/bind.php',
        );
    }

    /**
     * @param string $username
     * @param string $password
     * @return array{ok: bool, msg: string}
     */
    public static function bindPendingToAccount($username, $password)
    {
        $pending = self::getBindPending();
        if ($pending === null) {
            return array('ok' => false, 'msg' => '绑定会话已过期，请重新发起第三方登录');
        }

        $username = trim((string) $username);
        $password = (string) $password;
        if ($username === '' || $password === '') {
            return array('ok' => false, 'msg' => '请输入账号和密码');
        }

        $loginBlocked = AuthSecurity::checkLoginAllowed($username);
        if ($loginBlocked !== null) {
            return array('ok' => false, 'msg' => $loginBlocked);
        }

        $user = UserAuth::verifyCredentials($username, $password);
        if ($user === null) {
            AuthSecurity::recordLoginFailure($username);
            return array('ok' => false, 'msg' => '用户名/邮箱或密码错误');
        }

        $bindResult = self::bindUser((int) $user['id'], $pending['provider'], $pending['identity']);
        if ($bindResult !== true) {
            return array('ok' => false, 'msg' => $bindResult);
        }

        self::clearBindPending();
        UserAuth::loginById((int) $user['id']);

        return array('ok' => true, 'msg' => '绑定成功，已登录');
    }

    /**
     * @param int    $userId
     * @param string $provider
     * @param array  $identity
     * @return true|string
     */
    public static function bindUser($userId, $provider, array $identity)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return '不支持的绑定方式';
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            return '用户不存在';
        }

        $existing = self::findUserByIdentity($provider, $identity);
        if ($existing !== null && (int) $existing['id'] !== $userId) {
            return '该第三方账号已绑定其他用户';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');

            if ($provider === 'qq') {
                $openid = trim((string) $identity['openid']);
                if ($openid === '') {
                    return 'QQ 账号信息无效';
                }
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `oauth_qq_openid` = ? WHERE `id` = ?');
                $stmt->execute(array($openid, $userId));
            } else {
                $gid = trim((string) $identity['id']);
                if ($gid === '') {
                    return 'Gitee 账号信息无效';
                }
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `oauth_gitee_id` = ? WHERE `id` = ?');
                $stmt->execute(array($gid, $userId));
            }

            return true;
        } catch (Exception $e) {
            return '绑定失败：' . $e->getMessage();
        }
    }

    /**
     * @param string $provider
     * @param array  $identity
     * @return array|null
     */
    public static function findUserByIdentity($provider, array $identity)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');

            if ($provider === 'qq') {
                $openid = trim((string) (isset($identity['openid']) ? $identity['openid'] : ''));
                if ($openid === '') {
                    return null;
                }
                $stmt = $pdo->prepare(
                    'SELECT `id`, `username`, `email`, `avatar_url`, `oauth_qq_openid`, `oauth_gitee_id`, `last_login_at`
                     FROM `' . $table . '` WHERE `oauth_qq_openid` = ? AND `status` = 1 LIMIT 1'
                );
                $stmt->execute(array($openid));
            } else {
                $gid = trim((string) (isset($identity['id']) ? $identity['id'] : ''));
                if ($gid === '') {
                    return null;
                }
                $stmt = $pdo->prepare(
                    'SELECT `id`, `username`, `email`, `avatar_url`, `oauth_qq_openid`, `oauth_gitee_id`, `last_login_at`
                     FROM `' . $table . '` WHERE `oauth_gitee_id` = ? AND `status` = 1 LIMIT 1'
                );
                $stmt->execute(array($gid));
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int $userId
     * @return array{qq: bool, gitee: bool}
     */
    public static function bindingsForUser($userId)
    {
        $userId = (int) $userId;
        $result = array('qq' => false, 'gitee' => false);
        if ($userId <= 0) {
            return $result;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `oauth_qq_openid`, `oauth_gitee_id` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $result['qq'] = trim((string) $row['oauth_qq_openid']) !== '';
                $result['gitee'] = trim((string) $row['oauth_gitee_id']) !== '';
            }
        } catch (Exception $e) {
            // ignore
        }

        return $result;
    }

    /**
     * @param string $provider
     * @param string $code
     * @return array|null
     */
    private static function fetchIdentity($provider, $code)
    {
        if ($provider === 'qq') {
            return QQOAuth::fetchIdentity($code);
        }
        return GiteeOAuth::fetchIdentity($code);
    }

    /**
     * @param string $provider
     * @param array  $identity
     * @return void
     */
    private static function storeBindPending($provider, array $identity)
    {
        $_SESSION[self::BIND_SESSION_KEY] = array(
            'provider' => $provider,
            'identity' => $identity,
            'expires'  => time() + 600,
        );
    }

    /**
     * @return array|null
     */
    public static function getBindPending()
    {
        if (!isset($_SESSION[self::BIND_SESSION_KEY]) || !is_array($_SESSION[self::BIND_SESSION_KEY])) {
            return null;
        }

        $pending = $_SESSION[self::BIND_SESSION_KEY];
        if (empty($pending['expires']) || (int) $pending['expires'] < time()) {
            self::clearBindPending();
            return null;
        }

        return $pending;
    }

    /**
     * @return void
     */
    public static function clearBindPending()
    {
        unset($_SESSION[self::BIND_SESSION_KEY]);
    }

    /**
     * @param string|null $provider
     * @return string|null
     */
    private static function normalizeProvider($provider)
    {
        $provider = strtolower(trim((string) $provider));
        if ($provider === 'qq' || $provider === 'gitee') {
            return $provider;
        }
        return null;
    }
}
