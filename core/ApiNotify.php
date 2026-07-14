<?php
/**
 * 文件：core/ApiNotify.php
 * 作用：接口投稿 / 审核结果的邮件通知（依赖 Mailer，发信失败不阻断主流程）
 */

class ApiNotify
{
    /**
     * 开发者提交或重新提交后，通知全体可用管理员
     *
     * @param array $api formatRow / 原始行均可（需含 name、endpoint、username 等）
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsPending(array $api)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
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
    public static function notifyUserAuditResult(array $api, $audit, $rejectReason = '')
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }

        $to = isset($api['email']) ? trim((string) $api['email']) : '';
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'sent' => 0, 'error' => '投稿用户邮箱无效');
        }

        $siteName = self::siteName();
        $name = isset($api['name']) ? (string) $api['name'] : '';
        $audit = (int) $audit;
        $reason = trim((string) $rejectReason);

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

        try {
            Mailer::send($to, $subject, $body);
            return array('ok' => true, 'sent' => 1, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => $e->getMessage());
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
        return array_values($list);
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
            try {
                Mailer::send($email, $subject, $body);
                $sent++;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
            }
        }
        return array(
            'ok'    => $sent > 0,
            'sent'  => $sent,
            'error' => $sent > 0 ? '' : $lastError,
        );
    }

    /**
     * @return string
     */
    private static function siteName()
    {
        try {
            $name = trim((string) Config::get('site_name', 'misc-api'));
            return $name !== '' ? $name : 'misc-api';
        } catch (Exception $e) {
            return 'misc-api';
        }
    }

    /**
     * @param string $text
     * @return string
     */
    private static function e($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
