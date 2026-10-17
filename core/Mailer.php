<?php
/**
 * 文件：core/Mailer.php
 * 作用：系统邮件发送（SMTP；多账号池 mailsmtp；轮询/主备/随机；失败换号一次）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 * 调用方仍用 Mailer::send($to, $subject, $body)；选号与换号在本类内部完成。
 */

class Mailer
{
    const CONFIG_KEY = 'mailsmtp';
    const MAX_ACCOUNTS = 5;
    /** mailsmtp JSON 解析深度上限（与 SystemApiKey 对齐） */
    const JSON_MAX_DEPTH = 16;
    /** mailsmtp 原始 JSON 最大字节（5 账号足够） */
    const JSON_MAX_BYTES = 32768;
    /** 轮询计数桶（RateLimitStore / Redis） */
    const RR_BUCKET = 'mailrr';
    const RR_WINDOW = 2592000; // 30 天

    /**
     * 发送邮件
     *
     * @param string $to      收件人
     * @param string $subject 主题
     * @param string $body    HTML 正文
     * @param string|array $pinId   非空时钉死该账号 id（失败不换号；后台测试用）；若为 array 则当作 $options
     * @param array        $options 可选：`mode` 强制选号策略（0 轮询 / 1 主备 / 2 随机），覆盖配置（群发强制轮询用）
     * @return bool
     * @throws Exception
     */
    public static function send($to, $subject, $body, $pinId = '', $options = array())
    {
        if (is_array($pinId)) {
            $options = $pinId;
            $pinId = '';
        }
        if (!is_array($options)) {
            $options = array();
        }

        if (!Config::isMailEnabled()) {
            throw new Exception('邮箱发信功能未配置，请先在后台系统设置中配置');
        }

        $to = trim((string) $to);
        // 业务发信（注册/验证码等）用宽松校验；群发另传 strict=true
        if (!self::isValidRecipient($to, false)) {
            throw new Exception('收件邮箱无效');
        }
        if (preg_match('/[\r\n]/', (string) $subject)) {
            throw new Exception('邮件参数含非法换行');
        }

        $cfg = self::loadConfig();
        $pool = self::readyPool($cfg);
        if (count($pool) === 0) {
            throw new Exception('未配置可用的 SMTP 账号');
        }

        $pinId = preg_replace('/[^a-zA-Z0-9]/', '', (string) $pinId);
        if ($pinId !== '') {
            $pinned = null;
            foreach ($pool as $acc) {
                if (isset($acc['id']) && (string) $acc['id'] === $pinId) {
                    $pinned = $acc;
                    break;
                }
            }
            if ($pinned === null) {
                // 钉死测试：允许测未启用号（仍须 host/from 齐）
                foreach (isset($cfg['list']) && is_array($cfg['list']) ? $cfg['list'] : array() as $acc) {
                    if (!is_array($acc) || !isset($acc['id']) || (string) $acc['id'] !== $pinId) {
                        continue;
                    }
                    $host = isset($acc['host']) ? trim((string) $acc['host']) : '';
                    $from = isset($acc['fromemail']) ? trim((string) $acc['fromemail']) : '';
                    if ($host === '' || $from === '') {
                        throw new Exception('该账号 SMTP 主机或发件邮箱未配置');
                    }
                    $pinned = $acc;
                    break;
                }
            }
            if ($pinned === null) {
                throw new Exception('指定的发信账号不存在');
            }
            return self::sendWithAccount($pinned, $to, $subject, $body);
        }

        $mode = isset($cfg['mode']) ? (int) $cfg['mode'] : 0;
        if (array_key_exists('mode', $options)) {
            $mode = (int) $options['mode'];
            if ($mode < 0 || $mode > 2) {
                $mode = 0;
            }
        }
        $pick = self::pickPrimary($pool, $mode);
        $primary = $pick['account'];
        $primaryIndex = $pick['index'];

        try {
            return self::sendWithAccount($primary, $to, $subject, $body);
        } catch (Exception $e1) {
            $alt = self::pickFailover($pool, $mode, $primaryIndex);
            if ($alt === null) {
                throw $e1;
            }
            try {
                return self::sendWithAccount($alt, $to, $subject, $body);
            } catch (Exception $e2) {
                throw new Exception('主账号与换号均失败：' . $e1->getMessage() . ' / ' . $e2->getMessage());
            }
        }
    }

