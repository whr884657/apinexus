-- ApiNexus 13.26.40
-- 令牌配额 / 配额已用 / 配额回落 / 失效时间 + 邮件通知开关

ALTER TABLE `{prefix}apikey`
  ADD COLUMN `quota` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '令牌配额积分：0不限制；>0为该密钥可消耗上限' AFTER `pointsspent`,
  ADD COLUMN `quotaused` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '本配额已消耗积分（相对quota；与终身pointsspent分开）' AFTER `quota`,
  ADD COLUMN `quotafallback` tinyint(1) NOT NULL DEFAULT 0 COMMENT '配额用尽后：0禁止继续调用 1改扣账户总积分' AFTER `quotaused`,
  ADD COLUMN `expiretime` datetime DEFAULT NULL COMMENT '失效时间：空表示永不过期' AFTER `quotafallback`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_key_quota', '1')
ON DUPLICATE KEY UPDATE `key` = `key`;
