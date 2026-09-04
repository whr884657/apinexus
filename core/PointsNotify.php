<?php
/**
 * 文件：core/PointsNotify.php
 * 作用：积分相关邮件通知（余额归零 / 不足调用 / 充值成功；失败不阻断主流程）
 */

class PointsNotify
{
    /** Redis 逻辑键前缀：积分不足调用提醒（24h 去重） */
    const REDIS_KEY_INSUFFICIENT_PREFIX = 'notify:points_insufficient:';

    /** 不足提醒去重 TTL（秒） */
    const INSUFFICIENT_TTL = 86400;

    /**
     * 积分余额由正变为零时通知用户
     *
     * @param int   $userId
     * @param float $balance 变动后余额（应为 0）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyBalanceZero($userId, $balance = 0.0)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_points_zero', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭积分余额归零通知邮件');
        }

        $userId = (int) $userId;
        $to = self::userEmail($userId);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '用户邮箱无效');
        }

        $siteName = self::siteName();
        $username = self::userName($userId);
        $rechargeUrl = rtrim(vs_base_url(), '/') . '/user/recharge';

        $subject = '【' . $siteName . '】积分余额已用尽';
        $body = '<p>您好' . ($username !== '' ? ('，' . self::e($username)) : '') . '：</p>';
        $body .= '<p>您在「' . self::e($siteName) . '」的积分余额已变为 <strong>0</strong>。';
        $body .= '收费接口将暂时无法继续调用，请及时充值。</p>';
        $body .= '<p>当前余额：' . self::e(self::fmtPoints($balance)) . '</p>';
        $body .= '<p><a href="' . self::e($rechargeUrl) . '">前往充值中心</a></p>';
        $body .= '<p>本邮件由系统自动发送，如非本人操作请忽略。</p>';

        return self::sendOne($to, $subject, $body);
    }

    /**
     * 积分不足以支付本次调用时通知用户（与归零通知独立；Redis 24h 去重）
     *
     * @param int   $userId
     * @param float $balance 当前余额
     * @param float $need     本次需要积分（可 0）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyPointsInsufficient($userId, $balance = 0.0, $need = 0.0)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_points_insufficient', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭积分不足调用通知邮件');
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '用户无效');
        }

        // 去重依赖 Redis；不可用时跳过，避免连调刷信
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'sent' => 0, 'error' => '缓存不可用，已跳过不足提醒');
        }
        $redisKey = self::REDIS_KEY_INSUFFICIENT_PREFIX . $userId;
        // 发信前原子占坑（SET NX），避免并发连调刷信
        $claimed = false;
        try {
            $claimed = (bool) RedisService::withClient(function ($redis) use ($redisKey) {
                $fullKey = RedisService::buildKey($redisKey);
                // phpredis：NX + EX
                return (bool) $redis->set($fullKey, '1', array('nx', 'ex' => self::INSUFFICIENT_TTL));
            });
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => '缓存不可用，已跳过不足提醒');
        }
        if (!$claimed) {
            return array('ok' => true, 'sent' => 0, 'error' => '24小时内已提醒过');
        }

        $to = self::userEmail($userId);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::clearInsufficientNoticeFlag($userId);
            return array('ok' => false, 'sent' => 0, 'error' => '用户邮箱无效');
        }

        $siteName = self::siteName();
        $username = self::userName($userId);
        $rechargeUrl = rtrim(vs_base_url(), '/') . '/user/recharge';

        $subject = '【' . $siteName . '】积分余额已不支持调用该接口';
        $body = '<p>您好' . ($username !== '' ? ('，' . self::e($username)) : '') . '：</p>';
        $body .= '<p>您在「' . self::e($siteName) . '」的积分余额已不足以支付本次收费接口调用，请及时充值。</p>';
        $body .= '<ul>';
        $body .= '<li>当前余额：' . self::e(self::fmtPoints($balance)) . '</li>';
        if ((float) $need > 0) {
            $body .= '<li>本次需要：' . self::e(self::fmtPoints($need)) . '</li>';
        }
        $body .= '</ul>';
        $body .= '<p><a href="' . self::e($rechargeUrl) . '">前往充值中心</a></p>';
        $body .= '<p>本邮件由系统自动发送，如非本人操作请忽略。</p>';

        $result = self::sendOne($to, $subject, $body);
        if (empty($result['ok']) || (int) $result['sent'] <= 0) {
            self::clearInsufficientNoticeFlag($userId);
        }
        return $result;
    }

    /**
     * 清除「积分不足调用」24h 去重标记（充值/加分后可再次提醒）
     *
     * @param int $userId
     * @return void
     */
    public static function clearInsufficientNoticeFlag($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        try {
            RedisCache::forget(self::REDIS_KEY_INSUFFICIENT_PREFIX . $userId);
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * 用户充值到账成功后通知
     *
     * @param int    $userId
     * @param float  $amount   到账积分
     * @param float  $balance  到账后余额
     * @param string $orderno  订单号（可空）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyRechargeSuccess($userId, $amount, $balance, $orderno = '')
    {
        self::clearInsufficientNoticeFlag($userId);

        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_recharge_success', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭积分充值成功通知邮件');
        }

        $userId = (int) $userId;
        $to = self::userEmail($userId);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '用户邮箱无效');
        }

        $siteName = self::siteName();
        $username = self::userName($userId);
        $pointsUrl = rtrim(vs_base_url(), '/') . '/user/points';

        $subject = '【' . $siteName . '】积分充值成功';
        $body = '<p>您好' . ($username !== '' ? ('，' . self::e($username)) : '') . '：</p>';
        $body .= '<p>您在「' . self::e($siteName) . '」的积分充值已到账。</p>';
        $body .= '<ul>';
        $body .= '<li>到账积分：' . self::e(self::fmtPoints($amount)) . '</li>';
        $body .= '<li>当前余额：' . self::e(self::fmtPoints($balance)) . '</li>';
        if (trim((string) $orderno) !== '') {
            $body .= '<li>订单号：' . self::e($orderno) . '</li>';
        }
        $body .= '</ul>';
        $body .= '<p><a href="' . self::e($pointsUrl) . '">查看积分变动</a></p>';
        $body .= '<p>本邮件由系统自动发送。</p>';

        return self::sendOne($to, $subject, $body);
    }

    /**
     * 令牌配额用尽（或开始改扣总积分）时通知用户；Redis 去重至配额抬高后清除
     *
     * @param int $userId
     * @param int $keyId
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyKeyQuotaExhausted($userId, $keyId)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_key_quota', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭令牌配额用尽通知邮件');
        }

        $userId = (int) $userId;
        $keyId = (int) $keyId;
        if ($userId <= 0 || $keyId <= 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '参数无效');
        }

        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'sent' => 0, 'error' => '缓存不可用，已跳过配额提醒');
        }

        $redisKey = ApiKeyManager::REDIS_KEY_QUOTA_NOTICE_PREFIX . $keyId;
        $claimed = false;
        try {
            // 必须用 setNx / ['nx']；array('nx'=>true) 在 phpredis 下不生效，会每次覆盖并发信
            $claimed = (bool) RedisService::withClient(function ($redis) use ($redisKey) {
                $fullKey = RedisService::buildKey($redisKey);
                if (method_exists($redis, 'setNx')) {
                    return (bool) $redis->setNx($fullKey, '1');
                }
                return (bool) $redis->set($fullKey, '1', array('nx'));
            });
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => '缓存不可用，已跳过配额提醒');
        }
        if (!$claimed) {
            return array('ok' => true, 'sent' => 0, 'error' => '已提醒过');
        }

        $to = self::userEmail($userId);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            ApiKeyManager::clearQuotaNoticeFlag($keyId);
            return array('ok' => false, 'sent' => 0, 'error' => '用户邮箱无效');
        }

        $keyRow = ApiKeyManager::findById($keyId);
        $remark = is_array($keyRow) && isset($keyRow['remark']) ? trim((string) $keyRow['remark']) : '';
        if ($remark === '') {
            $remark = '令牌#' . $keyId;
        }
        $quota = is_array($keyRow) && isset($keyRow['quota']) ? (float) $keyRow['quota'] : 0.0;
        $fallback = is_array($keyRow) && !empty($keyRow['quotafallback']);

        $siteName = self::siteName();
        $username = self::userName($userId);
        $keysUrl = rtrim(vs_base_url(), '/') . '/user/keys';

        $subject = '【' . $siteName . '】令牌配额已用尽';
        $body = '<p>您好' . ($username !== '' ? ('，' . self::e($username)) : '') . '：</p>';
        $body .= '<p>您在「' . self::e($siteName) . '」的调用令牌「' . self::e($remark) . '」所分配的积分配额已用尽。</p>';
        $body .= '<ul>';
        if ($quota > 0) {
            $body .= '<li>分配配额：' . self::e(self::fmtPoints($quota)) . '</li>';
        }
        if ($fallback) {
            $body .= '<li>后续调用将消耗您的<strong>账户总积分</strong>（已开启配额回落）。</li>';
        } else {
            $body .= '<li>在提高该令牌配额或开启「配额用尽后改用总积分」之前，使用该令牌的收费调用将无法继续。</li>';
        }
        $body .= '</ul>';
        $body .= '<p><a href="' . self::e($keysUrl) . '">前往令牌管理</a></p>';
        $body .= '<p>本邮件由系统自动发送，如非本人操作请忽略。</p>';

        $result = self::sendOne($to, $subject, $body);
        if (empty($result['ok']) || (int) $result['sent'] <= 0) {
            ApiKeyManager::clearQuotaNoticeFlag($keyId);
        }
        return $result;
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return array{ok:bool,sent:int,error:string}
     */
    private static function sendOne($to, $subject, $body)
    {
        try {
            Mailer::send($to, $subject, $body);
            return array('ok' => true, 'sent' => 1, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => $e->getMessage());
        }
    }

    /**
     * @param int $userid
     * @return string
     */
    private static function userEmail($userid)
    {
        $userid = (int) $userid;
        if ($userid <= 0) {
            return '';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `email` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && isset($row['email']) ? trim((string) $row['email']) : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @param int $userid
     * @return string
     */
    private static function userName($userid)
    {
        $userid = (int) $userid;
        if ($userid <= 0) {
            return '';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `username` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && isset($row['username']) ? trim((string) $row['username']) : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @return string
     */
    private static function siteName()
    {
        if (class_exists('SiteContext')) {
            $n = trim((string) SiteContext::siteName());
            if ($n !== '') {
                return $n;
            }
        }
        try {
            $n = trim((string) Config::get('site_name', 'ApiNexus'));
            return $n !== '' ? $n : 'ApiNexus';
        } catch (Exception $e) {
            return 'ApiNexus';
        }
    }

    /**
     * @param float|int|string $n
     * @return string
     */
    private static function fmtPoints($n)
    {
        $v = round((float) $n, 4);
        $s = rtrim(rtrim(sprintf('%.4f', $v), '0'), '.');
        return $s === '' ? '0' : $s;
    }

    /**
     * @param string $s
     * @return string
     */
    private static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
