-- ApiNexus 7.0.0：公告与文章共用内容表

CREATE TABLE IF NOT EXISTS `{prefix}content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `kind` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型：0公告 1文章',
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '标题',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `body` mediumtext NOT NULL COMMENT '正文Markdown',
  `cover` varchar(500) NOT NULL DEFAULT '' COMMENT '封面图链接（文章用，公告可空）',
  `ispinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶：0否 1是',
  `ispopup` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否弹窗：0否 1是（公告）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0草稿 1已发布 2下架',
  `userid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '发布者用户ID',
  `views` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '阅读量',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（越小越前）',
  `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_kind_status_id` (`kind`, `status`, `id`),
  KEY `idx_kind_pin_id` (`kind`, `ispinned`, `id`),
  KEY `idx_kind_popup` (`kind`, `ispopup`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告与文章共用内容表';