    /**
     * 验证码邮件 HTML 正文（登录 / 重置 / 注册等）
     *
     * @param string $displayName 称呼（用户名）
     * @param string $brandName   站点名或系统名
     * @param string $actionDesc  用途说明，如「登录」「申请重置管理员密码」
     * @param string $code        6 位验证码
     * @param int    $ttlSeconds  有效期秒数
     * @return string
     */
    public static function otpMailBody($displayName, $brandName, $actionDesc, $code, $ttlSeconds)
    {
        $mins = max(1, (int) ($ttlSeconds / 60));
        $name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
        $brand = htmlspecialchars((string) $brandName, ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars((string) $actionDesc, ENT_QUOTES, 'UTF-8');
        $codeEsc = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');

        return '<div style="font-family:sans-serif;line-height:1.8;">'
            . '<p>您好，' . $name . '：</p>'
            . '<p>您正在' . $action . ' ' . $brand . '，验证码为：</p>'
            . '<p style="font-size:24px;font-weight:bold;margin:16px 0;">' . $codeEsc . '</p>'
            . '<p>验证码 ' . $mins . ' 分钟内有效，请勿泄露给他人。</p>'
            . '<p>如非本人操作，请忽略此邮件。</p></div>';
    }

    /**
     * 是否至少有一个可用发信账号
     *
     * @return bool
     */
    public static function hasReadyAccount()
    {
        return count(self::readyPool(self::loadConfig())) > 0;
    }

    /**
     * 解析 mailsmtp JSON（深度/长度上限；失败返回 null）
     *
     * @param string $raw
     * @return array|null
     */
    public static function decodeConfigJson($raw)
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if (strlen($raw) > self::JSON_MAX_BYTES) {
            return null;
        }
        $decoded = json_decode($raw, true, self::JSON_MAX_DEPTH);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }

    /**
     * 字段是否含回车或其它控制字符（SMTP 注入防护）
     *
     * @param string $value
     * @return bool
     */
    public static function hasControlChars($value)
    {
        return (bool) preg_match('/[\x00-\x1F\x7F]/', (string) $value);
    }

    /**
     * SMTP 主机是否为合法域名（禁止 IP、禁止 URL/路径）
     *
     * @param string $host
     * @return bool
     */
    public static function isValidSmtpHost($host)
    {
        $host = trim((string) $host);
        if ($host === '') {
            return false;
        }
        if (strlen($host) > 255 || self::hasControlChars($host)) {
            return false;
        }
        if (strpos($host, '://') !== false || strpos($host, '/') !== false
            || strpos($host, '@') !== false || strpos($host, ' ') !== false
            || strpos($host, '\\') !== false) {
            return false;
        }
        // 去掉末尾点再验
        $host = rtrim($host, '.');
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        // 至少一段点号的域名（如 smtp.qq.com）；禁止裸 IP / 单标签冒充
        if (!preg_match(
            '/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$/',
            $host
        )) {
            return false;
        }
        return true;
    }

    /**
     * 保存/发信前校验账号 SMTP 敏感字段；通过返回 ''，否则返回错误文案
     *
     * @param array $account
     * @param bool  $requireReady 为 true 时要求 host/user/from 非空且格式齐（启用账号）
     * @return string
     */
    public static function validateAccountFields(array $account, $requireReady = false)
    {
        $host = isset($account['host']) ? trim((string) $account['host']) : '';
        $user = isset($account['user']) ? trim((string) $account['user']) : '';
        $from = isset($account['fromemail']) ? trim((string) $account['fromemail']) : '';
        $fromName = isset($account['fromname']) ? trim((string) $account['fromname']) : '';

        if ($host !== '') {
            if (!self::isValidSmtpHost($host)) {
                return 'SMTP 服务器请填写域名（不允许 IP 或非法字符）';
            }
        } elseif ($requireReady) {
            return '已启用的账号须填写 SMTP 主机、用户名与发件邮箱';
        }

        if ($user !== '') {
            if (self::hasControlChars($user)) {
                return 'SMTP 用户名含非法控制字符';
            }
        } elseif ($requireReady) {
            return '已启用的账号须填写 SMTP 主机、用户名与发件邮箱';
        }

        if ($from !== '') {
            if (self::hasControlChars($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                return '发件邮箱格式无效';
            }
        } elseif ($requireReady) {
            return '已启用的账号须填写 SMTP 主机、用户名与发件邮箱';
        }

        if ($fromName !== '' && self::hasControlChars($fromName)) {
            return '发件人名称含非法控制字符';
        }

        return '';
    }

    /**
     * @return array{mode:int,list:array<int,array>}
     */
    public static function loadConfig()
    {
        $raw = Config::get(self::CONFIG_KEY, '');
        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = self::decodeConfigJson($raw);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }
        return self::normalizeConfig($decoded);
    }

