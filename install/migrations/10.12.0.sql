-- ApiNexus 10.12.0：控制台日聚合表（滚动固定 30 天）
CREATE TABLE IF NOT EXISTS `{prefix}statday` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `statdate` date NOT NULL COMMENT '统计日（东八区日历日）',
  `calls` int unsigned NOT NULL DEFAULT 0 COMMENT '当日总调用次数',
  `okcount` int unsigned NOT NULL DEFAULT 0 COMMENT '当日成功次数',
  `failcount` int unsigned NOT NULL DEFAULT 0 COMMENT '当日失败次数',
  `guestcalls` int unsigned NOT NULL DEFAULT 0 COMMENT '当日游客调用（无密钥且未扣积分）',
  `keycalls` int unsigned NOT NULL DEFAULT 0 COMMENT '当日密钥调用（有密钥未扣积分）',
  `pointscalls` int unsigned NOT NULL DEFAULT 0 COMMENT '当日积分调用（已扣积分）',
  `topjson` mediumtext COMMENT '当日TOP接口JSON：[{apiid,rank,calls},...]',
  `updatetime` datetime DEFAULT NULL COMMENT '最后刷新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_statdate` (`statdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='控制台按日调用聚合（滚动30天）';
