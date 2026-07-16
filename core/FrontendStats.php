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
        return UserManager::count();
    }

    /**
     * 今日调用次数（按 apilog.createtime 自然日）
     *
     * @return int
     */
    public static function todayCallCount()
    {
        if (!ApiStats::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apilog');
            // createtime 由 ApiStats 写入 NOW()（日期时间）
            $stmt = $pdo->query(
                'SELECT COUNT(*) FROM `' . $table . '` WHERE DATE(`createtime`) = CURDATE()'
            );
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}
