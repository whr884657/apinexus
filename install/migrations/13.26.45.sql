-- ApiNexus 13.26.45
-- 1) 充值订单支付成功通知管理员（邮件开关）
-- 2) 签到：user.lastcheckin 取代 checkin 表（回填后 DROP）
-- 3) 聚合登录：user.aggmap（JSON 绑定映射）
-- 4) 人机验证场景：文章评论 / 申请友链（默认关）
-- 5) 充值说明 Markdown（套餐 / 自定义 / 卡密；空则用户端不展示）
-- 6) 自定义金额阶梯优惠 pay_custom_bonus（JSON，默认 []）
-- 7) 控制台日聚合：statday.pointscost（当日积分消耗合计）
-- 8) 调用日志搜索索引：idx_apiname / idx_apikey / idx_userid_apiid|ip|apiname
-- 注：主题设置迁 core/data/{id}/theme.db 由 PHP ensureThemeSettingsLocal45 完成（非本 SQL）

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_order_admin', '1')
ON DUPLICATE KEY UPDATE `key` = `key`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('captcha_on_comment', '0'),
('captcha_on_applylink', '0')
ON DUPLICATE KEY UPDATE `key` = `key`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('pay_tip_package', ''),
('pay_tip_custom', ''),
('pay_tip_cardkey', ''),
('pay_custom_bonus', '[]')
ON DUPLICATE KEY UPDATE `key` = `key`;

ALTER TABLE `{prefix}user`
  ADD COLUMN `lastcheckin` date DEFAULT NULL COMMENT '最近签到日期（空表示从未签到；与服务器当日比较判定今日是否已签）' AFTER `lastlogin`;

-- 从旧 checkin 表回填各用户最近签到日（表已不存在时本句会失败；仅旧站升级路径会跑到此处）
UPDATE `{prefix}user` u
INNER JOIN (
  SELECT `userid`, MAX(`checkindate`) AS `d`
  FROM `{prefix}checkin`
  GROUP BY `userid`
) c ON c.`userid` = u.`id`
SET u.`lastcheckin` = c.`d`
WHERE u.`lastcheckin` IS NULL OR u.`lastcheckin` < c.`d`;

DROP TABLE IF EXISTS `{prefix}checkin`;

ALTER TABLE `{prefix}user`
  ADD COLUMN `aggmap` text COMMENT '聚合登录绑定JSON（键为内部id，值为social_uid）' AFTER `giteeid`;

-- 7) 控制台日聚合：当日积分消耗合计（次数 pointscalls ≠ 消耗量 pointscost）
ALTER TABLE `{prefix}statday`
  ADD COLUMN `pointscost` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '当日积分消耗合计（已扣积分金额）' AFTER `pointscalls`;

-- 8) 调用日志单字段搜索索引（对齐管理端/用户端 q_field；前缀匹配）
ALTER TABLE `{prefix}apilog`
  ADD KEY `idx_apiname` (`apiname`),
  ADD KEY `idx_apikey` (`apikey`(64)),
  ADD KEY `idx_userid_apiid` (`userid`, `apiid`),
  ADD KEY `idx_userid_ip` (`userid`, `ip`),
  ADD KEY `idx_userid_apiname` (`userid`, `apiname`);
