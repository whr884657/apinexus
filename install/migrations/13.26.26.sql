-- 13.26.26：备案号按访问域名匹配（固定两槽）

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('site_domain', ''),
('site_domain1', ''),
('site_icp1', ''),
('site_gongan1', '')
ON DUPLICATE KEY UPDATE `key` = `key`;
