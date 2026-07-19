<?php
/**
 * 文件：core/LinkNotify.php
 * 作用：友情链接申请 / 审核通过的邮件通知（失败不阻断主流程）
 */

class LinkNotify
{
    /**
     * 有新申请时通知全体可用管理员
     *
     * @param array $link LinkManager::formatRow
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsPending(array $link)
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
    public static function notifyApplicantApproved(array $link)
    {
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'sent' => 0, 'error' => '邮箱发信未配置');
        }
        if (Config::get('mail_notify_link_pass', '1') !== '1') {
            return array('ok' => false, 'sent' => 0, 'error' => '已关闭友链通过通知');
        }

        $to = self::extractEmail(isset($link['contact']) ? (string) $link['contact'] : '');
        if ($to === '') {
            return array('ok' => false, 'sent' => 0, 'error' => '联系方式中无有效邮箱');
        }

        $siteName = self::siteName();
        $name = isset($link['name']) ? (string) $link['name'] : '';
        $linksUrl = rtrim(vs_base_url(), '/') . '/links';

        $subject = '【' . $siteName . '】您的友情链接申请已通过';
        $body = '<p>您好：</p>';
        $body .= '<p>您申请的友情链接「' . self::e($name) . '」已审核<strong>通过</strong>，可在站点友链页查看：</p>';
        $body .= '<p><a href="' . self::e($linksUrl) . '">' . self::e($linksUrl) . '</a></p>';
        $body .= '<p>本邮件由系统自动发送。</p>';

        try {
            Mailer::send($to, $subject, $body);
            return array('ok' => true, 'sent' => 1, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => $e->getMessage());
        }
    }

    /**
     * 从联系方式中提取邮箱
     *
     * @param string $contact
     * @return string
     */
    public static function extractEmail($contact)
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
            $name = trim((string) Config::get('site_name', 'ApiNexus'));
            return $name !== '' ? $name : 'ApiNexus';
        } catch (Exception $e) {
            return 'ApiNexus';
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
