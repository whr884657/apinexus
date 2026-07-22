-- ApiNexus 5.8.0：apilog 复合索引 + 默认查询/保留天数配置
-- 说明：加速时间窗列表与 COUNT；禁止默认无条件全表扫描

ALTER TABLE `{prefix}apilog`
  ADD INDEX `idx_createtime_id` (`createtime`, `id`),
  ADD INDEX `idx_ok_createtime` (`ok`, `createtime`),
  ADD INDEX `idx_apiid_createtime` (`apiid`, `createtime`);

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_query_days', '7')
ON DUPLICATE KEY UPDATE `value` = `value`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_keep_days', '30')
ON DUPLICATE KEY UPDATE `value` = `value`;
