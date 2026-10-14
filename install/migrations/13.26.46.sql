-- ApiNexus 13.26.46 · 本文件仅 config 种子与旧键清理
--
-- 【为何这里不要写 apilog ALTER】
-- 1) 升级路径 executeFile('13.26.46') 故意不跑本文件里的表结构语句，改由
--    DatabaseMigrator::ensureApilogExtended46() 幂等完成（缺哪列加哪列、合并一条、
--    优先 ALGORITHM=INPLACE, LOCK=NONE；Duplicate column 1060 当成功忽略）。
-- 2) 加列只改表结构：DEFAULT ''，业务不会对历史日志做 UPDATE「补参数」。
--    MySQL 5.7 大表 ALTER 仍可能短暂占用元数据锁；合并成一次比连跑三次轻得多。
-- 3) 慢查询日志里的 SELECT COUNT(*) FROM apilog 不是加列产生的，而是后台「调用日志」
--    列表统计（ApiLogManager）；升级窗口请少开该页，避免与 ALTER 抢锁。
--
-- 从 13.26.45 → 13.26.46：勿在本文件追加多列 ALTER / DROP；结构只走 PHP ensure*。

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
