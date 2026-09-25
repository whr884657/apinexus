<?php
/**
 * 文件：core/oauth/OAuthService.php
 * 作用：OAuth 登录编排（QQ / Gitee / 聚合 agg；仅已注册用户可绑定/登录）
 */

class OAuthService
{
    const BIND_SESSION_KEY = 'vs_oauth_bind_pending';

    /**
     * @param string $provider qq|gitee|agg
     * @param array  $context intent / user_id / item_id(agg)
     * @return string|null
     */
    public static function authorizeUrl($provider, array $context = array())
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return null;
        }

        if ($provider === 'agg') {
            $itemId = isset($context['item_id']) ? trim((string) $context['item_id']) : '';
            if ($itemId === '' || !OAuthConfig::isAggItemEnabled($itemId)) {
                return null;
            }
            return AggOAuth::authorizeUrl($itemId, $context);
        }

        if (!OAuthConfig::isEnabled($provider)) {
            return null;
        }

        if ($provider === 'qq') {
            return QQOAuth::authorizeUrl($context);
        }

        return GiteeOAuth::authorizeUrl($context);
    }

    /**
     * 兼容旧主题：是否启用官方 QQ / Gitee
     *
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
     * 登录页按钮列表（官方 + 已开启的聚合方式）
     *
     * @return array<int, array{key: string, provider: string, item_id: string, label: string, icon: string, url: string}>
     */
    public static function loginButtons()
    {
        $base = vs_base_url();
        $out = array();

        if (OAuthConfig::isEnabled('qq')) {
            $out[] = array(
                'key'      => 'qq',
                'provider' => 'qq',
                'item_id'  => '',
                'label'    => 'QQ',
                'icon'     => OAuthConfig::resolveIconUrl('oauth/qq.svg'),
                'url'      => $base . '/user/oauth/start?provider=qq',
            );
        }
        if (OAuthConfig::isEnabled('gitee')) {
            $out[] = array(
                'key'      => 'gitee',
                'provider' => 'gitee',
                'item_id'  => '',
                'label'    => 'Gitee',
                'icon'     => OAuthConfig::resolveIconUrl('oauth/gitee.svg'),
                'url'      => $base . '/user/oauth/start?provider=gitee',
            );
        }

        if (OAuthConfig::isEnabled('agg')) {
            foreach (OAuthConfig::aggItemsOn() as $item) {
                $id = (string) $item['id'];
                $out[] = array(
                    'key'      => 'agg:' . $id,
                    'provider' => 'agg',
                    'item_id'  => $id,
                    'label'    => (string) $item['name'],
                    'icon'     => OAuthConfig::resolveIconUrl(isset($item['icon']) ? $item['icon'] : ''),
                    'url'      => $base . '/user/oauth/start?provider=agg&id=' . rawurlencode($id),
                );
            }
        }

        return $out;
    }

    /**
     * 账号页绑定列表
     *
     * @param int $userId
     * @return array<int, array{key: string, provider: string, item_id: string, label: string, icon: string, bound: bool, bind_url: string}>
     */
    public static function accountButtons($userId)
    {
        $userId = (int) $userId;
        $bindings = self::bindingsForUser($userId);
        $base = vs_base_url();
        $out = array();

        if (OAuthConfig::isEnabled('qq')) {
            $out[] = array(
                'key'      => 'qq',
                'provider' => 'qq',
                'item_id'  => '',
                'label'    => 'QQ',
                'icon'     => OAuthConfig::resolveIconUrl('oauth/qq.svg'),
                'bound'    => !empty($bindings['qq']),
                'bind_url' => $base . '/user/oauth/start?provider=qq&intent=bind',
            );
        }
        if (OAuthConfig::isEnabled('gitee')) {
            $out[] = array(
                'key'      => 'gitee',
                'provider' => 'gitee',
                'item_id'  => '',
                'label'    => 'Gitee',
                'icon'     => OAuthConfig::resolveIconUrl('oauth/gitee.svg'),
                'bound'    => !empty($bindings['gitee']),
                'bind_url' => $base . '/user/oauth/start?provider=gitee&intent=bind',
            );
        }

        if (OAuthConfig::isEnabled('agg')) {
            $aggBound = isset($bindings['agg']) && is_array($bindings['agg']) ? $bindings['agg'] : array();
            foreach (OAuthConfig::aggItemsOn() as $item) {
                $id = (string) $item['id'];
                $out[] = array(
                    'key'      => 'agg:' . $id,
                    'provider' => 'agg',
                    'item_id'  => $id,
                    'label'    => (string) $item['name'],
                    'icon'     => OAuthConfig::resolveIconUrl(isset($item['icon']) ? $item['icon'] : ''),
                    'bound'    => !empty($aggBound[$id]),
                    'bind_url' => $base . '/user/oauth/start?provider=agg&id=' . rawurlencode($id) . '&intent=bind',
                );
            }
        }

        return $out;
    }

    /**
     * @param string $provider
     * @param string $code
     * @param string $state
     * @param string $aggType 聚合回调 GET type（网关原样带回）
     * @return array{status: string, msg?: string, redirect?: string}
     */
    public static function handleCallback($provider, $code, $state, $aggType = '')
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

        $itemId = isset($stateData['item_id']) ? (string) $stateData['item_id'] : '';
        $aggType = trim((string) $aggType);
        $identity = self::fetchIdentity($provider, $code, $itemId, $aggType);
        if ($identity === null) {
            return array('status' => 'error', 'msg' => '获取第三方账号信息失败');
        }

        $intent = isset($stateData['intent']) ? $stateData['intent'] : 'login';
        $bindUserId = isset($stateData['user_id']) ? (int) $stateData['user_id'] : 0;

        if ($intent === 'bind' && $bindUserId > 0) {
            if (UserAuth::check() && UserAuth::id() !== $bindUserId) {
                vs_flash_set('error', '绑定会话无效，请重新登录后再试');
                return array(
                    'status'   => 'error',
                    'msg'      => '绑定会话无效，请重新登录后再试',
                    'redirect' => vs_base_url() . '/user/account.php',
                );
            }

            $accountUrl = vs_base_url() . '/user/account.php';

            if (self::userHasBinding($bindUserId, $provider, $itemId)) {
                self::restoreBindUserSession($bindUserId);
                vs_flash_set('error', '该账号已绑定此第三方，无需重复操作');
                return array(
                    'status'   => 'done',
                    'redirect' => $accountUrl,
                );
            }

            $bindResult = self::bindUser($bindUserId, $provider, $identity);
            if ($bindResult !== true) {
                self::restoreBindUserSession($bindUserId);
                vs_flash_set('error', (string) $bindResult);
                return array(
                    'status'   => 'done',
                    'redirect' => $accountUrl,
                );
            }

            self::restoreBindUserSession($bindUserId);
            vs_flash_set('success', '第三方账号绑定成功');
            return array(
                'status'   => 'done',
                'redirect' => $accountUrl,
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
                return true;
            }

            if ($provider === 'gitee') {
                $gid = trim((string) $identity['id']);
                if ($gid === '') {
                    return 'Gitee 账号信息无效';
                }
                $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `giteeid` = ? WHERE `id` = ?');
                $stmt->execute(array($gid, $userId));
                return true;
            }

            $itemId = isset($identity['item_id']) ? trim((string) $identity['item_id']) : '';
            $socialUid = isset($identity['social_uid']) ? trim((string) $identity['social_uid']) : '';
            if ($itemId === '' || $socialUid === '' || !OAuthConfig::isValidAggItemId($itemId)) {
                return '聚合登录账号信息无效';
            }

            $map = self::loadAggmap($pdo, $table, $userId);
            $map[$itemId] = $socialUid;
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `aggmap` = ? WHERE `id` = ?');
            $stmt->execute(array(json_encode($map, JSON_UNESCAPED_UNICODE), $userId));
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
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            }

            if ($provider === 'gitee') {
                $gid = trim((string) (isset($identity['id']) ? $identity['id'] : ''));
                if ($gid === '') {
                    return null;
                }
                $stmt = $pdo->prepare(
                    'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `lastlogin`
                     FROM `' . $table . '` WHERE `giteeid` = ? AND `status` = 1 LIMIT 1'
                );
                $stmt->execute(array($gid));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            }

            $itemId = isset($identity['item_id']) ? trim((string) $identity['item_id']) : '';
            $socialUid = isset($identity['social_uid']) ? trim((string) $identity['social_uid']) : '';
            if ($itemId === '' || $socialUid === '' || !OAuthConfig::isValidAggItemId($itemId)) {
                return null;
            }

            $path = '$.' . $itemId;
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `lastlogin`, `aggmap`
                 FROM `' . $table . '`
                 WHERE `status` = 1 AND `aggmap` <> \'\'
                   AND JSON_UNQUOTE(JSON_EXTRACT(`aggmap`, ?)) = ?
                 LIMIT 1'
            );
            $stmt->execute(array($path, $socialUid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }

            // 兼容无 JSON 函数的环境：扫描少量带 aggmap 的用户
            $stmt = $pdo->query(
                'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `lastlogin`, `aggmap`
                 FROM `' . $table . '` WHERE `status` = 1 AND `aggmap` <> \'\' LIMIT 500'
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $map = self::decodeAggmap(isset($row['aggmap']) ? $row['aggmap'] : '');
                if (isset($map[$itemId]) && (string) $map[$itemId] === $socialUid) {
                    return $row;
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int $userId
     * @return array{qq: bool, gitee: bool, agg: array<string, bool>}
     */
    public static function bindingsForUser($userId)
    {
        $userId = (int) $userId;
        $result = array('qq' => false, 'gitee' => false, 'agg' => array());
        if ($userId <= 0) {
            return $result;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `qqopenid`, `giteeid`, `aggmap` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $result['qq'] = trim((string) $row['qqopenid']) !== '';
                $result['gitee'] = trim((string) $row['giteeid']) !== '';
                $map = self::decodeAggmap(isset($row['aggmap']) ? $row['aggmap'] : '');
                foreach ($map as $k => $v) {
                    if (trim((string) $v) !== '') {
                        $result['agg'][(string) $k] = true;
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }

        return $result;
    }

    /**
     * 绑定页/提示用的平台展示名（qq / gitee / 聚合条目名）
     *
     * @param string $provider
     * @param array  $identity
     * @return string
     */
    public static function providerDisplayLabel($provider, array $identity = array())
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === 'qq') {
            return 'QQ';
        }
        if ($provider === 'gitee') {
            return 'Gitee';
        }
        if ($provider === 'agg') {
            $itemId = isset($identity['item_id']) ? trim((string) $identity['item_id']) : '';
            $item = $itemId !== '' ? OAuthConfig::aggItem($itemId) : null;
            if ($item !== null && isset($item['name']) && trim((string) $item['name']) !== '') {
                return trim((string) $item['name']);
            }
            return '第三方';
        }
        return '第三方';
    }

    /**
     * 绑定页展示用的第三方昵称
     *
     * @param string $provider
     * @param array  $identity
     * @return string
     */
    public static function identityDisplayName($provider, array $identity)
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === 'qq' || $provider === 'agg') {
            return isset($identity['nickname']) ? trim((string) $identity['nickname']) : '';
        }
        if ($provider === 'gitee') {
            $name = isset($identity['name']) ? trim((string) $identity['name']) : '';
            if ($name === '' && isset($identity['login'])) {
                $name = trim((string) $identity['login']);
            }
            return $name;
        }
        return '';
    }

    /**
     * @param int    $userId
     * @param string $provider
     * @param string $itemId agg 时必填
     * @return true|string
     */
    public static function unbindUser($userId, $provider, $itemId = '')
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

            if ($provider === 'qq' || $provider === 'gitee') {
                $field = $provider === 'qq' ? 'qqopenid' : 'giteeid';
                $stmt = $pdo->prepare(
                    'UPDATE `' . $table . '` SET `' . $field . '` = NULL WHERE `id` = ?'
                );
                $stmt->execute(array($userId));
                // 再读校验：确保库内已清空（兼容历史空串）
                $check = $pdo->prepare(
                    'SELECT `' . $field . '` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
                );
                $check->execute(array($userId));
                $row = $check->fetch(PDO::FETCH_ASSOC);
                $left = $row && isset($row[$field]) ? trim((string) $row[$field]) : '';
                if ($left !== '') {
                    $stmt2 = $pdo->prepare(
                        'UPDATE `' . $table . '` SET `' . $field . '` = ? WHERE `id` = ?'
                    );
                    $stmt2->execute(array('', $userId));
                    $check->execute(array($userId));
                    $row = $check->fetch(PDO::FETCH_ASSOC);
                    $left = $row && isset($row[$field]) ? trim((string) $row[$field]) : '';
                    if ($left !== '') {
                        return '解绑失败：未能清除第三方绑定信息';
                    }
                }
                self::clearBindPending();
                return true;
            }

            $itemId = trim((string) $itemId);
            if ($itemId === '' || !OAuthConfig::isValidAggItemId($itemId)) {
                return '请指定要解绑的登录方式';
            }
            $map = self::loadAggmap($pdo, $table, $userId);
            // 兼容 JSON 数字键与字符串键
            unset($map[$itemId]);
            if (ctype_digit($itemId)) {
                unset($map[(string) ((int) $itemId)]);
            }
            $json = empty($map) ? null : json_encode($map, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return '解绑失败：绑定数据异常';
            }
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `aggmap` = ? WHERE `id` = ?');
            $stmt->execute(array($json === null ? '' : $json, $userId));

            $leftMap = self::loadAggmap($pdo, $table, $userId);
            if (isset($leftMap[$itemId]) && trim((string) $leftMap[$itemId]) !== '') {
                return '解绑失败：未能清除第三方绑定信息';
            }
            self::clearBindPending();
            return true;
        } catch (Exception $e) {
            return '解绑失败：' . $e->getMessage();
        }
    }

    /**
     * @param string $provider
     * @param int    $userId
     * @param string $itemId
     * @return string|null
     */
    public static function validateBindStart($provider, $userId, $itemId = '')
    {
        $provider = self::normalizeProvider($provider);
        if ($provider === null) {
            return '该登录方式未启用或配置不完整';
        }

        if ($provider === 'agg') {
            $itemId = trim((string) $itemId);
            if ($itemId === '' || !OAuthConfig::isAggItemEnabled($itemId)) {
                return '该登录方式未启用或配置不完整';
            }
        } elseif (!OAuthConfig::isEnabled($provider)) {
            return '该登录方式未启用或配置不完整';
        }

        if (self::userHasBinding($userId, $provider, $itemId)) {
            return '您已绑定该第三方账号';
        }

        return null;
    }

    /**
     * @param int    $userId
     * @param string $provider
     * @param string $itemId
     * @return bool
     */
    private static function userHasBinding($userId, $provider, $itemId = '')
    {
        $bindings = self::bindingsForUser($userId);
        if ($provider === 'qq' || $provider === 'gitee') {
            return !empty($bindings[$provider]);
        }
        $itemId = trim((string) $itemId);
        return $itemId !== '' && !empty($bindings['agg'][$itemId]);
    }

    /**
     * @param string $provider
     * @param string $code
     * @param string $itemId
     * @param string $aggType 回调带回的 type（优先用于换票）
     * @return array|null
     */
    private static function fetchIdentity($provider, $code, $itemId = '', $aggType = '')
    {
        if ($provider === 'qq') {
            return QQOAuth::fetchIdentity($code);
        }
        if ($provider === 'gitee') {
            return GiteeOAuth::fetchIdentity($code);
        }

        $itemId = trim((string) $itemId);
        $aggType = trim((string) $aggType);
        $item = $itemId !== '' ? OAuthConfig::aggItem($itemId) : null;
        if ($item === null && $aggType !== '') {
            $resolved = OAuthConfig::resolveAggItemIdByType($aggType);
            if ($resolved !== '') {
                $itemId = $resolved;
                $item = OAuthConfig::aggItem($itemId);
            }
        }
        if ($item === null) {
            return null;
        }
        $type = $aggType !== '' ? $aggType : (isset($item['type']) ? (string) $item['type'] : '');
        return AggOAuth::fetchIdentity($code, $type, $itemId);
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
        if ($provider === 'gitee') {
            return hash('sha256', 'gitee:' . (isset($identity['id']) ? $identity['id'] : ''));
        }
        return hash(
            'sha256',
            'agg:' . (isset($identity['item_id']) ? $identity['item_id'] : '')
            . ':' . (isset($identity['social_uid']) ? $identity['social_uid'] : '')
        );
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
        if ($provider === 'qq' || $provider === 'gitee' || $provider === 'agg') {
            return $provider;
        }
        return null;
    }

    /**
     * @param PDO    $pdo
     * @param string $table
     * @param int    $userId
     * @return array
     */
    private static function loadAggmap(PDO $pdo, $table, $userId)
    {
        $stmt = $pdo->prepare('SELECT `aggmap` FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
        $stmt->execute(array((int) $userId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return self::decodeAggmap($row && isset($row['aggmap']) ? $row['aggmap'] : '');
    }

    /**
     * @param string $raw
     * @return array<string, string>
     */
    private static function decodeAggmap($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array();
        }
        $out = array();
        foreach ($data as $k => $v) {
            $k = (string) $k;
            if (!OAuthConfig::isValidAggItemId($k)) {
                continue;
            }
            $v = trim((string) $v);
            if ($v !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
