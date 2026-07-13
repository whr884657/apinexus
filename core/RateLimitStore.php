<?php
/**
 * 文件：core/RateLimitStore.php
 * 作用：发信/操作频率限制（优先 Redis，降低 MySQL 高频写入；不可用时回退 MySQL）
 */

class RateLimitStore
{
    const RETENTION_SECONDS = 86400;

    /**
     * @return string
     */
    private static function table()
    {
        return Database::table('mail_code_rate_log');
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
    private static function limitKey($bucket)
    {
        return hash('sha256', (string) $bucket);
    }

    /**
     * @param string $bucket
     * @param int    $windowSeconds
     * @return string
     */
    private static function redisWindowKey($bucket, $windowSeconds)
    {
        $windowSeconds = max(1, (int) $windowSeconds);
        $slot = (int) floor(time() / $windowSeconds);
        return 'rl:' . self::limitKey($bucket) . ':' . $slot;
    }

    /**
     * @param string $bucket
     * @return string
     */
    private static function redisLastKey($bucket)
    {
        return 'rl:last:' . self::limitKey($bucket);
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
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `created_at` < ? LIMIT 2000');
            $stmt->execute(array($threshold));
        } catch (Exception $e) {
            // 清理失败不影响主流程
        }
    }

    /**
     * @param string $bucket
     * @param int    $windowSeconds
     * @return int
     */
    public static function countHits($bucket, $windowSeconds)
    {
        if (RedisCache::enabled()) {
            try {
                return RedisService::withClient(function (Redis $redis) use ($bucket, $windowSeconds) {
                    $key = RedisService::buildKey(self::redisWindowKey($bucket, $windowSeconds));
                    return (int) $redis->get($key);
                });
            } catch (Exception $e) {
                // 回退 MySQL
            }
        }

        if (!self::isReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $since = time() - (int) $windowSeconds;
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `limit_key` = ? AND `created_at` >= ?'
            );
            $stmt->execute(array(self::limitKey($bucket), $since));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param string $bucket
     * @return int
     */
    public static function secondsSinceLastHit($bucket)
    {
        if (RedisCache::enabled()) {
            try {
                return RedisService::withClient(function (Redis $redis) use ($bucket) {
                    $key = RedisService::buildKey(self::redisLastKey($bucket));
                    $last = (int) $redis->get($key);
                    if ($last <= 0) {
                        return -1;
                    }
                    return time() - $last;
                });
            } catch (Exception $e) {
                // 回退 MySQL
            }
        }

        if (!self::isReady()) {
            return -1;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT MAX(`created_at`) FROM `' . self::table() . '` WHERE `limit_key` = ?'
            );
            $stmt->execute(array(self::limitKey($bucket)));
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
     * @param string $bucket
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @param bool   $record
     * @return bool
     */
    public static function allow($bucket, $windowSeconds, $maxAttempts, $record = true)
    {
        $count = self::countHits($bucket, $windowSeconds);
        if ($count >= $maxAttempts) {
            return false;
        }

        if ($record) {
            self::recordHit($bucket, $windowSeconds);
        }

        return true;
    }

    /**
     * @param string $bucket
     * @param int    $windowSeconds
     * @return void
     */
    public static function recordHit($bucket, $windowSeconds = 3600)
    {
        if (RedisCache::enabled()) {
            try {
                RedisService::withClient(function (Redis $redis) use ($bucket, $windowSeconds) {
                    $windowSeconds = max(1, (int) $windowSeconds);
                    $winKey = RedisService::buildKey(self::redisWindowKey($bucket, $windowSeconds));
                    $lastKey = RedisService::buildKey(self::redisLastKey($bucket));
                    $count = (int) $redis->incr($winKey);
                    if ($count === 1) {
                        $redis->expire($winKey, $windowSeconds + 60);
                    }
                    $redis->setex($lastKey, self::RETENTION_SECONDS, (string) time());
                });
                return;
            } catch (Exception $e) {
                // 回退 MySQL
            }
        }

        if (!self::isReady()) {
            return;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '` (`limit_key`, `created_at`) VALUES (?, ?)'
            );
            $stmt->execute(array(self::limitKey($bucket), time()));
            self::maybeCleanup();
        } catch (Exception $e) {
            // 写入失败时不阻断业务
        }
    }
}
