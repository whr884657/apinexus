-- ApiNexus 3.8.0
-- 1) 全表白字段中文 COMMENT
-- 2) api.status：英文串 → 数字（0正常 1禁用 2维护）
-- 3) api.audit_status：审核（0不通过 1通过；存量/管理员默认通过）

-- ---------- admin ----------
ALTER TABLE `{prefix}admin`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    MODIFY COLUMN `username` varchar(50) NOT NULL COMMENT '管理员用户名',
    MODIFY COLUMN `password` char(32) NOT NULL COMMENT '密码哈希（MD5，非明文）',
    MODIFY COLUMN `email` varchar(100) NOT NULL COMMENT '管理员邮箱',
    MODIFY COLUMN `avatar_url` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    MODIFY COLUMN `bound_user_id` int(10) unsigned DEFAULT NULL COMMENT '绑定的前台用户 ID（后台发布内容所用身份）',
    MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '账号状态：0禁用 1启用',
    MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间';

-- ---------- user ----------
ALTER TABLE `{prefix}user`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    MODIFY COLUMN `username` varchar(50) NOT NULL COMMENT '用户名',
    MODIFY COLUMN `password` char(32) NOT NULL COMMENT '密码哈希（MD5，非明文）',
    MODIFY COLUMN `email` varchar(100) NOT NULL COMMENT '用户邮箱',
    MODIFY COLUMN `avatar_url` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    MODIFY COLUMN `oauth_qq_openid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ 登录 OpenID',
    MODIFY COLUMN `oauth_gitee_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'Gitee 登录用户 ID',
    MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '账号状态：0禁用 1启用',
    MODIFY COLUMN `role` varchar(16) NOT NULL DEFAULT 'user' COMMENT '用户角色：user普通用户 developer开发者',
    MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    MODIFY COLUMN `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间';

-- ---------- config ----------
ALTER TABLE `{prefix}config`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    MODIFY COLUMN `key` varchar(50) NOT NULL COMMENT '配置键名',
    MODIFY COLUMN `value` text COMMENT '配置值（文本/JSON）';

-- ---------- mail_code_rate_log ----------
ALTER TABLE `{prefix}mail_code_rate_log`
    MODIFY COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    MODIFY COLUMN `limit_key` varchar(64) NOT NULL COMMENT '限流键（SHA256）',
    MODIFY COLUMN `created_at` int unsigned NOT NULL COMMENT '命中时间（Unix 时间戳）';

-- ---------- category ----------
ALTER TABLE `{prefix}category`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    MODIFY COLUMN `name` varchar(50) NOT NULL COMMENT '分类名称',
    MODIFY COLUMN `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '分类图标 URL 或本地路径',
    MODIFY COLUMN `description` varchar(255) NOT NULL DEFAULT '' COMMENT '分类描述',
    MODIFY COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（数值越小越靠前）',
    MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '分类状态：0禁用 1启用',
    MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    MODIFY COLUMN `updated_at` datetime DEFAULT NULL COMMENT '最后更新时间';

-- ---------- api：先补注释与其它列，再迁移 status / 增加 audit_status ----------
ALTER TABLE `{prefix}api`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    MODIFY COLUMN `name` varchar(100) NOT NULL COMMENT '接口名称',
    MODIFY COLUMN `description` text COMMENT '接口描述',
    MODIFY COLUMN `endpoint` varchar(500) NOT NULL DEFAULT '' COMMENT '接口地址（http/https）',
    MODIFY COLUMN `method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT '请求方式：GET或POST',
    MODIFY COLUMN `request_params` mediumtext COMMENT '请求参数（JSON 数组）',
    MODIFY COLUMN `response_example` mediumtext COMMENT '返回参数示例',
    MODIFY COLUMN `doc_normal` mediumtext COMMENT '普通文档',
    MODIFY COLUMN `doc_ai` mediumtext COMMENT 'AI 文档',
    MODIFY COLUMN `call_count` bigint unsigned NOT NULL DEFAULT 0 COMMENT '累计请求次数',
    MODIFY COLUMN `require_key` tinyint(1) NOT NULL DEFAULT 0 COMMENT '密钥要求：0不需要 1必须 2可选',
    MODIFY COLUMN `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标（链接或本地 SVG 路径）',
    MODIFY COLUMN `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称（对应 category.name，可空）',
    MODIFY COLUMN `user_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建者用户 ID（0表示未绑定前台用户）',
    MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    MODIFY COLUMN `updated_at` datetime DEFAULT NULL COMMENT '最后更新时间';

-- 审核字段（存量接口视为管理员侧数据，默认审核通过）
ALTER TABLE `{prefix}api`
    ADD COLUMN `audit_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '审核状态：0审核不通过 1审核通过（管理员发布默认1）' AFTER `status`;

-- 英文状态 → 数字（仍 varchar 阶段先写成 '0'/'1'/'2'）
UPDATE `{prefix}api` SET `status` = CASE
    WHEN `status` IN ('disabled', '1') THEN '1'
    WHEN `status` IN ('maintenance', '2') THEN '2'
    ELSE '0'
END;

ALTER TABLE `{prefix}api`
    MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '接口状态：0正常 1禁用 2维护';

ALTER TABLE `{prefix}api`
    ADD KEY `idx_audit_status` (`audit_status`);
