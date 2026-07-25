<?php
/**
 * 文件：core/FrontendComment.php
 * 作用：前台主题 · 文章评论（主题只调用本类，禁止直读库）
 */

class FrontendComment
{
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
     * 提交评论：邮箱必填；昵称选填
     *
     * @param int    $contentid
     * @param string $email
     * @param string $body
     * @param string $nickname
     * @return array|string
     */
    public static function submit($contentid, $email, $body, $nickname = '')
    {
        $userid = 0;
        if (class_exists('UserAuth') && UserAuth::check()) {
            $userid = (int) UserAuth::id();
        }
        return CommentManager::create(array(
            'contentid' => (int) $contentid,
            'userid'    => $userid,
            'email'     => $email,
            'nickname'  => $nickname,
            'body'      => $body,
            'status'    => CommentManager::STATUS_PENDING,
        ));
    }
}
