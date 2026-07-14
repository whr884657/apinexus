-- misc-api 3.6.0：重建 API 接口表（清除旧审核结构，改为后台接口列表字段）

DROP TABLE IF EXISTS `{prefix}api`;

CREATE TABLE `{prefix}api` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '接口名称',
    `description` text COMMENT '接口描述',
    `endpoint` varchar(500) NOT NULL DEFAULT '' COMMENT '接口地址',
    `method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT 'GET|POST',
    `request_params` mediumtext COMMENT '请求参数（JSON 数组）',
    `response_example` mediumtext COMMENT '返回参数示例',
    `doc_normal` mediumtext COMMENT '普通文档',
    `doc_ai` mediumtext COMMENT 'AI 文档',
    `call_count` bigint unsigned NOT NULL DEFAULT 0 COMMENT '请求次数',
    `require_key` tinyint(1) NOT NULL DEFAULT 0 COMMENT '密钥要求：0不需要 1必须 2可选',
    `status` varchar(20) NOT NULL DEFAULT 'normal' COMMENT 'normal|disabled|maintenance',
    `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标（链接或本地 SVG 路径）',
    `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称（对接 category.name，可选）',
    `user_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建者用户 ID',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_category` (`category`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 接口表';
