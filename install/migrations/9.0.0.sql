-- ApiNexus 9.0.0：接口反馈表

CREATE TABLE IF NOT EXISTS `{prefix}feedback` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `apiid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联接口ID',
  `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '反馈用户ID',
  `content` text NOT NULL COMMENT '反馈内容',
  `reply` text COMMENT '处理回复',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态：0待处理 1已处理',
  `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `updatetime` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_apiid` (`apiid`),
  KEY `idx_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='接口反馈';
