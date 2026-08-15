-- ApiNexus 13.26.7
-- 1) 积分余额归零 / 充值成功邮件通知开关（幂等 INSERT IGNORE）
-- 2) 用户近 7 日聚合 JSON（user.stat7）；密钥累计消耗（apikey.pointsspent）
-- 3) apilog 按用户列表复合索引（仅目录，不加业务列）
-- 安全：禁止扫 apilog 回填 stat7；密钥消耗仅按 orders.keyid 回填；勿改 points/role 等无关列

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_points_zero', '1'),
('mail_notify_recharge_success', '1');

ALTER TABLE `{prefix}user`
    ADD COLUMN `stat7` mediumtext NULL COMMENT '近7日调用聚合JSON（按日分桶，供用户控制台）' AFTER `keycalls`;

ALTER TABLE `{prefix}apikey`
    ADD COLUMN `pointsspent` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '该密钥累计消耗积分（接口扣费合计）' AFTER `calls`;

ALTER TABLE `{prefix}apilog`
    ADD KEY `idx_userid_id` (`userid`, `id`),
    ADD KEY `idx_userid_createtime` (`userid`, `createtime`);

-- 历史密钥消耗：API 扣减 − 调用退回（仅有 keyid）；管理员无 key 流水不计入
UPDATE `{prefix}apikey` k
LEFT JOIN (
    SELECT `keyid` AS kid,
           COALESCE(SUM(
               CASE
                   WHEN `direct` = 0 AND `kind` = 0 THEN `amount`
                   WHEN `direct` = 1 AND `kind` = 4 THEN -`amount`
                   ELSE 0
               END
           ), 0) AS spent
    FROM `{prefix}orders`
    WHERE `status` = 1 AND `keyid` > 0
    GROUP BY `keyid`
) o ON o.kid = k.`id`
SET k.`pointsspent` = GREATEST(0, COALESCE(o.spent, 0));
