-- misc-api 2.12.0：API 接口表（含审核状态）

CREATE TABLE IF NOT EXISTS `{prefix}api` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '提交用户 ID',
    `name` varchar(100) NOT NULL COMMENT '接口名称',
    `slug` varchar(100) NOT NULL DEFAULT '' COMMENT '接口标识',
    `description` text COMMENT '接口描述',
    `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类',
    `endpoint` varchar(500) NOT NULL DEFAULT '' COMMENT '接口地址',
    `method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT '请求方法',
    `type` varchar(10) NOT NULL DEFAULT 'api' COMMENT 'api|tapi',
    `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected|offline',
    `reject_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '拒绝原因',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 接口表';
