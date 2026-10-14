<?php
/**
 * 文件：core/MailNotify.php
 * 作用：业务邮件模板与通知唯一实现（审核/友链/反馈/评论/积分/开发者申请等）
 *
 * SMTP 传输见 Mailer；验证码正文见 Mailer::otpMailBody。
 * 发信失败不阻断主流程；开关为 mail_notify_*；正文默认禁止站内 URL（E432）。
 */

class MailNotify
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
        $vars = self::buildCommonVars($userId);
        $vars['余额'] = self::fmtPoints($balance);

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::POINTS_ZERO : 19,
            $vars
        );
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】积分余额已用尽';
        $body = '<p>您好' . ($username !== '' ? ('，' . self::e($username)) : '') . '：</p>';
        $body .= '<p>您在「' . self::e($siteName) . '」的积分余额已变为 <strong>0</strong>。';
        $body .= '收费接口将暂时无法继续调用，请及时充值。</p>';
        $body .= '<p>当前余额：' . self::e(self::fmtPoints($balance)) . '</p>';
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

        $vars = self::buildCommonVars($userId);
        $vars['余额'] = self::fmtPoints($balance);
        $vars['本次需要'] = (float) $need > 0 ? self::fmtPoints($need) : '';

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::POINTS_INSUFFICIENT : 20,
            $vars
        );
        if (is_array($bound)) {
            $result = self::sendOne($to, $bound['subject'], $bound['body']);
            if (empty($result['ok']) || (int) $result['sent'] <= 0) {
                self::clearInsufficientNoticeFlag($userId);
            }
            return $result;
        }

        $subject = '【' . $siteName . '】积分余额已不支持调用该接口';
        $body = '<p>您好' . ($username !== '' ? ('，' . self::e($username)) : '') . '：</p>';
        $body .= '<p>您在「' . self::e($siteName) . '」的积分余额已不足以支付本次收费接口调用，请及时充值。</p>';
        $body .= '<ul>';
        $body .= '<li>当前余额：' . self::e(self::fmtPoints($balance)) . '</li>';
        if ((float) $need > 0) {
            $body .= '<li>本次需要：' . self::e(self::fmtPoints($need)) . '</li>';
        }
        $body .= '</ul>';
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

        $vars = self::buildCommonVars($userId);
        $vars['到账积分'] = self::fmtPoints($amount);
        $vars['余额'] = self::fmtPoints($balance);
        $vars['订单号'] = trim((string) $orderno);

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::RECHARGE_SUCCESS : 22,
            $vars
        );
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

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
            // 必须用 setNx 或 set(..., array('nx'))；禁止关联键写法，否则 phpredis 下 NX 不生效会连发（E305）
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

        $vars = self::buildCommonVars($userId);
        $vars['令牌备注'] = $remark;
        $vars['令牌名称'] = $remark;
        $vars['令牌编号'] = (string) $keyId;
        $vars['分配配额'] = $quota > 0 ? self::fmtPoints($quota) : '';
        $vars['配额回落'] = $fallback ? '是' : '否';

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::KEY_QUOTA : 21,
            $vars
        );
        if (is_array($bound)) {
            $result = self::sendOne($to, $bound['subject'], $bound['body']);
            if (empty($result['ok']) || (int) $result['sent'] <= 0) {
                ApiKeyManager::clearQuotaNoticeFlag($keyId);
            }
            return $result;
        }

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
        $body .= '<p>本邮件由系统自动发送，如非本人操作请忽略。</p>';

        $result = self::sendOne($to, $subject, $body);
        if (empty($result['ok']) || (int) $result['sent'] <= 0) {
            ApiKeyManager::clearQuotaNoticeFlag($keyId);
        }
        return $result;
    }

    /**
     * 用户充值订单首次支付成功后，通知全体可用管理员（收入明细 + 今日累计）
     *
     * @param string $orderno
     * @param int    $userId
     * @param float  $money   本单实付金额（元）
     * @param float  $points  本单到账积分
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsOrderPaid($orderno, $userId, $money, $points = 0.0)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_order_admin', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭充值订单成功通知管理员邮件');
        }

        $emails = self::adminEmails();
        if (count($emails) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '未找到管理员邮箱');
        }

        $orderno = trim((string) $orderno);
        $userId = (int) $userId;
        $money = round((float) $money, 2);
        $points = (float) $points;
        $username = self::userName($userId);
        $todayTotal = self::todayRechargeIncome();
        $siteName = self::siteName();

        $vars = self::buildCommonVars($userId);
        $vars['订单号'] = $orderno;
        $vars['本单收入'] = '¥' . number_format($money, 2, '.', '');
        $vars['到账积分'] = $points > 0 ? self::fmtPoints($points) : '';
        $vars['今日累计充值收入'] = '¥' . number_format($todayTotal, 2, '.', '');

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::ORDER_ADMIN : 23,
            $vars
        );
        if (is_array($bound)) {
            return self::sendToMany($emails, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】有新的充值订单到账';
        $body = '<p>您好，站长：</p>';
        $body .= '<p>站点「' . self::e($siteName) . '」新增一笔充值订单，支付已成功。</p>';
        $body .= '<ul>';
        if ($orderno !== '') {
            $body .= '<li>订单号：' . self::e($orderno) . '</li>';
        }
        $body .= '<li>用户：' . self::e($username !== '' ? $username : ('用户#' . $userId)) . '</li>';
        $body .= '<li>本单收入：¥' . self::e(number_format($money, 2, '.', '')) . '</li>';
        if ($points > 0) {
            $body .= '<li>到账积分：' . self::e(self::fmtPoints($points)) . '</li>';
        }
        $body .= '<li>今日累计充值收入：¥' . self::e(number_format($todayTotal, 2, '.', '')) . '</li>';
        $body .= '</ul>';
        $body .= '<p>本邮件由系统自动发送。</p>';

        return self::sendToMany($emails, $subject, $body);
    }

    /**
     * 开发者提交或重新提交后，通知全体可用管理员
     *
     * @param array $api formatRow / 原始行均可（需含 name、endpoint、username 等）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsApiPending(array $api)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_submit', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭投稿通知邮件');
        }

        $emails = self::adminEmails();
        if (count($emails) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '未找到管理员邮箱');
        }

        $siteName = self::siteName();
        $name = isset($api['name']) ? (string) $api['name'] : '';
        $endpoint = isset($api['endpoint']) ? (string) $api['endpoint'] : '';
        $username = isset($api['username']) ? (string) $api['username'] : '';
        $userId = isset($api['userid']) ? (int) $api['userid'] : 0;
        $apiId = isset($api['id']) ? (int) $api['id'] : 0;

        $vars = self::buildCommonVars($userId);
        $vars['接口名'] = $name;
        $vars['接口地址'] = $endpoint;
        $vars['接口编号'] = (string) $apiId;
        $vars['提交者'] = $username !== '' ? $username : ('UID ' . $userId);

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::API_SUBMIT : 10,
            $vars
        );
        if (is_array($bound)) {
            return self::sendToMany($emails, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】有新的接口待审核';
        $body = '<p>您好，站长：</p>';
        $body .= '<p>开发者提交了接口，请登录管理后台的「接口审核」处理。</p>';
        $body .= '<ul>';
        $body .= '<li>接口名称：' . self::e($name) . '</li>';
        $body .= '<li>接口地址：' . self::e($endpoint) . '</li>';
        $body .= '<li>接口编号：' . $apiId . '</li>';
        $body .= '<li>提交者：' . self::e($username !== '' ? $username : ('UID ' . $userId)) . '</li>';
        $body .= '</ul>';
        $body .= '<p>请及时审核。本邮件由系统自动发送。</p>';

        return self::sendToMany($emails, $subject, $body);
    }

    /**
     * 审核完成后通知投稿用户
     *
     * @param array  $api
     * @param int    $audit ApiManager::AUDIT_*
     * @param string $rejectReason
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyUserApiAuditResult(array $api, $audit, $rejectReason = '')
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }

        $audit = (int) $audit;
        if ($audit === ApiManager::AUDIT_APPROVED) {
            if (Config::get('mail_notify_pass', '1') !== '1') {
                return array('ok' => false, 'sent' => 0, 'error' => '已关闭审核通过通知邮件');
            }
        } else {
            if (Config::get('mail_notify_fail', '1') !== '1') {
                return array('ok' => false, 'sent' => 0, 'error' => '已关闭审核拒绝通知邮件');
            }
        }

        $to = isset($api['email']) ? trim((string) $api['email']) : '';
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '投稿用户邮箱无效');
        }

        $siteName = self::siteName();
        $name = isset($api['name']) ? (string) $api['name'] : '';
        $reason = trim((string) $rejectReason);
        $apiUserId = isset($api['userid']) ? (int) $api['userid'] : 0;

        $apiId = isset($api['id']) ? (int) $api['id'] : 0;
        $vars = self::buildCommonVars($apiUserId);
        $vars['接口名'] = $name;
        $vars['接口编号'] = $apiId > 0 ? (string) $apiId : '';
        $vars['拒绝原因'] = $reason;
        $vars['原因说明'] = $reason;

        $channel = $audit === ApiManager::AUDIT_APPROVED
            ? (class_exists('MailChannels') ? MailChannels::API_PASS : 11)
            : (class_exists('MailChannels') ? MailChannels::API_FAIL : 12);
        $bound = self::renderBoundTemplate($channel, $vars);
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        if ($audit === ApiManager::AUDIT_APPROVED) {
            $subject = '【' . $siteName . '】您的接口已审核通过';
            $body = '<p>您好：</p>';
            $body .= '<p>您提交的接口「' . self::e($name) . '」已审核<strong>通过</strong>，可在用户中心「API 管理」中查看。</p>';
            $body .= '<p>本邮件由系统自动发送。</p>';
        } else {
            $subject = '【' . $siteName . '】您的接口未通过审核';
            $body = '<p>您好：</p>';
            $body .= '<p>您提交的接口「' . self::e($name) . '」未能通过审核。</p>';
            if ($reason !== '') {
                $body .= '<p>原因说明：</p><p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                    . nl2br(self::e($reason)) . '</p>';
            } else {
                $body .= '<p>管理员未填写具体原因。您可修改后重新提交，或联系站长了解详情。</p>';
            }
            $body .= '<p>本邮件由系统自动发送。</p>';
        }

        return self::sendOne($to, $subject, $body);
    }

    /**
     * 有新申请时通知全体可用管理员
     *
     * @param array $link LinkManager::formatRow
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsLinkPending(array $link)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_link_apply', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭友链申请通知');
        }

        $emails = self::adminEmails();
        if (count($emails) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '未找到管理员邮箱');
        }

        $siteName = self::siteName();
        $name = isset($link['name']) ? (string) $link['name'] : '';
        $siteurl = isset($link['siteurl']) ? (string) $link['siteurl'] : '';
        $contact = isset($link['contact']) ? (string) $link['contact'] : '';
        $id = isset($link['id']) ? (int) $link['id'] : 0;

        $vars = self::buildCommonVars(0);
        $vars['网站名称'] = $name;
        $vars['网站名'] = $name;
        $vars['网站链接'] = $siteurl;
        $vars['联系方式'] = $contact !== '' ? $contact : '未填写';
        $vars['编号'] = (string) $id;

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::LINK_APPLY : 13,
            $vars
        );
        if (is_array($bound)) {
            return self::sendToMany($emails, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】有新的友情链接申请';
        $body = '<p>您好，站长：</p>';
        $body .= '<p>有访客提交了友情链接申请，请登录管理后台「友情链接」处理。</p>';
        $body .= '<ul>';
        $body .= '<li>网站名称：' . self::e($name) . '</li>';
        $body .= '<li>网站链接：' . self::e($siteurl) . '</li>';
        $body .= '<li>联系方式：' . self::e($contact !== '' ? $contact : '未填写') . '</li>';
        $body .= '<li>编号：' . $id . '</li>';
        $body .= '</ul>';
        $body .= '<p>本邮件由系统自动发送。</p>';

        return self::sendToMany($emails, $subject, $body);
    }

    /**
     * 审核通过后，若联系方式含邮箱则通知申请人
     *
     * @param array $link
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyLinkApplicantApproved(array $link)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_link_pass', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭友链通过通知');
        }

        $to = self::extractContactEmail(isset($link['contact']) ? (string) $link['contact'] : '');
        if ($to === '') {
            return array('ok' => false, 'sent' => 0, 'error' => '联系方式中无有效邮箱');
        }

        $siteName = self::siteName();
        $name = isset($link['name']) ? (string) $link['name'] : '';

        $siteurl = isset($link['siteurl']) ? (string) $link['siteurl'] : '';
        $vars = self::buildCommonVars(0);
        $vars['网站名称'] = $name;
        $vars['网站名'] = $name;
        $vars['网站链接'] = $siteurl;

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::LINK_PASS : 14,
            $vars
        );
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】您的友情链接申请已通过';
        $body = '<p>您好：</p>';
        $body .= '<p>您申请的友情链接「' . self::e($name) . '」已审核<strong>通过</strong>，可在站点友链页查看。</p>';
        $body .= '<p>本邮件由系统自动发送。</p>';

        return self::sendOne($to, $subject, $body);
    }

    /**
     * 用户提交新反馈后，通知管理员 + 接口发布者（userid>0 的开发者）
     *
     * @param array $feedback ApiFeedbackManager::formatRow
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsFeedbackPending(array $feedback)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_feedback_admin', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭新反馈通知邮件');
        }

        $emails = self::adminEmails();
        $apiId = isset($feedback['apiid']) ? (int) $feedback['apiid'] : 0;
        $ownerId = self::apiOwnerUserId($apiId);
        $submitterId = isset($feedback['userid']) ? (int) $feedback['userid'] : 0;
        // 接口由前台用户发布时，额外通知发布者（与管理员邮箱去重；不重复通知提交者本人）
        if ($ownerId > 0 && $ownerId !== $submitterId) {
            $ownerEmail = self::userEmail($ownerId);
            if ($ownerEmail !== '' && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $emails[$ownerEmail] = $ownerEmail;
            }
        }
        $emails = array_values($emails);
        if (count($emails) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '未找到可通知的邮箱');
        }

        $siteName = self::siteName();
        $apiName = isset($feedback['api_name']) ? trim((string) $feedback['api_name']) : '';
        if ($apiName === '') {
            $apiName = '相关接口';
        }
        $username = isset($feedback['username']) ? trim((string) $feedback['username']) : '';
        $userId = $submitterId;
        $content = isset($feedback['content']) ? trim((string) $feedback['content']) : '';
        $id = isset($feedback['id']) ? (int) $feedback['id'] : 0;

        $submitterLabel = $username !== '' ? $username : ('用户#' . $userId);
        $vars = self::buildCommonVars($userId);
        $vars['反馈编号'] = (string) $id;
        $vars['关联接口'] = $apiName;
        $vars['接口名'] = $apiName;
        $vars['接口编号'] = $apiId > 0 ? (string) $apiId : '';
        $vars['提交用户'] = $submitterLabel;
        $vars['反馈内容'] = $content;

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::FEEDBACK_ADMIN : 15,
            $vars
        );
        if (is_array($bound)) {
            return self::sendToMany($emails, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】有新的接口反馈待处理';
        $body = '<p>您好：</p>';
        $body .= '<p>接口「' . self::e($apiName) . '」收到新的用户反馈，请及时处理。</p>';
        $body .= '<ul>';
        $body .= '<li>反馈编号：' . $id . '</li>';
        $body .= '<li>关联接口：' . self::e($apiName) . ($apiId > 0 ? ('（#' . $apiId . '）') : '') . '</li>';
        $body .= '<li>提交用户：' . self::e($username !== '' ? $username : ('用户#' . $userId)) . '</li>';
        $body .= '</ul>';
        if ($content !== '') {
            $body .= '<p>反馈内容：</p>';
            $body .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($content)) . '</p>';
        }
        $body .= '<p>管理员可在后台「接口反馈」中处理；开发者后续也可在用户中心处理自有接口反馈。</p>';
        $body .= '<p>本邮件由系统自动发送。</p>';

        return self::sendToMany($emails, $subject, $body);
    }

    /**
     * 管理员标记已处理后，通知提交用户
     *
     * @param array $feedback ApiFeedbackManager::formatRow 结构（可含 email）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyUserFeedbackHandled(array $feedback)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_feedback', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭反馈处理通知邮件');
        }

        $to = isset($feedback['email']) ? trim((string) $feedback['email']) : '';
        if ($to === '') {
            $to = self::userEmail(isset($feedback['userid']) ? (int) $feedback['userid'] : 0);
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '用户邮箱无效');
        }

        $siteName = self::siteName();
        $apiName = isset($feedback['api_name']) ? trim((string) $feedback['api_name']) : '';
        if ($apiName === '') {
            $apiName = '相关接口';
        }
        $content = isset($feedback['content']) ? trim((string) $feedback['content']) : '';
        $reply = isset($feedback['reply']) ? trim((string) $feedback['reply']) : '';
        $feedbackUserId = isset($feedback['userid']) ? (int) $feedback['userid'] : 0;

        $vars = self::buildCommonVars($feedbackUserId);
        $vars['关联接口'] = $apiName;
        $vars['接口名'] = $apiName;
        $vars['反馈内容'] = $content;
        $vars['处理回复'] = $reply;

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::FEEDBACK_USER : 16,
            $vars
        );
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】您的接口反馈已处理';
        $body = '<p>您好：</p>';
        $body .= '<p>您针对接口「' . self::e($apiName) . '」提交的反馈已处理完成。</p>';
        if ($content !== '') {
            $body .= '<p>您的反馈内容：</p>';
            $body .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($content)) . '</p>';
        }
        if ($reply !== '') {
            $body .= '<p>处理回复：</p>';
            $body .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($reply)) . '</p>';
        } else {
            $body .= '<p>管理员已标记处理完成（未填写文字回复）。</p>';
        }
        $body .= '<p>本邮件由系统自动发送。</p>';

        return self::sendOne($to, $subject, $body);
    }

    /**
     * 用户发布新评论后通知管理员（含引用场景）
     *
     * @param array $comment CommentManager::formatRow
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsCommentNew(array $comment)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_comment_admin', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭新评论通知邮件');
        }

        $emails = array_values(self::adminEmails());
        if (count($emails) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '未找到可通知的管理员邮箱');
        }

        $siteName = self::siteName();
        $title = self::articleTitle($comment);
        $nickname = self::commentNickname($comment);
        $bodyText = isset($comment['body']) ? trim((string) $comment['body']) : '';
        $id = isset($comment['id']) ? (int) $comment['id'] : 0;
        $parentid = isset($comment['parentid']) ? (int) $comment['parentid'] : 0;
        $commentUserId = isset($comment['userid']) ? (int) $comment['userid'] : 0;

        $vars = self::buildCommonVars($commentUserId);
        $vars['评论编号'] = (string) $id;
        $vars['关联文章'] = $title;
        $vars['标题'] = $title;
        $vars['评论者'] = $nickname;
        $vars['是否引用'] = $parentid > 0 ? '是' : '否';
        $vars['引用评论'] = $parentid > 0 ? (string) $parentid : '';
        $vars['评论内容'] = $bodyText;

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::COMMENT_ADMIN : 17,
            $vars
        );
        if (is_array($bound)) {
            return self::sendToMany($emails, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】有新的文章评论';
        if ($parentid > 0) {
            $subject = '【' . $siteName . '】有人引用了评论，请查看';
        }

        $html = '<p>您好：</p>';
        if ($parentid > 0) {
            $html .= '<p>文章「' . self::e($title) . '」有人引用了已有评论，请及时到后台查看。</p>';
        } else {
            $html .= '<p>文章「' . self::e($title) . '」收到新的用户评论，请及时到后台查看。</p>';
        }
        $html .= '<ul>';
        $html .= '<li>评论编号：#' . $id . '</li>';
        $html .= '<li>关联文章：' . self::e($title) . '</li>';
        $html .= '<li>评论者：' . self::e($nickname) . '</li>';
        if ($parentid > 0) {
            $html .= '<li>引用评论：#' . $parentid . '</li>';
        }
        $html .= '</ul>';
        if ($bodyText !== '') {
            $html .= '<p>评论内容：</p>';
            $html .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($bodyText)) . '</p>';
        }
        $html .= '<p>本邮件由系统自动发送。</p>';

        return self::sendToMany($emails, $subject, $html);
    }

    /**
     * 评论被引用时，通知被引用评论的作者
     *
     * @param array $comment       新评论 formatRow
     * @param array $parentComment 被引用评论（formatRow 或原始行均可）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyParentQuoted(array $comment, array $parentComment)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_comment', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭评论用户通知邮件');
        }

        $to = self::commentEmail($parentComment);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '被引用评论邮箱无效');
        }

        $fromEmail = self::commentEmail($comment);
        if ($fromEmail !== '' && strcasecmp($fromEmail, $to) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '跳过通知本人');
        }

        $siteName = self::siteName();
        $title = self::articleTitle($comment);
        $quoter = self::commentNickname($comment);
        $quoteBody = isset($comment['body']) ? trim((string) $comment['body']) : '';
        $origBody = isset($parentComment['body']) ? trim((string) $parentComment['body']) : '';
        $parentUserId = isset($parentComment['userid']) ? (int) $parentComment['userid'] : 0;

        $vars = self::buildCommonVars($parentUserId);
        $vars['关联文章'] = $title;
        $vars['标题'] = $title;
        $vars['评论者'] = $quoter;
        $vars['原评论'] = $origBody;
        $vars['评论内容'] = $quoteBody;
        $vars['管理员回复'] = '';
        $vars['通知类型'] = '评论被引用';

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::COMMENT_USER : 18,
            $vars
        );
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】您的评论被引用了';
        $html = '<p>您好：</p>';
        $html .= '<p>您在文章「' . self::e($title) . '」下的评论被 <strong>'
            . self::e($quoter) . '</strong> 引用了。</p>';
        if ($origBody !== '') {
            $html .= '<p>您的原评论：</p>';
            $html .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($origBody)) . '</p>';
        }
        if ($quoteBody !== '') {
            $html .= '<p>对方评论：</p>';
            $html .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($quoteBody)) . '</p>';
        }
        $html .= '<p>本邮件由系统自动发送。</p>';

        return self::sendOne($to, $subject, $html);
    }

    /**
     * 管理员回复评论后，通知评论作者
     *
     * @param array $comment CommentManager::formatRow（含 reply）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyUserAdminReply(array $comment)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_comment', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭评论用户通知邮件');
        }

        $reply = isset($comment['reply']) ? trim((string) $comment['reply']) : '';
        if ($reply === '') {
            return array('ok' => false, 'sent' => 0, 'error' => '回复内容为空，跳过通知');
        }

        $to = self::commentEmail($comment);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '评论者邮箱无效');
        }

        $siteName = self::siteName();
        $title = self::articleTitle($comment);
        $bodyText = isset($comment['body']) ? trim((string) $comment['body']) : '';
        $commentUserId = isset($comment['userid']) ? (int) $comment['userid'] : 0;

        $vars = self::buildCommonVars($commentUserId);
        $vars['关联文章'] = $title;
        $vars['标题'] = $title;
        $vars['评论者'] = '';
        $vars['原评论'] = '';
        $vars['评论内容'] = $bodyText;
        $vars['管理员回复'] = $reply;
        $vars['通知类型'] = '管理员回复';

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::COMMENT_USER : 18,
            $vars
        );
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】您的评论收到了管理员回复';
        $html = '<p>您好：</p>';
        $html .= '<p>您在文章「' . self::e($title) . '」下的评论收到了管理员回复。</p>';
        if ($bodyText !== '') {
            $html .= '<p>您的评论：</p>';
            $html .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($bodyText)) . '</p>';
        }
        $html .= '<p>管理员回复：</p>';
        $html .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
            . nl2br(self::e($reply)) . '</p>';
        $html .= '<p>本邮件由系统自动发送。</p>';

        return self::sendOne($to, $subject, $html);
    }

    /**
     * 普通用户提交开发者申请后，通知全体可用管理员
     *
     * @param array $apply ApiFeedbackManager::formatDevApplyRow
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsDevApplyPending(array $apply)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_dev_apply', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭开发者申请通知邮件');
        }

        $emails = self::adminEmails();
        if (count($emails) === 0) {
            return array('ok' => false, 'sent' => 0, 'error' => '未找到管理员邮箱');
        }

        $siteName = self::siteName();
        $username = isset($apply['username']) ? trim((string) $apply['username']) : '';
        $userId = isset($apply['userid']) ? (int) $apply['userid'] : 0;
        $email = isset($apply['email']) ? trim((string) $apply['email']) : '';
        $content = isset($apply['content']) ? trim((string) $apply['content']) : '';
        $id = isset($apply['id']) ? (int) $apply['id'] : 0;

        $applicant = $username !== '' ? $username : ('用户#' . $userId);
        $vars = self::buildCommonVars($userId);
        $vars['申请编号'] = (string) $id;
        $vars['申请人'] = $applicant;
        $vars['注册邮箱'] = $email;
        $vars['申请理由'] = $content;

        $bound = self::renderBoundTemplate(
            class_exists('MailChannels') ? MailChannels::DEV_APPLY : 24,
            $vars
        );
        if (is_array($bound)) {
            return self::sendToMany($emails, $bound['subject'], $bound['body']);
        }

        $subject = '【' . $siteName . '】有新的开发者申请待审核';
        $body = '<p>您好，站长：</p>';
        $body .= '<p>有普通用户提交了开发者申请，请登录管理后台的「开发审核」处理。</p>';
        $body .= '<ul>';
        $body .= '<li>申请编号：' . $id . '</li>';
        $body .= '<li>申请人：' . self::e($username !== '' ? $username : ('用户#' . $userId)) . '</li>';
        if ($email !== '') {
            $body .= '<li>注册邮箱：' . self::e($email) . '</li>';
        }
        $body .= '</ul>';
        if ($content !== '') {
            $body .= '<p>申请理由：</p>';
            $body .= '<p style="padding:12px;background:#f8fafc;border-radius:8px;">'
                . nl2br(self::e($content)) . '</p>';
        }
        $body .= '<p>请及时审核。本邮件由系统自动发送。</p>';

        return self::sendToMany($emails, $subject, $body);
    }

    /**
     * 管理员审核完成后通知申请人
     *
     * @param array $apply  formatDevApplyRow（须含 email）
     * @param bool  $passed true=通过 / false=拒绝
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyUserDevApplyResult(array $apply, $passed)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }

        $passed = (bool) $passed;
        if ($passed) {
            if (Config::get('mail_notify_dev_pass', '1') !== '1') {
                return array('ok' => false, 'sent' => 0, 'error' => '已关闭开发者申请通过通知邮件');
            }
        } else {
            if (Config::get('mail_notify_dev_fail', '1') !== '1') {
                return array('ok' => false, 'sent' => 0, 'error' => '已关闭开发者申请拒绝通知邮件');
            }
        }

        $to = isset($apply['email']) ? trim((string) $apply['email']) : '';
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '申请人邮箱无效');
        }

        $siteName = self::siteName();
        $username = isset($apply['username']) ? trim((string) $apply['username']) : '';
        $hello = $username !== '' ? ('您好，' . self::e($username) . '：') : '您好：';

        $reply = isset($apply['reply']) ? trim((string) $apply['reply']) : '';
        $applyUserId = isset($apply['userid']) ? (int) $apply['userid'] : 0;

        $vars = self::buildCommonVars($applyUserId);
        $vars['申请人'] = $username !== '' ? $username : ('用户#' . $applyUserId);
        $vars['处理回复'] = $reply;

        $channel = $passed
            ? (class_exists('MailChannels') ? MailChannels::DEV_PASS : 25)
            : (class_exists('MailChannels') ? MailChannels::DEV_FAIL : 26);
        $bound = self::renderBoundTemplate($channel, $vars);
        if (is_array($bound)) {
            return self::sendOne($to, $bound['subject'], $bound['body']);
        }

        if ($passed) {
            $subject = '【' . $siteName . '】您的开发者申请已通过';
            $body = '<p>' . $hello . '</p>';
            $body .= '<p>您在「' . self::e($siteName) . '」提交的<strong>开发者申请</strong>已审核<strong>通过</strong>。';
            $body .= '您现在可以以开发者身份使用相关功能（如投稿接口等）。</p>';
            if ($reply !== '') {
                $body .= '<p><strong>处理回复：</strong><br>'
                    . nl2br(self::e($reply)) . '</p>';
            }
            $body .= '<p>本邮件由系统自动发送。</p>';
        } else {
            $subject = '【' . $siteName . '】您的开发者申请未通过';
            $body = '<p>' . $hello . '</p>';
            $body .= '<p>您在「' . self::e($siteName) . '」提交的<strong>开发者申请</strong>未能通过审核。</p>';
            if ($reply !== '') {
                $body .= '<p><strong>处理回复：</strong><br>'
                    . nl2br(self::e($reply)) . '</p>';
            } else {
                $body .= '<p>您可完善申请理由后再次提交，或联系站长了解详情。</p>';
            }
            $body .= '<p>本邮件由系统自动发送。</p>';
        }

        return self::sendOne($to, $subject, $body);
    }

    /**
     * 今日已完成充值订单的 money 合计（元）
     *
     * @return float
     */
    private static function todayRechargeIncome()
    {
        if (!class_exists('OrderManager') || !OrderManager::tableReady()) {
            return 0.0;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT COALESCE(SUM(`money`), 0) FROM `' . OrderManager::table() . '`
                 WHERE `direct` = ' . (int) OrderManager::DIRECT_INC
                . ' AND `kind` = ' . (int) OrderManager::KIND_RECHARGE
                . ' AND `status` = ' . (int) OrderManager::STATUS_DONE
                . ' AND `paytime` IS NOT NULL AND DATE(`paytime`) = CURDATE()'
            );
            if (!$stmt) {
                return 0.0;
            }
            return round((float) $stmt->fetchColumn(), 2);
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * @return array
     */
    private static function adminEmails()
    {
        $list = array();
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT `email` FROM `' . Database::table('admin') . '`
                 WHERE `status` = 1 AND `email` IS NOT NULL AND `email` <> \'\''
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
            foreach ($rows as $row) {
                $email = isset($row['email']) ? trim((string) $row['email']) : '';
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $list[$email] = $email;
                }
            }
        } catch (Exception $e) {
            return array();
        }
        return $list;
    }

    /**
     * @param array  $emails
     * @param string $subject
     * @param string $body
     * @return array{ok:bool,sent:int,error:string}
     */
    private static function sendToMany(array $emails, $subject, $body)
    {
        $sent = 0;
        $lastError = '';
        foreach ($emails as $email) {
            $one = self::sendOne($email, $subject, $body);
            if (!empty($one['ok'])) {
                $sent++;
            } else {
                $lastError = isset($one['error']) ? (string) $one['error'] : '发送失败';
            }
        }
        return array(
            'ok'    => $sent > 0,
            'sent'  => $sent,
            'error' => $sent > 0 ? '' : $lastError,
        );
    }

    /**
     * 单封发信出口（业务通知 / 群发共用；含收件人与主题头校验）
     *
     * @param string     $to
     * @param string     $subject
     * @param string     $body
     * @param array|null $options 透传 Mailer::send（如群发 `array('mode'=>0)` 强制轮询启用池）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function sendOne($to, $subject, $body, $options = null)
    {
        $to = trim((string) $to);
        $subject = self::safeHeaderText($subject);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            return array('ok' => false, 'sent' => 0, 'error' => '收件人邮箱无效');
        }
        try {
            if (is_array($options)) {
                Mailer::send($to, $subject, $body, '', $options);
            } else {
                Mailer::send($to, $subject, $body);
            }
            return array('ok' => true, 'sent' => 1, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => $e->getMessage());
        }
    }

    /**
     * 主题等邮件头字段：去掉换行与控制字符，防头注入（Mailer 仍会再拦一层）
     *
     * @param string $text
     * @return string
     */
    private static function safeHeaderText($text)
    {
        $text = (string) $text;
        $text = preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $text);
        return trim($text);
    }

    /**
     * 公共占位符变量（群发 / 业务通知共用）
     *
     * @param int        $userId
     * @param array|null $userRow
     * @return array<string,string>
     */
    public static function buildCommonVars($userId, $userRow = null)
    {
        $userId = (int) $userId;
        $username = '';
        $email = '';
        $avatar = '';
        $role = '';
        if (is_array($userRow)) {
            $username = isset($userRow['username']) ? trim((string) $userRow['username']) : '';
            $email = isset($userRow['email']) ? trim((string) $userRow['email']) : '';
            $avatar = isset($userRow['avatar']) ? trim((string) $userRow['avatar']) : '';
            $role = isset($userRow['role']) ? trim((string) $userRow['role']) : '';
        } else {
            $username = self::userName($userId);
            $email = self::userEmail($userId);
        }
        if ($role === '' && $userId > 0 && class_exists('UserRole')) {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare(
                    'SELECT `role`, `avatar` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($userId));
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($r)) {
                    $role = isset($r['role']) ? trim((string) $r['role']) : '';
                    if ($avatar === '' && isset($r['avatar'])) {
                        $avatar = trim((string) $r['avatar']);
                    }
                }
            } catch (Exception $e) {
            }
        }
        $roleLabel = '普通用户';
        if ($role !== '' && class_exists('UserRole')) {
            if ($role === UserRole::ROLE_DEVELOPER) {
                $roleLabel = '开发者';
            } elseif (method_exists('UserRole', 'label')) {
                $roleLabel = (string) UserRole::label($role);
            }
        }
        $now = time();
        $weekMap = array('日', '一', '二', '三', '四', '五', '六');
        $avatarUrl = self::absoluteAvatarUrl($avatar);
        $avatarHtml = '';
        if ($avatarUrl !== '') {
            $avatarHtml = '<img src="' . self::e($avatarUrl) . '" width="48" height="48" alt="" '
                . 'style="border-radius:50%;display:inline-block;vertical-align:middle;">';
        }
        $balance = 0.0;
        if ($userId > 0 && class_exists('PointsManager') && method_exists('PointsManager', 'balance')) {
            try {
                $balance = (float) PointsManager::balance($userId);
            } catch (Exception $e) {
                $balance = 0.0;
            }
        }
        $siteName = self::siteName();
        $siteUrl = self::siteUrl();
        return array(
            '站点名'     => $siteName,
            '站点名称'   => $siteName,
            '站点链接'   => $siteUrl,
            '站点地址'   => $siteUrl,
            '系统名'     => class_exists('SiteContext') ? (string) SiteContext::systemName() : $siteName,
            '用户名'     => $username !== '' ? $username : ('用户#' . $userId),
            '用户编号'   => (string) $userId,
            '用户邮箱'   => $email,
            '用户角色'   => $roleLabel,
            '用户头像地址'=> $avatarUrl,
            '用户头像'   => $avatarHtml,
            '余额'       => self::fmtPoints($balance),
            '日期'       => date('Y-m-d', $now),
            '时间'       => date('H:i:s', $now),
            '日期时间'   => date('Y-m-d H:i:s', $now),
            '年'         => date('Y', $now),
            '月'         => date('m', $now),
            '日'         => date('d', $now),
            '星期'       => '星期' . $weekMap[(int) date('w', $now)],
            '时'         => date('H', $now),
            '分'         => date('i', $now),
            '秒'         => date('s', $now),
        );
    }

    /**
     * @param string $avatar
     * @return string
     */
    private static function absoluteAvatarUrl($avatar)
    {
        $avatar = trim((string) $avatar);
        if ($avatar === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $avatar)) {
            return $avatar;
        }
        if (isset($avatar[0]) && $avatar[0] === '/' && function_exists('vs_base_url')) {
            $base = rtrim((string) vs_base_url(), '/');
            return $base !== '' ? ($base . $avatar) : $avatar;
        }
        return '';
    }

    /**
     * 替换 {{键}}；文字键转义，用户头像为白名单 HTML
     *
     * @param string               $text
     * @param array<string,string> $vars
     * @param bool                 $isHtml
     * @return string
     */
    public static function applyPlaceholders($text, array $vars, $isHtml = true)
    {
        $text = (string) $text;
        if ($text === '' || strpos($text, '{{') === false) {
            return $isHtml ? self::sanitizeMailHtml($text) : self::safeHeaderText($text);
        }
        $out = preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/u', function ($m) use ($vars, $isHtml) {
            $key = trim((string) $m[1]);
            if ($key === '' || !array_key_exists($key, $vars)) {
                return $m[0];
            }
            $val = (string) $vars[$key];
            if ($key === '用户头像') {
                return $isHtml ? $val : '';
            }
            return $isHtml ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : $val;
        }, $text);
        $out = is_string($out) ? $out : $text;
        return $isHtml ? self::sanitizeMailHtml($out) : self::safeHeaderText($out);
    }

    /**
     * 邮件 HTML 白名单（去掉 script/事件等）
     *
     * @param string $html
     * @return string
     */
    public static function sanitizeMailHtml($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }
        // 危险块级标签（含 svg/math 事件面）
        $html = preg_replace(
            '#<(script|iframe|object|embed|form|svg|math|link|meta|base|style)[^>]*>.*?</\1>#is',
            '',
            $html
        );
        $html = preg_replace(
            '#</?(script|iframe|object|embed|form|svg|math|link|meta|base|style)[^>]*>#is',
            '',
            $html
        );
        // 只处理标签段，避免误伤正文 URL（如 /one=1）；属性级剥离 on* / 危险协议
        $html = preg_replace_callback('/<[^>]+>/', array('MailNotify', 'sanitizeMailTag'), $html);
        if (!is_string($html)) {
            $html = '';
        }
        if (function_exists('mb_strlen') && mb_strlen($html, 'UTF-8') > 262144) {
            $html = mb_substr($html, 0, 262144, 'UTF-8');
        }
        return is_string($html) ? $html : '';
    }

    /**
     * @param array $m preg 回调，[0]=整标签
     * @return string
     */
    private static function sanitizeMailTag(array $m)
    {
        $tag = (string) $m[0];
        if ($tag === '' || $tag[0] !== '<') {
            return $tag;
        }
        // 注释 / 闭标签原样（闭标签无属性）
        if (isset($tag[1]) && ($tag[1] === '!' || $tag[1] === '/')) {
            return $tag;
        }
        if (!preg_match('/^<([a-zA-Z][a-zA-Z0-9:]*)\s*([^>]*)>$/s', $tag, $tm)) {
            return '';
        }
        $name = $tm[1];
        $rest = trim((string) $tm[2]);
        $selfClose = '';
        if ($rest !== '' && substr($rest, -1) === '/') {
            $selfClose = ' /';
            $rest = rtrim(substr($rest, 0, -1));
        }
        $attrsOut = '';
        if ($rest !== '') {
            if (preg_match_all(
                '#([^\s=/ >]+)(?:\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+)))?#',
                $rest,
                $am,
                PREG_SET_ORDER
            )) {
                foreach ($am as $a) {
                    $an = strtolower((string) $a[1]);
                    if ($an === '' || $an === '/') {
                        continue;
                    }
                    // 事件处理器（含 "onerror 直连、/onerror）
                    if (strpos($an, 'on') === 0 && strlen($an) >= 3) {
                        continue;
                    }
                    $hasVal = isset($a[2]) && $a[2] !== '';
                    if (!$hasVal) {
                        $attrsOut .= ' ' . $an;
                        continue;
                    }
                    $q0 = isset($a[2][0]) ? $a[2][0] : '"';
                    if ($q0 === '"') {
                        $rawVal = isset($a[3]) ? (string) $a[3] : '';
                        $quote = '"';
                    } elseif ($q0 === "'") {
                        $rawVal = isset($a[4]) ? (string) $a[4] : '';
                        $quote = "'";
                    } else {
                        $rawVal = isset($a[5]) ? (string) $a[5] : (string) $a[2];
                        $quote = '"';
                    }
                    if ($an === 'style') {
                        $safe = self::sanitizeStyleAttrValue($rawVal);
                        if ($safe === '') {
                            continue;
                        }
                        $attrsOut .= ' style=' . $quote
                            . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . $quote;
                        continue;
                    }
                    if ($an === 'href' || $an === 'src' || $an === 'action'
                        || $an === 'formaction' || $an === 'xlink:href') {
                        $safe = self::sanitizeUrlAttrValue($rawVal);
                        if ($safe === null) {
                            continue;
                        }
                        $attrsOut .= ' ' . $an . '=' . $quote
                            . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . $quote;
                        continue;
                    }
                    $attrsOut .= ' ' . $an . '=' . $quote
                        . htmlspecialchars($rawVal, ENT_QUOTES, 'UTF-8') . $quote;
                }
            }
        }
        return '<' . $name . $attrsOut . $selfClose . '>';
    }

    /**
     * 保留安全行内样式；剥离 expression / javascript url 等
     *
     * @param string $val
     * @return string
     */
    private static function sanitizeStyleAttrValue($val)
    {
        $val = html_entity_decode((string) $val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $val);
        if (!is_string($val) || $val === '') {
            return '';
        }
        if (preg_match('/expression\s*\(/i', $val)
            || preg_match('/-moz-binding/i', $val)
            || preg_match('/\bbehavior\s*:/i', $val)
            || preg_match('/(javascript|vbscript|data)\s*:/i', $val)
            || preg_match('/\burl\s*\(\s*[\'"]?\s*(javascript|vbscript|data)\s*:/i', $val)) {
            return '';
        }
        return $val;
    }

    /**
     * 实体解码后再判协议；危险则丢弃整属性（返回 null）
     *
     * @param string $val
     * @return string|null
     */
    private static function sanitizeUrlAttrValue($val)
    {
        $decoded = html_entity_decode((string) $val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/[\x00-\x1F\x7F]+/', '', $decoded);
        if (!is_string($decoded)) {
            return null;
        }
        $decoded = trim($decoded);
        $compact = preg_replace('/\s+/', '', $decoded);
        if (!is_string($compact)) {
            return null;
        }
        if (preg_match('/^(?:javascript|vbscript|data)\s*:/i', $compact)) {
            return null;
        }
        return $decoded;
    }

    /**
     * 剥站内绝对 URL / 本域链接（E432：业务系统通知默认禁止站内跳转）
     *
     * @param string $text
     * @return string
     */
    public static function stripSiteUrls($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        $hosts = array();
        if (function_exists('vs_base_url')) {
            $base = rtrim((string) vs_base_url(), '/');
            $h = $base !== '' ? parse_url($base, PHP_URL_HOST) : null;
            if (is_string($h) && $h !== '') {
                $hosts[strtolower($h)] = true;
            }
        }
        try {
            if (class_exists('Config')) {
                foreach (array('site_url', 'site_domain') as $k) {
                    $raw = trim((string) Config::get($k, ''));
                    if ($raw === '') {
                        continue;
                    }
                    if (!preg_match('#^https?://#i', $raw)) {
                        $raw = 'https://' . $raw;
                    }
                    $h = parse_url($raw, PHP_URL_HOST);
                    if (is_string($h) && $h !== '') {
                        $hosts[strtolower($h)] = true;
                    }
                }
            }
        } catch (Exception $e) {
        }
        if ($hosts === array()) {
            return $text;
        }
        $hostRe = implode('|', array_map(function ($h) {
            return preg_quote($h, '#');
        }, array_keys($hosts)));
        // 仅剥可点击站内链接（保留头像等 img src）
        $text = preg_replace(
            '#\shref\s*=\s*([\'"])\s*https?://(?:www\.)?(?:' . $hostRe . ')[^\'"]*\1#i',
            '',
            $text
        );
        // 纯文本中的绝对站内 URL（避开已在标签属性内的，粗略：非引号后）
        $text = preg_replace(
            '#(?<!["\'=])https?://(?:www\.)?(?:' . $hostRe . ')[^\s<\'"]*#i',
            '',
            $text
        );
        return is_string($text) ? $text : '';
    }

    /**
     * 读已发布绑定模板并渲染；无则 null（调用方回落内置文案）
     *
     * @param int                  $bindpage
     * @param array<string,string> $vars
     * @return array{subject:string,body:string}|null
     */
    public static function renderBoundTemplate($bindpage, array $vars)
    {
        if (!class_exists('ContentManager') || !ContentManager::tableReady()) {
            return null;
        }
        $tpl = ContentManager::findPublishedMailTemplate($bindpage);
        if (!is_array($tpl)) {
            return null;
        }
        $subject = isset($tpl['summary']) ? (string) $tpl['summary'] : '';
        $body = isset($tpl['body']) ? (string) $tpl['body'] : '';
        if (trim($subject) === '' || trim($body) === '') {
            return null;
        }
        if (class_exists('MailChannels')) {
            $vars = MailChannels::prepareVars($bindpage, $vars);
        }
        $subject = self::applyPlaceholders($subject, $vars, false);
        $body = self::applyPlaceholders($body, $vars, true);
        // 系统场景：渲染后再剥一次站内 URL（占位符展开后也可能带入）
        if (class_exists('MailChannels') && MailChannels::isSystemChannel($bindpage)) {
            $subject = self::stripSiteUrls($subject);
            $body = self::stripSiteUrls($body);
        }
        return array(
            'subject' => $subject,
            'body'    => $body,
        );
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
     * 站点绝对根地址（无尾斜杠）；优先当前访问基准，回落配置 site_url / site_domain
     *
     * @return string
     */
    private static function siteUrl()
    {
        if (function_exists('vs_base_url')) {
            $base = rtrim((string) vs_base_url(), '/');
            if ($base !== '') {
                return $base;
            }
        }
        try {
            $raw = trim((string) Config::get('site_url', ''));
            if ($raw === '') {
                $raw = trim((string) Config::get('site_domain', ''));
            }
            if ($raw === '') {
                return '';
            }
            if (!preg_match('#^https?://#i', $raw)) {
                $raw = 'https://' . $raw;
            }
            return rtrim($raw, '/');
        } catch (Exception $e) {
            return '';
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

    /**
     * 接口发布者用户 ID（0 表示管理员发布或未绑定）
     *
     * @param int $apiid
     * @return int
     */
    private static function apiOwnerUserId($apiid)
    {
        $apiid = (int) $apiid;
        if ($apiid <= 0) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `userid` FROM `' . Database::table('api') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($apiid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && isset($row['userid']) ? (int) $row['userid'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param array $comment
     * @return string
     */
    private static function commentEmail(array $comment)
    {
        $email = isset($comment['email']) ? trim((string) $comment['email']) : '';
        if (function_exists('vs_normalize_email')) {
            $email = vs_normalize_email($email);
        }
        return $email;
    }

    /**
     * @param array $comment
     * @return string
     */
    private static function commentNickname(array $comment)
    {
        $nickname = isset($comment['nickname']) ? trim((string) $comment['nickname']) : '';
        if ($nickname !== '') {
            return $nickname;
        }
        $email = self::commentEmail($comment);
        if ($email !== '') {
            return (string) preg_replace('/@.*$/', '', $email);
        }
        return '访客';
    }

    /**
     * @param array $comment
     * @return string
     */
    private static function articleTitle(array $comment)
    {
        $title = isset($comment['content_title']) ? trim((string) $comment['content_title']) : '';
        if ($title !== '') {
            return $title;
        }
        $id = isset($comment['contentid']) ? (int) $comment['contentid'] : 0;
        return $id > 0 ? ('文章#' . $id) : '相关文章';
    }

    /**
     * 从联系方式中提取邮箱
     *
     * @param string $contact
     * @return string
     */
    private static function extractContactEmail($contact)
    {
        $contact = trim((string) $contact);
        if ($contact === '') {
            return '';
        }
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return $contact;
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $contact, $m)) {
            $email = $m[0];
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        }
        return '';
    }

}
