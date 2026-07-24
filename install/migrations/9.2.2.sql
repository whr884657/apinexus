-- ApiNexus 9.2.2：新反馈通知管理员邮件开关
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_feedback_admin', '1');
