<?php
/**
 * 文件：core/RateLimitStore.php
 * 作用：基于 MySQL 的安全频率限制存储（替代本地 JSON 文件）
 */

class RateLimitStore
{
    const RETENTION_SECONDS = 86400;

    /**
     * @return string
     */
    private static function table()
    {
        return Database::table('security_rate_hit');
    }

    /**
     * @return bool
     */
    private static function isReady()
    {
        if (!InstallChecker::isInstalled()) {
            return false;
        }
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            $ready = ($stmt && $stmt->fetch(PDO::FETCH_NUM));
        } catch (Exception $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * @param string $bucket
     * @return string
     */
    private static function bucketKey($bucket)
    {
        return hash('sha256', (string) $bucket);
    }

    /**
     * @return void
     */
    private static function maybeCleanup()
    {
        if (!self::isReady() || random_int(1, 100) > 5) {
            return;
        }
        try {
            $pdo = Database::connect();
            $threshold = time() - self::RETENTION_SECONDS;
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `hit_at` < ? LIMIT 2000');
            $stmt->execute(array($threshold));
        } catch (Exception $e) {
            // 清理失败不影响主流程
        }
    }

    /**
     * 窗口内命中次数
     *
     * @param string $bucket
     * @param int    $windowSeconds
     * @return int
     */
    public static function countHits($bucket, $windowSeconds)
    {
        if (!self::isReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $since = time() - (int) $windowSeconds;
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `bucket` = ? AND `hit_at` >= ?'
            );
            $stmt->execute(array(self::bucketKey($bucket), $since));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 距上次命中经过的秒数（无记录返回 -1）
     *
     * @param string $bucket
     * @return int
     */
    public static function secondsSinceLastHit($bucket)
    {
        if (!self::isReady()) {
            return -1;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT MAX(`hit_at`) FROM `' . self::table() . '` WHERE `bucket` = ?'
            );
            $stmt->execute(array(self::bucketKey($bucket)));
            $last = (int) $stmt->fetchColumn();
            if ($last <= 0) {
                return -1;
            }
            return time() - $last;
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * 检查是否允许并在需要时记录
     *
     * @param string $bucket
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @param bool   $record
     * @return bool
     */
    public static function allow($bucket, $windowSeconds, $maxAttempts, $record = true)
    {
        if (!self::isReady()) {
            return true;
        }

        $count = self::countHits($bucket, $windowSeconds);
        if ($count >= $maxAttempts) {
            return false;
        }

        if ($record) {
            self::recordHit($bucket);
        }

        return true;
    }

    /**
     * 记录一次命中
     *
     * @param string $bucket
     * @return void
     */
    public static function recordHit($bucket)
    {
        if (!self::isReady()) {
            return;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '` (`bucket`, `hit_at`) VALUES (?, ?)'
            );
            $stmt->execute(array(self::bucketKey($bucket), time()));
            self::maybeCleanup();
        } catch (Exception $e) {
            // 写入失败时不阻断业务
        }
    }
}
