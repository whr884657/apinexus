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
        return Database::table('mailrate');
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
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `createtime` < ? LIMIT 2000');
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
                return RedisService::withClient(function ($redis) use ($bucket, $windowSeconds) {
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
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `limitkey` = ? AND `createtime` >= ?'
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
                return RedisService::withClient(function ($redis) use ($bucket) {
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
                'SELECT MAX(`createtime`) FROM `' . self::table() . '` WHERE `limitkey` = ?'
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
     * Redis 内原子检查并占用一个窗口额度。
     *
     * @param string $bucket
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @param bool   $record
     * @return bool
     * @throws Exception
     */
    private static function redisAllow($bucket, $windowSeconds, $maxAttempts, $record)
    {
        $windowSeconds = max(1, (int) $windowSeconds);
        $winKey = RedisService::buildKey(self::redisWindowKey($bucket, $windowSeconds));
        $lastKey = RedisService::buildKey(self::redisLastKey($bucket));
        $script = <<<'LUA'
local current = tonumber(redis.call('GET', KEYS[1]) or '0')
if current >= tonumber(ARGV[1]) then
    return 0
end
if ARGV[2] == '1' then
    current = redis.call('INCR', KEYS[1])
    if current == 1 then
        redis.call('EXPIRE', KEYS[1], tonumber(ARGV[3]) + 60)
    end
    redis.call('SETEX', KEYS[2], tonumber(ARGV[4]), ARGV[5])
end
return 1
LUA;
        $args = array(
            $winKey,
            $lastKey,
            (string) max(0, (int) $maxAttempts),
            $record ? '1' : '0',
            (string) $windowSeconds,
            (string) self::RETENTION_SECONDS,
            (string) time(),
        );
        $result = RedisService::withClient(function ($redis) use ($script, $args) {
            if (!is_object($redis) || !method_exists($redis, 'eval')) {
                throw new Exception('Redis eval unavailable');
            }
            return $redis->eval($script, $args, 2);
        });
        if ($result === false || $result === null) {
            throw new Exception('Redis eval failed');
        }
        return (int) $result === 1;
    }

    /**
     * MySQL 回退：用连接级命名锁包住检查与插入，避免 check/record 竞态。
     *
     * @param string $bucket
     * @param int    $windowSeconds
     * @param int    $maxAttempts
     * @param bool   $record
     * @return bool
     */
    private static function mysqlAllow($bucket, $windowSeconds, $maxAttempts, $record)
    {
        if (!self::isReady()) {
            return true;
        }
        $pdo = Database::connect();
        $limitKey = self::limitKey($bucket);
        $since = time() - (int) $windowSeconds;
        if (!$record) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `limitkey` = ? AND `createtime` >= ?'
            );
            $stmt->execute(array($limitKey, $since));
            return (int) $stmt->fetchColumn() < (int) $maxAttempts;
        }

        $lockName = 'vs_rl:' . substr(hash('sha256', $limitKey . ':' . (int) $windowSeconds), 0, 56);
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $lockStmt->execute(array($lockName));
        if ((int) $lockStmt->fetchColumn() !== 1) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::table() . '` WHERE `limitkey` = ? AND `createtime` >= ?'
            );
            $stmt->execute(array($limitKey, $since));
            if ((int) $stmt->fetchColumn() >= (int) $maxAttempts) {
                return false;
            }
            $insert = $pdo->prepare(
                'INSERT INTO `' . self::table() . '` (`limitkey`, `createtime`) VALUES (?, ?)'
            );
            $insert->execute(array($limitKey, time()));
            return true;
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute(array($lockName));
            } catch (Exception $eRelease) {
                // 连接释放时 MySQL 会自动释放命名锁。
            }
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
        $windowSeconds = max(1, (int) $windowSeconds);
        $maxAttempts = max(0, (int) $maxAttempts);
        if (RedisCache::enabled()) {
            try {
                return self::redisAllow($bucket, $windowSeconds, $maxAttempts, $record);
            } catch (Exception $e) {
                // Redis 不可用时使用同样具备原子锁的 MySQL 路径。
            }
        }
        return self::mysqlAllow($bucket, $windowSeconds, $maxAttempts, $record);
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
                RedisService::withClient(function ($redis) use ($bucket, $windowSeconds) {
                    $windowSeconds = max(1, (int) $windowSeconds);
                    $winKey = RedisService::buildKey(self::redisWindowKey($bucket, $windowSeconds));
                    $lastKey = RedisService::buildKey(self::redisLastKey($bucket));
                    $count = (int) $redis->incr($winKey);
                    if ($count === 1) {
                        $redis->expire($winKey, $windowSeconds + 60);
                    }
                    $redis->setex($lastKey, self::RETENTION_SECONDS, (string) time());
                });
                RedisCache::maintainKeyspace();
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
                'INSERT INTO `' . self::table() . '` (`limitkey`, `createtime`) VALUES (?, ?)'
            );
            $stmt->execute(array(self::limitKey($bucket), time()));
            self::maybeCleanup();
        } catch (Exception $e) {
            // 写入失败时不阻断业务
        }
    }
}
