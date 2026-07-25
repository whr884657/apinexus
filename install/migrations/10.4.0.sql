-- ApiNexus 10.4.0：每日签到表 + 注册赠送/签到配置 + orders.kind 注释扩展
CREATE TABLE IF NOT EXISTS `{prefix}checkin` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `checkindate` date NOT NULL COMMENT '签到日期（按天唯一）',
  `points` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '本次签到获得积分',
  `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '签到时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_userid_date` (`userid`, `checkindate`),
  KEY `idx_checkindate` (`checkindate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户每日签到记录';

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('register_gift_enabled', '0'),
('register_gift_points', '100'),
('checkin_enabled', '0'),
('checkin_points_min', '10'),
('checkin_points_max', '30');

ALTER TABLE `{prefix}orders`
  MODIFY COLUMN `kind` tinyint(1) NOT NULL COMMENT '类型：增加时0用户充值1管理员加款2注册赠送3每日签到；减少时0API调用1管理员扣款2AI调用(预留)';
