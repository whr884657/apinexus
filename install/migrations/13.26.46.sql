-- ApiNexus 13.26.46 · 本文件仅 config 种子与旧键清理
-- 表结构变更（apikey.scopejson、apilog 扩列、feedback.kind 等）由 DatabaseMigrator PHP 幂等完成，勿在此写多列 ALTER

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES (
  'sysapion',
  '{"apilogarchive":0,"apiquery":0,"apirank":0,"cardkey":0,"checkin":0,"sitestats":0,"userinfo":0}'
);

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('uabrand', 'ApiNexus');

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES (
  'mailsmtp',
  '{"mode":0,"list":[]}'
);

UPDATE `{prefix}config` AS n INNER JOIN `{prefix}config` AS o ON o.`key` = 'outbound_ua_brand' SET n.`value` = o.`value` WHERE n.`key` = 'uabrand';

DELETE FROM `{prefix}config` WHERE `key` IN (
  'apiquery_api_enabled',
  'apirank_api_enabled',
  'cardkey_api_enabled',
  'apilog_api_enabled',
  'outbound_ua_brand'
);

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('cfid', '');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('cfkey', '');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('captcha_pepper', '');

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('mail_notify_dev_apply', '1');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('mail_notify_dev_pass', '1');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('mail_notify_dev_fail', '1');

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES (
  'ops_site_promo',
  '{"mode":"off","percent_pay":null,"expire_at":"","label":"","updated_at":"","updated_by":0}'
);

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES (
  'sysapirq',
  '{"apis":{"apilogarchive":{"qpm":0,"qps":0},"apiquery":{"qpm":0,"qps":0},"apirank":{"qpm":0,"qps":0},"cardkey":{"qpm":0,"qps":0},"checkin":{"qpm":0,"qps":0},"sitestats":{"qpm":0,"qps":0},"userinfo":{"qpm":0,"qps":0}}}'
);
