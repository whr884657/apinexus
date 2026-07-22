-- ApiNexus 5.8.0：apilog 复合索引 + 冷热归档配置（SQLite 冷库）
-- 说明：加速时间窗列表与 COUNT；热天数 + 归档开关 + 计划任务密钥

ALTER TABLE `{prefix}apilog`
  ADD INDEX `idx_createtime_id` (`createtime`, `id`),
  ADD INDEX `idx_ok_createtime` (`ok`, `createtime`),
  ADD INDEX `idx_apiid_createtime` (`apiid`, `createtime`);

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_query_days', '7')
ON DUPLICATE KEY UPDATE `value` = `value`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_hot_days', '30')
ON DUPLICATE KEY UPDATE `value` = `value`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_archive_enabled', '1')
ON DUPLICATE KEY UPDATE `value` = `value`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_cron_key', '')
ON DUPLICATE KEY UPDATE `value` = `value`;

DELETE FROM `{prefix}config` WHERE `key` = 'apilog_keep_days';
