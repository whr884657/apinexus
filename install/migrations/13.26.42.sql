-- 13.26.42：积分卡密表 cardkey（唯一码 + 状态/列表索引）

CREATE TABLE IF NOT EXISTS `{prefix}cardkey` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `code` char(20) NOT NULL COMMENT '卡密串（20位字母数字大小写）',
  `points` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '兑换积分数量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1未使用(库存) 2已使用 3作废 4已发放(API出库,仍可兑)',
  `userid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '兑换用户ID；未兑为0',
  `adminid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '生成管理员ID；对接 API 生成为0',
  `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '生成时间',
  `usetime` datetime DEFAULT NULL COMMENT '兑换时间；未兑为空',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_id` (`status`, `id`),
  KEY `idx_status_points` (`status`, `points`, `id`),
  KEY `idx_createtime_id` (`createtime`, `id`),
  KEY `idx_userid_id` (`userid`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分卡密';

-- 系统密钥（PHP 迁自旧 apilog_cron_key；业务只认 system_api_key）
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('system_api_key', '');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('apilog_purge_enabled', '0');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('cardkey_api_enabled', '0');
