<?php
/**
 * 文件：core/PointsNotify.php
 * 作用：积分相关邮件通知（余额归零 / 充值成功；失败不阻断主流程）
 */

class PointsNotify
{
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
