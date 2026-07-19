<?php
/**
 * 文件：core/FrontendStats.php
 * 作用：前台主题可展示的统计数据（无 SQL 进主题）
 */

class FrontendStats
{
    /**
     * 已注册用户数
     *
     * @return int
     */
    public static function userCount()
    {
        try {
            return max(0, (int) UserManager::count());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 今日调用次数（按 apilog.createtime 自然日）
     *
     * @return int
     */
    public static function todayCallCount()
    {
        if (!class_exists('ApiLogManager')) {
            return 0;
        }
        return ApiLogManager::countToday();
    }
}
