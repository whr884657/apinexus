-- ApiNexus 3.11.0
-- 本地路径 / 代理外链 + 邮件通知开关配置

ALTER TABLE `{prefix}api`
  ADD COLUMN `apitype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '接口类型：0本地路径 1代理外链' AFTER `endpoint`,
  ADD COLUMN `targeturl` varchar(500) NOT NULL DEFAULT '' COMMENT '代理上游完整地址（仅代理类型）' AFTER `apitype`,
  ADD COLUMN `proxyslug` varchar(64) NOT NULL DEFAULT '' COMMENT '代理短码（仅代理类型）' AFTER `targeturl`;

ALTER TABLE `{prefix}api`
  ADD KEY `idx_proxyslug` (`proxyslug`),
  ADD KEY `idx_apitype` (`apitype`);

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_submit', '1'),
('mail_notify_pass', '1'),
('mail_notify_fail', '1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
