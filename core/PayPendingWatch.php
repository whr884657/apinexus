<?php
/**
 * 文件：core/PayPendingWatch.php
 * 作用：充值待支付单超时自动取消（惰性过期 + Redis ZSET 顺带弹出；无计划任务、无全表扫）
 */

class PayPendingWatch
{
    /** 待支付超过此时长（秒）自动取消 */
    const TTL_SECONDS = 180;

    /** 单次顺带最多处理条数 */
    const DRAIN_LIMIT = 20;

    /** Redis 逻辑键：score=到期 unix 时间，member=orderno */
    const KEY_ZSET = 'pay:pending:expire';

    /** 顺带弹出互斥锁 */
    const KEY_DRAIN_LOCK = 'pay:pending:drain_lock';

    /**
     * @return int
     */
    public static function ttlSeconds()
    {
        return self::TTL_SECONDS;
    }

    /**
     * 创建待支付成功后登记到期时间
     *
     * @param string $orderno
     * @param int    $createdAt Unix 秒；0=当前
     * @return void
     */
    public static function track($orderno, $createdAt = 0)
    {
        $orderno = trim((string) $orderno);
        if ($orderno === '') {
            return;
        }
        $createdAt = (int) $createdAt;
        if ($createdAt <= 0) {
            $createdAt = time();
        }
        $expireAt = $createdAt + self::TTL_SECONDS;
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($orderno, $expireAt) {
                $redis->zAdd(RedisService::buildKey(self::KEY_ZSET), $expireAt, $orderno);
            });
        } catch (Exception $e) {
            // 登记失败不影响下单；惰性过期仍可兜底
        }
    }

    /**
     * 支付完成 / 主动取消 / 超时取消后移出挂单索引
     *
     * @param string $orderno
     * @return void
     */
    public static function untrack($orderno)
    {
        $orderno = trim((string) $orderno);
        if ($orderno === '') {
            return;
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($orderno) {
                $redis->zRem(RedisService::buildKey(self::KEY_ZSET), $orderno);
            });
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * 单笔惰性过期：待支付且已超时则 cancelPending
     *
     * @param string $orderno
     * @return bool 是否执行了取消
     */
    public static function expireIfDue($orderno)
    {
        $orderno = trim((string) $orderno);
        if ($orderno === '' || !class_exists('OrderManager') || !OrderManager::tableReady()) {
            return false;
        }
        $row = OrderManager::findByOrderNo($orderno);
        if (!$row || !self::isRechargePending($row)) {
            self::untrack($orderno);
            return false;
        }
        if (!self::rowIsOverdue($row)) {
            return false;
        }
        $ok = PointsManager::cancelPending($orderno);
        return $ok;
    }

    /**
     * 按用户清理本人超时待支付充值单（无 Redis 时的降级路径；有索引 userid+status）
     *
     * @param int $userId
     * @param int $limit
     * @return int 取消条数
     */
    public static function expireUserPending($userId, $limit = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !OrderManager::tableReady()) {
            return 0;
        }
        $limit = $limit === null ? self::DRAIN_LIMIT : max(1, min(50, (int) $limit));
        $ttl = self::TTL_SECONDS;
        $n = 0;
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `orderno` FROM `' . OrderManager::table() . '`
                 WHERE `userid` = ? AND `status` = ? AND `direct` = ? AND `kind` = ?
                   AND `createtime` <= DATE_SUB(NOW(), INTERVAL ? SECOND)
                 ORDER BY `id` ASC
                 LIMIT ' . (int) $limit
            );
            $stmt->execute(array(
                $userId,
                OrderManager::STATUS_PENDING,
                OrderManager::DIRECT_INC,
                OrderManager::KIND_RECHARGE,
                $ttl,
            ));
            $list = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($list)) {
                return 0;
            }
            foreach ($list as $orderno) {
                if (PointsManager::cancelPending((string) $orderno)) {
                    $n++;
                }
            }
        } catch (Exception $e) {
            return $n;
        }
        return $n;
    }

    /**
     * 站点流量顺带：弹出 Redis 中已到期的挂单并取消（单次上限 + 短锁）
     *
     * @param int $limit
     * @return int
     */
    public static function drainExpired($limit = null)
    {
        $limit = $limit === null ? self::DRAIN_LIMIT : max(1, min(50, (int) $limit));
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !OrderManager::tableReady()) {
            return 0;
        }
        $n = 0;
        try {
            $orderNos = RedisService::withClient(function ($redis) use ($limit) {
                $lockKey = RedisService::buildKey(self::KEY_DRAIN_LOCK);
                $got = false;
                try {
                    if (defined('Redis::OPT_SERIALIZER')) {
                        // phpredis SET NX EX
                        $got = $redis->set($lockKey, '1', array('nx', 'ex' => 3));
                    }
                } catch (Exception $e) {
                    $got = false;
                }
                if (!$got) {
                    try {
                        $got = $redis->setnx($lockKey, '1');
                        if ($got) {
                            $redis->expire($lockKey, 3);
                        }
                    } catch (Exception $e2) {
                        return array();
                    }
                }
                if (!$got) {
                    return array();
                }
                $zkey = RedisService::buildKey(self::KEY_ZSET);
                $now = time();
                $members = $redis->zRangeByScore($zkey, '-inf', (string) $now, array('limit' => array(0, $limit)));
                if (!is_array($members)) {
                    return array();
                }
                return $members;
            });
        } catch (Exception $e) {
            return 0;
        }
        if (!is_array($orderNos) || $orderNos === array()) {
            return 0;
        }
        foreach ($orderNos as $orderno) {
            $orderno = (string) $orderno;
            if ($orderno === '') {
                continue;
            }
            $row = OrderManager::findByOrderNo($orderno);
            if (!$row || !self::isRechargePending($row)) {
                self::untrack($orderno);
                continue;
            }
            // ZSET 已到期：直接取消（不再二次比对 createtime，避免时钟偏差导致误移出索引）
            if (PointsManager::cancelPending($orderno)) {
                $n++;
            } else {
                self::untrack($orderno);
            }
        }
        return $n;
    }

    /**
     * 已登录用户页 / 后台页入口：顺带弹出 + 清理当前用户超时单
     *
     * @param int $userId 0=仅站点级 drain（后台管理员无对应用户充值时）
     * @return void
     */
    public static function onRequest($userId = 0)
    {
        try {
            self::drainExpired();
            $userId = (int) $userId;
            if ($userId > 0) {
                self::expireUserPending($userId);
            }
        } catch (Exception $e) {
            // 绝不阻断页面
        }
    }

    /**
     * 列表行展示前：若为本站充值待支付且超时，当场取消并改写行状态
     *
     * @param array $row
     * @return array
     */
    public static function applyLazyToRow(array $row)
    {
        if (!self::isRechargePending($row) || !self::rowIsOverdue($row)) {
            return $row;
        }
        $orderno = isset($row['orderno']) ? (string) $row['orderno'] : '';
        if ($orderno === '') {
            return $row;
        }
        if (PointsManager::cancelPending($orderno)) {
            $row['status'] = OrderManager::STATUS_CANCEL;
        }
        return $row;
    }

    /**
     * @param array $row
     * @return bool
     */
    public static function isRechargePending(array $row)
    {
        return (int) $row['status'] === OrderManager::STATUS_PENDING
            && (int) $row['direct'] === OrderManager::DIRECT_INC
            && (int) $row['kind'] === OrderManager::KIND_RECHARGE;
    }

    /**
     * @param array $row
     * @return bool
     */
    public static function rowIsOverdue(array $row)
    {
        $ct = isset($row['createtime']) ? trim((string) $row['createtime']) : '';
        if ($ct === '') {
            return false;
        }
        $ts = strtotime($ct);
        if ($ts === false) {
            return false;
        }
        return (time() - $ts) >= self::TTL_SECONDS;
    }
}
