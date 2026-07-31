-- 13.22.2：用户表缓存累计消耗积分、累计密钥调用（控制台 KPI 免实时扫库）
-- 回填口径与控制台一致：
--   pointsspent ← orders 已完成扣减（direct=0,status=1）SUM(amount)
--   keycalls    ← apikey.calls 按用户 SUM（成功且有效密钥累加）
-- 无需扫描 apilog（千万级）；orders / apikey 聚合为集合更新，凌晨低峰可一次跑完。

ALTER TABLE `{prefix}user`
    ADD COLUMN `pointsspent` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '累计消耗积分（已完成扣减流水合计）' AFTER `points`,
    ADD COLUMN `keycalls` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '累计密钥调用次数（成功且有效密钥）' AFTER `pointsspent`;

-- 历史积分消耗：按用户一次汇总写回
UPDATE `{prefix}user` u
LEFT JOIN (
    SELECT `userid` AS uid, COALESCE(SUM(`amount`), 0) AS spent
    FROM `{prefix}orders`
    WHERE `direct` = 0 AND `status` = 1
    GROUP BY `userid`
) o ON o.uid = u.`id`
SET u.`pointsspent` = COALESCE(o.spent, 0);

-- 历史密钥调用：按用户汇总各令牌 calls
UPDATE `{prefix}user` u
LEFT JOIN (
    SELECT `userid` AS uid, COALESCE(SUM(`calls`), 0) AS calls
    FROM `{prefix}apikey`
    GROUP BY `userid`
) k ON k.uid = u.`id`
SET u.`keycalls` = COALESCE(k.calls, 0);
