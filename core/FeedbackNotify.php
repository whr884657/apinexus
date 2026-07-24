<?php
/**
 * 文件：core/FeedbackNotify.php
 * 作用：接口反馈处理结果邮件通知（依赖 Mailer，失败不阻断主流程）
 */

class FeedbackNotify
{
    /**
     * 管理员标记已处理后，通知提交用户
     *
     * @param array $feedback ApiFeedbackManager::formatRow 结构（可含 email）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyUserHandled(array $feedback)
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
        $apiId = isset($feedback['apiid']) ? (int) $feedback['apiid'] : 0;
        $detailUrl = $apiId > 0 ? vs_api_detail_url($apiId) : '';

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
        if ($detailUrl !== '') {
            $body .= '<p>接口详情：<a href="' . self::e($detailUrl) . '">' . self::e($detailUrl) . '</a></p>';
        }
        $body .= '<p>本邮件由系统自动发送。</p>';

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
        return 'ApiNexus';
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
