-- misc-api 2.15.0：API 接口分类表

CREATE TABLE IF NOT EXISTS `{prefix}api_category` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL COMMENT '分类名称',
    `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序（数值越小越靠前）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`),
    KEY `idx_sort_status` (`sort_order`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 接口分类';
