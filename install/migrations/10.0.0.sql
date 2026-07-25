-- 文章评论表（v10.0.0；字段以 database.sql / 10.1.0 迁移为准）
CREATE TABLE IF NOT EXISTS `{prefix}comment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `contentid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联文章ID',
  `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '登录用户ID（0表示访客）',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  `email` varchar(100) NOT NULL COMMENT '评论者邮箱（必填）',
  `body` text NOT NULL COMMENT '评论正文',
  `reply` text COMMENT '管理员回复',
  `ispinned` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '是否置顶：0否 1是',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态：0待审核 1已通过 2已拒绝',
  `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `updatetime` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_contentid` (`contentid`),
  KEY `idx_status` (`status`),
  KEY `idx_ispinned` (`ispinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章评论';
