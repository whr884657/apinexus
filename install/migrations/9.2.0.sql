-- ApiNexus 9.2.0：接口反馈处理结果邮件开关（配置项）
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_feedback', '1'),
('mail_notify_link_apply', '1'),
('mail_notify_link_pass', '1');
