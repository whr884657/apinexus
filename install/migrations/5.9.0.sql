-- ApiNexus 5.9.0：订单/积分列表查询索引 + 冷库分片条数配置
-- 说明：支撑时间窗 + keyset；积分流水不做冷热归档，仅优化在线查询

ALTER TABLE `{prefix}orders`
  ADD INDEX `idx_createtime_id` (`createtime`, `id`),
  ADD INDEX `idx_userid_status_id` (`userid`, `status`, `id`),
  ADD INDEX `idx_direct_kind_status_id` (`direct`, `kind`, `status`, `id`),
  ADD INDEX `idx_status_id` (`status`, `id`);

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_shard_rows', '5000')
ON DUPLICATE KEY UPDATE `value` = `value`;
