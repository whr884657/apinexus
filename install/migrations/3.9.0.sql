-- ApiNexus 3.9.0：字段名去下划线，改为简短英文词；mail_code_rate_log → mailrate

-- ---------- admin ----------
ALTER TABLE `{prefix}admin`
    CHANGE COLUMN `avatar_url` `avatar` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    CHANGE COLUMN `bound_user_id` `binduid` int(10) unsigned DEFAULT NULL COMMENT '绑定的前台用户ID（后台发布内容所用身份）',
    CHANGE COLUMN `created_at` `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间';

ALTER TABLE `{prefix}admin` DROP INDEX `uk_bound_user_id`;
ALTER TABLE `{prefix}admin` ADD UNIQUE KEY `uk_binduid` (`binduid`);

-- ---------- user ----------
ALTER TABLE `{prefix}user`
    CHANGE COLUMN `avatar_url` `avatar` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    CHANGE COLUMN `oauth_qq_openid` `qqopenid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ登录OpenID',
    CHANGE COLUMN `oauth_gitee_id` `giteeid` varchar(64) NOT NULL DEFAULT '' COMMENT 'Gitee登录用户ID',
    CHANGE COLUMN `created_at` `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    CHANGE COLUMN `last_login_at` `lastlogin` datetime DEFAULT NULL COMMENT '最后登录时间';

-- ---------- mailrate（表重命名 + 字段） ----------
RENAME TABLE `{prefix}mail_code_rate_log` TO `{prefix}mailrate`;

ALTER TABLE `{prefix}mailrate`
    CHANGE COLUMN `limit_key` `limitkey` varchar(64) NOT NULL COMMENT '限流键（SHA256）',
    CHANGE COLUMN `created_at` `createtime` int unsigned NOT NULL COMMENT '命中时间（Unix时间戳）';

ALTER TABLE `{prefix}mailrate` DROP INDEX `idx_limit_key_created`;
ALTER TABLE `{prefix}mailrate` ADD KEY `idx_limitkey_createtime` (`limitkey`, `createtime`);

-- ---------- api ----------
ALTER TABLE `{prefix}api`
    CHANGE COLUMN `request_params` `params` mediumtext COMMENT '请求参数（JSON数组）',
    CHANGE COLUMN `response_example` `response` mediumtext COMMENT '返回参数示例',
    CHANGE COLUMN `doc_normal` `doc` mediumtext COMMENT '普通文档',
    CHANGE COLUMN `doc_ai` `aidoc` mediumtext COMMENT 'AI文档',
    CHANGE COLUMN `call_count` `calls` bigint unsigned NOT NULL DEFAULT 0 COMMENT '累计请求次数',
    CHANGE COLUMN `require_key` `needkey` tinyint(1) NOT NULL DEFAULT 0 COMMENT '密钥要求：0不需要 1必须 2可选',
    CHANGE COLUMN `audit_status` `audit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '审核状态：0审核不通过 1审核通过（管理员发布默认1）',
    CHANGE COLUMN `user_id` `userid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建者用户ID（0表示未绑定前台用户）',
    CHANGE COLUMN `created_at` `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    CHANGE COLUMN `updated_at` `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间';

ALTER TABLE `{prefix}api` DROP INDEX `idx_audit_status`;
ALTER TABLE `{prefix}api` DROP INDEX `idx_user_id`;
ALTER TABLE `{prefix}api` ADD KEY `idx_audit` (`audit`);
ALTER TABLE `{prefix}api` ADD KEY `idx_userid` (`userid`);

-- ---------- category ----------
ALTER TABLE `{prefix}category`
    CHANGE COLUMN `sort_order` `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（数值越小越靠前）',
    CHANGE COLUMN `created_at` `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    CHANGE COLUMN `updated_at` `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间';
