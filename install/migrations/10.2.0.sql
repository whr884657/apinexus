-- ApiNexus 10.2.0：文章评论邮件通知开关
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('mail_notify_comment_admin', '1'),
('mail_notify_comment', '1');
