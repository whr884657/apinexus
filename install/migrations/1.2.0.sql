-- ApiNexus 1.2.0：注册邮箱策略、移除多域名功能

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('register_policy', '{"email_suffixes":[]}')
ON DUPLICATE KEY UPDATE `key` = `key`;

DELETE FROM `{prefix}config` WHERE `key` IN ('primary_domain', 'bound_domains');

DROP TABLE IF EXISTS `{prefix}domain`;