    /**
     * @param mixed $raw
     * @return array{mode:int,list:array<int,array>}
     */
    public static function normalizeConfig($raw)
    {
        $mode = 0;
        $list = array();
        if (is_array($raw)) {
            if (isset($raw['mode'])) {
                $mode = (int) $raw['mode'];
            }
            if (isset($raw['list']) && is_array($raw['list'])) {
                $list = $raw['list'];
            }
        }
        if ($mode < 0 || $mode > 2) {
            $mode = 0;
        }
        $out = array();
        $seen = array();
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $acc = self::normalizeAccount($item);
            if ($acc === null) {
                continue;
            }
            if (isset($seen[$acc['id']])) {
                continue;
            }
            $seen[$acc['id']] = true;
            $out[] = $acc;
            if (count($out) >= self::MAX_ACCOUNTS) {
                break;
            }
        }
        return array(
            'mode' => $mode,
            'list' => $out,
        );
    }

    /**
     * @param array $item
     * @return array|null
     */
    public static function normalizeAccount(array $item)
    {
        $id = isset($item['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $item['id']) : '';
        if ($id === '' || strlen($id) > 16) {
            $id = self::newAccountId();
        }
        $name = isset($item['name']) ? trim((string) $item['name']) : '';
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 32, 'UTF-8');
        } else {
            $name = substr($name, 0, 32);
        }
        // 显式解析：避免 JSON 数字 0 / 字符串 "0" 被误判（empty("0") 在 PHP 为 true，但 (int)"0"===0）
        $onRaw = isset($item['on']) ? $item['on'] : 0;
        if (is_bool($onRaw)) {
            $on = $onRaw ? 1 : 0;
        } elseif (is_string($onRaw)) {
            $on = ($onRaw === '1' || strtolower($onRaw) === 'true') ? 1 : 0;
        } else {
            $on = ((int) $onRaw) === 1 ? 1 : 0;
        }
        $host = isset($item['host']) ? trim((string) $item['host']) : '';
        if (strlen($host) > 255) {
            $host = substr($host, 0, 255);
        }
        $port = isset($item['port']) ? (int) $item['port'] : 465;
        if ($port < 1 || $port > 65535) {
            $port = 465;
        }
        // ssl: 0=SSL · 1=STARTTLS · 2=无
        $ssl = isset($item['ssl']) ? (int) $item['ssl'] : 0;
        if ($ssl < 0 || $ssl > 2) {
            $ssl = 0;
        }
        $user = isset($item['user']) ? trim((string) $item['user']) : '';
        if (strlen($user) > 255) {
            $user = substr($user, 0, 255);
        }
        $pass = isset($item['pass']) ? (string) $item['pass'] : '';
        if (strlen($pass) > 512) {
            $pass = substr($pass, 0, 512);
        }
        $fromemail = isset($item['fromemail']) ? trim((string) $item['fromemail']) : '';
        if (strlen($fromemail) > 255) {
            $fromemail = substr($fromemail, 0, 255);
        }
        $fromname = isset($item['fromname']) ? trim((string) $item['fromname']) : '';
        if (function_exists('mb_substr')) {
            $fromname = mb_substr($fromname, 0, 64, 'UTF-8');
        } else {
            $fromname = substr($fromname, 0, 64);
        }

        return array(
            'id' => $id,
            'name' => $name,
            'on' => $on,
            'host' => $host,
            'port' => $port,
            'ssl' => $ssl,
            'user' => $user,
            'pass' => $pass,
            'fromemail' => $fromemail,
            'fromname' => $fromname,
        );
    }

    /**
     * @return string
     */
    public static function newAccountId()
    {
        try {
            return substr(bin2hex(random_bytes(4)), 0, 8);
        } catch (Exception $e) {
            return substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        }
    }

    /**
     * 从旧扁平键拼一份 mailsmtp（迁移用；不写库）
     *
     * @return array{mode:int,list:array}
     */
    public static function buildFromLegacyFlat()
    {
        $host = trim((string) Config::get('mail_smtp_host', ''));
        $from = trim((string) Config::get('mail_from_email', ''));
        $list = array();
        if ($host !== '' || $from !== '') {
            $enc = strtolower(trim((string) Config::get('mail_smtp_secure', 'ssl')));
            $ssl = 0;
            if ($enc === 'tls') {
                $ssl = 1;
            } elseif ($enc === '' || $enc === 'none' || $enc === '2') {
                $ssl = 2;
            }
            $list[] = self::normalizeAccount(array(
                'id' => 'legacy1',
                'name' => '默认邮箱',
                'on' => 1,
                'host' => $host,
                'port' => (int) Config::get('mail_smtp_port', 465),
                'ssl' => $ssl,
                'user' => trim((string) Config::get('mail_smtp_user', '')),
                'pass' => (string) Config::get('mail_smtp_pass', ''),
                'fromemail' => $from,
                'fromname' => trim((string) Config::get('mail_from_name', '')),
            ));
        }
        return array(
            'mode' => 0,
            'list' => $list,
        );
    }

    /**
     * 若尚无 mailsmtp 或 list 为空且有旧键，则迁移写入；不负责 DELETE 旧键
     *
     * @return array{mode:int,list:array}
     */
    public static function ensureMigrated()
    {
        $all = Config::all();
        if (array_key_exists(self::CONFIG_KEY, $all)) {
            $cfg = self::loadConfig();
            if (count($cfg['list']) > 0) {
                return $cfg;
            }
            // mailsmtp 已存在但 list 空：若旧键有值则折入
            $legacy = self::buildFromLegacyFlat();
            if (count($legacy['list']) > 0) {
                Config::set(self::CONFIG_KEY, self::encodeConfig($legacy));
                return $legacy;
            }
            return $cfg;
        }
        $cfg = self::buildFromLegacyFlat();
        Config::set(self::CONFIG_KEY, self::encodeConfig($cfg));
        return $cfg;
    }

    /**
     * 保存前合并口令：新条目 pass 为空则保留同 id 旧口令
     *
     * @param array $newCfg
     * @param array $oldCfg
     * @return array
     */
    public static function mergePasswords(array $newCfg, array $oldCfg)
    {
        $oldById = array();
        if (isset($oldCfg['list']) && is_array($oldCfg['list'])) {
            foreach ($oldCfg['list'] as $acc) {
                if (is_array($acc) && isset($acc['id'])) {
                    $oldById[(string) $acc['id']] = $acc;
                }
            }
        }
        $list = isset($newCfg['list']) && is_array($newCfg['list']) ? $newCfg['list'] : array();
        foreach ($list as $i => $acc) {
            if (!is_array($acc)) {
                continue;
            }
            $id = isset($acc['id']) ? (string) $acc['id'] : '';
            $pass = isset($acc['pass']) ? (string) $acc['pass'] : '';
            if ($pass === '' && $id !== '' && isset($oldById[$id]['pass'])) {
                $list[$i]['pass'] = (string) $oldById[$id]['pass'];
            }
        }
        $newCfg['list'] = $list;
        return $newCfg;
    }

    /**
     * @param array $cfg
     * @return string
     */
    public static function encodeConfig(array $cfg)
    {
        $norm = self::normalizeConfig($cfg);
        return json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 启用池：on=1,且 host/user/fromemail 非空（顺序与 list 一致）
     *
     * @param array $cfg
     * @return array<int,array>
     */
    public static function readyPool(array $cfg)
    {
        $pool = array();
        $list = isset($cfg['list']) && is_array($cfg['list']) ? $cfg['list'] : array();
        foreach ($list as $acc) {
            if (!is_array($acc)) {
                continue;
            }
            if (empty($acc['on'])) {
                continue;
            }
            if (self::validateAccountFields($acc, true) !== '') {
                continue;
            }
            $pool[] = $acc;
        }
        return $pool;
    }

    /**
     * @param array $pool
     * @param int   $mode
     * @return array{account:array,index:int}
     */
    public static function pickPrimary(array $pool, $mode)
    {
        $n = count($pool);
        if ($n === 0) {
            throw new Exception('未配置可用的 SMTP 账号');
        }
        $mode = (int) $mode;
        $idx = 0;
        if ($mode === 1) {
            $idx = 0;
        } elseif ($mode === 2) {
            $idx = random_int(0, $n - 1);
        } else {
            try {
                RateLimitStore::recordHit(self::RR_BUCKET, self::RR_WINDOW);
                $c = RateLimitStore::countHits(self::RR_BUCKET, self::RR_WINDOW);
                $idx = ($c > 0) ? (($c - 1) % $n) : 0;
            } catch (Exception $e) {
                $idx = (int) (time() % $n);
            }
        }
        return array(
            'account' => $pool[$idx],
            'index' => $idx,
        );
    }

    /**
     * 换号：按 mode 在 pool 中选不同于主选的一个
     *
     * @param array $pool
     * @param int   $mode
     * @param int   $primaryIndex
     * @return array|null
     */
    public static function pickFailover(array $pool, $mode, $primaryIndex)
    {
        $n = count($pool);
        if ($n < 2) {
            return null;
        }
        $mode = (int) $mode;
        $primaryIndex = (int) $primaryIndex;
        if ($primaryIndex < 0 || $primaryIndex >= $n) {
            $primaryIndex = 0;
        }

        if ($mode === 2) {
            $candidates = array();
            for ($i = 0; $i < $n; $i++) {
                if ($i !== $primaryIndex) {
                    $candidates[] = $pool[$i];
                }
            }
            if (count($candidates) === 0) {
                return null;
            }
            return $candidates[random_int(0, count($candidates) - 1)];
        }

        // mode 0/1：取主选后的下一个（绕回）
        $next = ($primaryIndex + 1) % $n;
        if ($next === $primaryIndex) {
            return null;
        }
        return $pool[$next];
    }

    /**
     * ssl 数字 → 旧 secure 串（sendSmtp 用）
     *
     * @param int $ssl
     * @return string ssl|tls|none
     */
    public static function sslToSecure($ssl)
    {
        $ssl = (int) $ssl;
        if ($ssl === 1) {
            return 'tls';
        }
        if ($ssl === 2) {
            return 'none';
        }
        return 'ssl';
    }

    /**
     * @param array  $account
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     * @throws Exception
     */
    public static function sendWithAccount(array $account, $to, $subject, $body)
    {
        $err = self::validateAccountFields($account, true);
        if ($err !== '') {
            throw new Exception($err);
        }
        $host = isset($account['host']) ? trim((string) $account['host']) : '';
        $fromEmail = isset($account['fromemail']) ? trim((string) $account['fromemail']) : '';
        $port = isset($account['port']) ? (int) $account['port'] : 465;
        if ($port <= 0) {
            $port = 465;
        }
        $user = isset($account['user']) ? trim((string) $account['user']) : '';
        $pass = isset($account['pass']) ? (string) $account['pass'] : '';
        $secure = self::sslToSecure(isset($account['ssl']) ? (int) $account['ssl'] : 0);
        $fromName = isset($account['fromname']) ? trim((string) $account['fromname']) : '';
        if ($fromName === '') {
            $fromName = (string) Config::get('site_name', 'ApiNexus');
        }

        return self::sendSmtp($host, $port, $user, $pass, $secure, $fromEmail, $fromName, $to, $subject, $body);
    }

    /**
     * SMTP 发信（原生 socket，与历史实现一致）
     *
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pass
     * @param string $secure ssl|tls|none
     * @param string $fromEmail
     * @param string $fromName
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     * @throws Exception
     */
    private static function sendSmtp($host, $port, $user, $pass, $secure, $fromEmail, $fromName, $to, $subject, $body)
    {
        $secure = strtolower(trim((string) $secure));
        if ($secure !== 'tls' && $secure !== 'none' && $secure !== '') {
            $secure = 'ssl';
        }
        if ($secure === '') {
            $secure = 'none';
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . (int) $port;
        // 显式 SSL 上下文（禁止依赖 PHP 默认隐式策略）
        $sslCtx = array(
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        );
        $context = stream_context_create(array('ssl' => $sslCtx));
        // 连接/读写超时收紧，降低单封拖死 PHP 时限导致群发丢在途 uid 的概率
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new Exception('无法连接 SMTP 服务器：' . $errstr);
        }

        stream_set_timeout($socket, 10);
        self::readResponse($socket, array(220));

        $ehloHost = self::safeEhloHostname();
        self::smtpCommand($socket, 'EHLO ' . $ehloHost, array(250));

        if ($secure === 'tls') {
            self::smtpCommand($socket, 'STARTTLS', array(220));
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new Exception('STARTTLS 握手失败');
            }
            self::smtpCommand($socket, 'EHLO ' . $ehloHost, array(250));
        }

        if ($user !== '') {
            self::smtpCommand($socket, 'AUTH LOGIN', array(334));
            self::smtpCommand($socket, base64_encode($user), array(334));
            self::smtpCommand($socket, base64_encode($pass), array(235));
        }

        self::smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', array(250));
        self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', array(250, 251));
        self::smtpCommand($socket, 'DATA', array(354));

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $message = "From: {$encodedFromName} <{$fromEmail}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.";

        self::writeAll($socket, $message . "\r\n");
        self::readResponse($socket, array(250));
        self::smtpCommand($socket, 'QUIT', array(221));
        fclose($socket);

        return true;
    }

    /**
     * EHLO 主机名：仅允许安全主机字符，禁 CRLF/空白（防命令注入）
     *
     * @return string
     */
    /**
     * 校验收件邮箱
     *
     * @param string $email
     * @param bool   $strict true=群发：ASCII + 拒常见一次性域；false=注册/验证码等业务发信
     * @return bool
     */
    public static function isValidRecipient($email, $strict = false)
    {
        $email = trim((string) $email);
        if ($email === '' || strlen($email) > 254) {
            return false;
        }
        if (preg_match('/[\r\n\0]/', $email)) {
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return false;
        }
        $local = substr($email, 0, $at);
        $domain = strtolower(substr($email, $at + 1));
        if ($local === false || $domain === '' || strlen($local) > 64) {
            return false;
        }
        if (strlen($domain) > 253 || strpos($domain, '.') === false) {
            return false;
        }
        if (!$strict) {
            return true;
        }
        // 群发严格：SMTP 路径宜 ASCII；拦常见一次性域
        if (!preg_match('/^[\x21-\x7E]+$/', $email)) {
            return false;
        }
        static $disposable = array(
            'mailinator.com' => true,
            'guerrillamail.com' => true,
            'guerrillamail.net' => true,
            '10minutemail.com' => true,
            'tempmail.com' => true,
            'yopmail.com' => true,
            'trashmail.com' => true,
            'sharklasers.com' => true,
        );
        if (isset($disposable[$domain])) {
            return false;
        }
        return true;
    }

    private static function safeEhloHostname()
    {
        $raw = '';
        if (isset($_SERVER['SERVER_NAME'])) {
            $raw = trim((string) $_SERVER['SERVER_NAME']);
        }
        if ($raw === '' || preg_match('/[\r\n\x00-\x1F\x7F\s]/', $raw)) {
            return 'localhost';
        }
        if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,252}[A-Za-z0-9])?$/', $raw)) {
            return 'localhost';
        }
        return $raw;
    }

    /**
     * @param resource $socket
     * @param string   $command
     * @param array<int,int> $expectedCodes
     * @return void
     * @throws Exception
     */
    private static function smtpCommand($socket, $command, array $expectedCodes)
    {
        self::writeAll($socket, $command . "\r\n");
        self::readResponse($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     * @param string $data
     * @return void
     * @throws Exception
     */
    private static function writeAll($socket, $data)
    {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $count = @fwrite($socket, substr($data, $written));
            if ($count === false || $count === 0) {
                throw new Exception('SMTP 写入失败');
            }
            $written += $count;
        }
    }

    /**
     * @param resource $socket
     * @param array<int,int> $expectedCodes
     * @return string
     * @throws Exception
     */
    private static function readResponse($socket, array $expectedCodes)
    {
        $response = '';
        $complete = false;
        while (($line = @fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                $complete = true;
                break;
            }
        }
        if (!$complete || strlen($response) < 3 || !preg_match('/^([0-9]{3})(?:[ -])/', $response, $match)) {
            throw new Exception('SMTP 响应不完整');
        }
        $code = (int) $match[1];
        if (!in_array($code, $expectedCodes, true)) {
            throw new Exception('SMTP 阶段响应错误：' . $code);
        }
        return $response;
    }
}
