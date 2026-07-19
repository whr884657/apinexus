-- ApiNexus 4.4.0
-- 友情链接表

CREATE TABLE IF NOT EXISTS `{prefix}link` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `name` varchar(50) NOT NULL COMMENT '网站名称',
    `siteurl` varchar(255) NOT NULL COMMENT '网站链接',
    `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '头像/图标URL',
    `description` varchar(200) NOT NULL DEFAULT '' COMMENT '网站简介',
    `contact` varchar(100) NOT NULL DEFAULT '' COMMENT '联系方式',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待审 1通过 2拒绝',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（越小越前）',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请/创建时间',
    `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='友情链接';
