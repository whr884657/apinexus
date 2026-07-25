<?php
/**
 * 文件：core/CommentNotify.php
 * 作用：文章评论邮件通知（新评论/引用通知管理员；被引用与管理员回复通知评论者；失败不阻断主流程）
 */

class CommentNotify
{
    /**
     * 用户发布新评论后通知管理员（含引用场景）
     *
     * @param array $comment CommentManager::formatRow
     * @return array{ok:bool,sent:int,error:string}
     */
    public static function notifyAdminsNew(array $comment)
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
        $articleUrl = self::articleUrl(isset($comment['contentid']) ? (int) $comment['contentid'] : 0);
        $adminUrl = self::adminCommentsUrl();

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
        if ($articleUrl !== '') {
            $html .= '<p>文章链接：<a href="' . self::e($articleUrl) . '">' . self::e($articleUrl) . '</a></p>';
        }
        if ($adminUrl !== '') {
            $html .= '<p>后台评论管理：<a href="' . self::e($adminUrl) . '">' . self::e($adminUrl) . '</a></p>';
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
        $articleUrl = self::articleUrl(isset($comment['contentid']) ? (int) $comment['contentid'] : 0);

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
        if ($articleUrl !== '') {
            $html .= '<p>查看文章：<a href="' . self::e($articleUrl) . '">' . self::e($articleUrl) . '</a></p>';
        }
        $html .= '<p>本邮件由系统自动发送。</p>';

        try {
            Mailer::send($to, $subject, $html);
            return array('ok' => true, 'sent' => 1, 'error' => '');
        } catch (Exception $e) {
            return array('ok' => false, 'sent' => 0, 'error' => $e->getMessage());
        }
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
        $articleUrl = self::articleUrl(isset($comment['contentid']) ? (int) $comment['contentid'] : 0);

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
        if ($articleUrl !== '') {
            $html .= '<p>查看文章：<a href="' . self::e($articleUrl) . '">' . self::e($articleUrl) . '</a></p>';
        }
        $html .= '<p>本邮件由系统自动发送。</p>';

        try {
            Mailer::send($to, $subject, $html);
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
     * @param int $contentid
     * @return string
     */
    private static function articleUrl($contentid)
    {
        $contentid = (int) $contentid;
        if ($contentid <= 0 || !function_exists('vs_base_url')) {
            return '';
        }
        return rtrim(vs_base_url(), '/') . '/articles/' . $contentid;
    }

    /**
     * @return string
     */
    private static function adminCommentsUrl()
    {
        if (!function_exists('vs_base_url')) {
            return '';
        }
        return rtrim(vs_base_url(), '/') . '/admin/content/comments';
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
     * @param string $s
     * @return string
     */
    private static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
