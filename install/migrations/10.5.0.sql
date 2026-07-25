-- ApiNexus 10.5.0：接口 QPM（每分钟请求上限）
ALTER TABLE `{prefix}api`
  ADD COLUMN `qpm` int unsigned NOT NULL DEFAULT 0 COMMENT '每分钟请求上限：0不限制；大于0为每分钟最大次数' AFTER `needkey`;
