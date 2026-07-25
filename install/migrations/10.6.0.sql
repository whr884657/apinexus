-- ApiNexus 10.6.0：积分流水大规模搜索辅助索引（幂等）
-- 已有 idx_status_id / idx_userid_status_id / idx_direct_kind_status_id 时跳过重复创建

-- 备注前缀辅助（兜底短词搜索；主路径已改为 userid/direct+kind 精确过滤）
ALTER TABLE `{prefix}orders`
  ADD INDEX `idx_remark_prefix` (`remark`(32));
