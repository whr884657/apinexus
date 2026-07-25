<?php
/**
 * 文件：core/FrontendComment.php
 * 作用：前台主题 · 文章评论（主题只调用本类，禁止直读库）
 */

class FrontendComment
{
    const RATE_IP_WINDOW = 3600;
    const RATE_IP_MAX = 30;
    const RATE_EMAIL_WINDOW = 3600;
    const RATE_EMAIL_MAX = 15;

    /**
     * @return bool
     */
    public static function tableReady()
    {
        return class_exists('CommentManager') && CommentManager::tableReady();
    }

    /**
     * @param int $contentid
     * @return array
     */
    public static function listByContentId($contentid)
    {
        return CommentManager::listApprovedByContent($contentid);
    }

    /**
     * 提交评论：邮箱必填；昵称/网址选填；可引用 parentid
     *
     * @param int    $contentid
     * @param string $email
     * @param string $body
     * @param string $nickname
     * @param string $website
     * @param int    $parentid
     * @return array|string
     */
    public static function submit($contentid, $email, $body, $nickname = '', $website = '', $parentid = 0)
    {
        if (!self::tableReady()) {
            return '评论功能尚未就绪';
        }

        $userid = 0;
        if (class_exists('UserAuth') && UserAuth::check()) {
            $userid = (int) UserAuth::id();
            $user = class_exists('FrontendUser') ? FrontendUser::current() : null;
            if (is_array($user)) {
                if (trim((string) $email) === '' && !empty($user['email'])) {
                    $email = (string) $user['email'];
                }
                if (trim((string) $nickname) === '' && !empty($user['username'])) {
                    $nickname = (string) $user['username'];
                }
            }
        }

        if (class_exists('AuthSecurity')) {
            $ip = AuthSecurity::clientIp();
            $emailKey = function_exists('vs_normalize_email')
                ? vs_normalize_email($email)
                : strtolower(trim((string) $email));
            if (!AuthSecurity::rateLimitAllow('comment_ip:' . $ip, self::RATE_IP_WINDOW, self::RATE_IP_MAX, false)) {
                return '评论过于频繁，请稍后再试';
            }
            if ($emailKey !== ''
                && !AuthSecurity::rateLimitAllow('comment_email:' . $emailKey, self::RATE_EMAIL_WINDOW, self::RATE_EMAIL_MAX, false)
            ) {
                return '该邮箱评论过于频繁，请稍后再试';
            }
        }

        $result = CommentManager::create(array(
            'contentid' => (int) $contentid,
            'parentid'  => (int) $parentid,
            'userid'    => $userid,
            'email'     => $email,
            'nickname'  => $nickname,
            'website'   => $website,
            'body'      => $body,
            'status'    => CommentManager::STATUS_APPROVED,
        ));

        if (is_array($result) && class_exists('AuthSecurity')) {
            $ip = AuthSecurity::clientIp();
            $emailKey = function_exists('vs_normalize_email')
                ? vs_normalize_email($email)
                : strtolower(trim((string) $email));
            AuthSecurity::rateLimitAllow('comment_ip:' . $ip, self::RATE_IP_WINDOW, self::RATE_IP_MAX, true);
            if ($emailKey !== '') {
                AuthSecurity::rateLimitAllow('comment_email:' . $emailKey, self::RATE_EMAIL_WINDOW, self::RATE_EMAIL_MAX, true);
            }
        }

        if (is_array($result) && class_exists('CommentNotify')) {
            try {
                CommentNotify::notifyAdminsNew($result);
                $parentid = isset($result['parentid']) ? (int) $result['parentid'] : 0;
                if ($parentid > 0) {
                    $parent = CommentManager::findById($parentid);
                    if (is_array($parent)) {
                        CommentNotify::notifyParentQuoted($result, $parent);
                    }
                }
            } catch (Exception $e) {
                // 邮件失败不阻断评论
            }
        }

        return $result;
    }
}
