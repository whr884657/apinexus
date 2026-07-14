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
    public static function authorizeUrl($provider, array $context = array())
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null || !OAuthConfig::isEnabled($provider)) {
            return null;
        }

        if ($provider === 'qq') {
            return QQOAuth::authorizeUrl($context);
        }

        return GiteeOAuth::authorizeUrl($context);
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

        $rateMsg = AuthSecurity::checkOAuthCallbackAllowed();
        if ($rateMsg !== null) {
            return array('status' => 'error', 'msg' => $rateMsg);
        }
        AuthSecurity::recordOAuthCallback();

        $stateData = OAuthState::consume($provider, $state);
        if ($stateData === false) {
            return array('status' => 'error', 'msg' => '授权状态无效或已过期，请重试');
        }

        $code = trim((string) $code);
        if ($code === '') {
            return array('status' => 'error', 'msg' => '授权失败，未获取到授权码');
        }

        if (self::isAuthorizationCodeUsed($code)) {
            return array('status' => 'error', 'msg' => '授权码已失效，请重新发起登录');
        }
        self::markAuthorizationCodeUsed($code);

        $identity = self::fetchIdentity($provider, $code);
        if ($identity === null) {
            return array('status' => 'error', 'msg' => '获取第三方账号信息失败');
        }

        $intent = isset($stateData['intent']) ? $stateData['intent'] : 'login';
        $bindUserId = isset($stateData['user_id']) ? (int) $stateData['user_id'] : 0;

        if ($intent === 'bind' && $bindUserId > 0) {
            if (UserAuth::check() && UserAuth::id() !== $bindUserId) {
                return array(
                    'status'   => 'error',
                    'msg'      => '绑定会话无效，请重新登录后再试',
                    'redirect' => vs_base_url() . '/user/account.php?oauth_error=' . rawurlencode('绑定会话无效，请重新登录后再试'),
                );
            }

            $accountUrl = vs_base_url() . '/user/account.php';

            $currentBindings = self::bindingsForUser($bindUserId);
            if (!empty($currentBindings[$provider])) {
                self::restoreBindUserSession($bindUserId);
                return array(
                    'status'   => 'done',
                    'redirect' => $accountUrl . '?oauth_error=' . rawurlencode('该账号已绑定此第三方，无需重复操作'),
                );
            }

            $bindResult = self::bindUser($bindUserId, $provider, $identity);
            if ($bindResult !== true) {
                self::restoreBindUserSession($bindUserId);
                return array(
                    'status'   => 'done',
                    'redirect' => $accountUrl . '?oauth_error=' . rawurlencode($bindResult),
                );
            }

            self::restoreBindUserSession($bindUserId);
            return array(
                'status'   => 'done',
                'redirect' => $accountUrl . '?oauth_success=' . rawurlencode('第三方账号绑定成功'),
            );
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
     * @param int $userId
     * @return void
     */
    private static function restoreBindUserSession($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        if (!UserAuth::check() || UserAuth::id() !== $userId) {
            UserAuth::loginById($userId);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
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
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `qqopenid` = ? WHERE `id` = ?');
                $stmt->execute(array($openid, $userId));
            } else {
                $gid = trim((string) $identity['id']);
                if ($gid === '') {
                    return 'Gitee 账号信息无效';
                }
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `giteeid` = ? WHERE `id` = ?');
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
                    'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `lastlogin`
                     FROM `' . $table . '` WHERE `qqopenid` = ? AND `status` = 1 LIMIT 1'
                );
                $stmt->execute(array($openid));
            } else {
                $gid = trim((string) (isset($identity['id']) ? $identity['id'] : ''));
                if ($gid === '') {
                    return null;
                }
                $stmt = $pdo->prepare(
                    'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `lastlogin`
                     FROM `' . $table . '` WHERE `giteeid` = ? AND `status` = 1 LIMIT 1'
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
                'SELECT `qqopenid`, `giteeid` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $result['qq'] = trim((string) $row['qqopenid']) !== '';
                $result['gitee'] = trim((string) $row['giteeid']) !== '';
            }
        } catch (Exception $e) {
            // ignore
        }

        return $result;
    }

    /**
     * @param int    $userId
     * @param string $provider
     * @return true|string
     */
    public static function unbindUser($userId, $provider)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return '不支持的解绑方式';
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            return '用户不存在';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $field = $provider === 'qq' ? 'qqopenid' : 'giteeid';
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `' . $field . '` = ? WHERE `id` = ?');
            $stmt->execute(array('', $userId));
            return true;
        } catch (Exception $e) {
            return '解绑失败：' . $e->getMessage();
        }
    }

    /**
     * @param string $provider
     * @param int    $userId
     * @return string|null
     */
    public static function validateBindStart($provider, $userId)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null || !OAuthConfig::isEnabled($provider)) {
            return '该登录方式未启用或配置不完整';
        }

        $bindings = self::bindingsForUser($userId);
        if (!empty($bindings[$provider])) {
            return '您已绑定该第三方账号';
        }

        return null;
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
            'provider'      => $provider,
            'identity'      => $identity,
            'identity_hash' => self::identityHash($provider, $identity),
            'expires'       => time() + 600,
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

        if (
            empty($pending['identity_hash'])
            || empty($pending['provider'])
            || empty($pending['identity'])
            || !hash_equals(
                (string) $pending['identity_hash'],
                self::identityHash($pending['provider'], $pending['identity'])
            )
        ) {
            self::clearBindPending();
            return null;
        }

        return $pending;
    }

    /**
     * @param string $code
     * @return bool
     */
    private static function isAuthorizationCodeUsed($code)
    {
        if (!isset($_SESSION['vs_oauth_used_codes']) || !is_array($_SESSION['vs_oauth_used_codes'])) {
            return false;
        }
        $hash = hash('sha256', (string) $code);
        return in_array($hash, $_SESSION['vs_oauth_used_codes'], true);
    }

    /**
     * @param string $code
     * @return void
     */
    private static function markAuthorizationCodeUsed($code)
    {
        if (!isset($_SESSION['vs_oauth_used_codes']) || !is_array($_SESSION['vs_oauth_used_codes'])) {
            $_SESSION['vs_oauth_used_codes'] = array();
        }
        $_SESSION['vs_oauth_used_codes'][] = hash('sha256', (string) $code);
        if (count($_SESSION['vs_oauth_used_codes']) > 20) {
            $_SESSION['vs_oauth_used_codes'] = array_slice($_SESSION['vs_oauth_used_codes'], -20);
        }
    }

    /**
     * @param string $provider
     * @param array  $identity
     * @return string
     */
    private static function identityHash($provider, array $identity)
    {
        if ($provider === 'qq') {
            return hash('sha256', 'qq:' . (isset($identity['openid']) ? $identity['openid'] : ''));
        }
        return hash('sha256', 'gitee:' . (isset($identity['id']) ? $identity['id'] : ''));
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
