<?php
/**
 * 文件：core/FrontendFeedback.php
 * 作用：前台主题 · 接口反馈提交（主题只调用本类，禁止直读库）
 */

class FrontendFeedback
{
    /**
     * @return bool
     */
    public static function tableReady()
    {
        return class_exists('ApiFeedbackManager') && ApiFeedbackManager::tableReady();
    }

    /**
     * 当前登录用户提交某接口反馈
     *
     * @param int    $apiid
     * @param string $content
     * @return array|string
     */
    public static function submit($apiid, $content)
    {
        if (!class_exists('UserAuth') || !UserAuth::check()) {
            return '请登录后提交反馈问题';
        }
        $uid = (int) UserAuth::id();
        if ($uid <= 0) {
            return '请登录后提交反馈问题';
        }
        return ApiFeedbackManager::create($apiid, $uid, $content);
    }
}
