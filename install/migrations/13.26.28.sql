-- ApiNexus 13.26.28
-- 积分不足调用邮件通知开关（幂等；无表结构变更）

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_points_insufficient', '1');
