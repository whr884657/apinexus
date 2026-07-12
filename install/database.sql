-- ============================================================
-- misc-api 数据库结构定义（安装时执行）
-- 说明：{prefix} 为表前缀占位符，安装时自动替换
-- ============================================================

-- 管理员表
CREATE TABLE IF NOT EXISTS `{prefix}admin` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` char(32) NOT NULL COMMENT 'password hash',
    `email` varchar(100) NOT NULL,
    `avatar_url` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    `bound_user_id` int(10) unsigned DEFAULT NULL COMMENT '绑定的用户账号ID（后台发布内容身份）',
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_bound_user_id` (`bound_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- 用户表
CREATE TABLE IF NOT EXISTS `{prefix}user` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` char(32) NOT NULL COMMENT 'password hash',
    `email` varchar(100) NOT NULL,
    `avatar_url` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    `oauth_qq_openid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ OpenID',
    `oauth_gitee_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'Gitee 用户 ID',
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 系统配置表
CREATE TABLE IF NOT EXISTS `{prefix}config` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `key` varchar(50) NOT NULL,
    `value` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- 初始系统配置
INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('site_name', 'misc-api'),
('site_description', '基于 PHP + MySQL 的轻量级 Web 管理系统'),
('site_keywords', 'misc-api,PHP,MySQL,管理系统'),
('site_favicon', ''),
('site_logo', ''),
('site_icp', ''),
('site_gongan', ''),
('register_policy', '{"email_suffixes":[]}'),
('oauth_config', '{"qq":{"enabled":false,"app_id":"","app_key":""},"gitee":{"enabled":false,"client_id":"","client_secret":""}}'),
('mail_enabled', '0'),
('mail_smtp_host', ''),
('mail_smtp_port', '465'),
('mail_smtp_user', ''),
('mail_smtp_pass', ''),
('mail_smtp_secure', 'ssl'),
('mail_from_email', ''),
('mail_from_name', 'misc-api'),
('frontend_theme', 'default');

-- 安全频率限制命中记录（v2.10.2+）
CREATE TABLE IF NOT EXISTS `{prefix}security_rate_hit` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `bucket` varchar(64) NOT NULL COMMENT 'bucket sha256',
    `hit_at` int unsigned NOT NULL COMMENT 'unix timestamp',
    PRIMARY KEY (`id`),
    KEY `idx_bucket_hit_at` (`bucket`, `hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安全频率限制命中记录';
