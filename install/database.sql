-- ============================================================
-- ApiNexus 数据库结构定义（安装时执行）
-- 说明：{prefix} 为表前缀占位符，安装时自动替换
-- 规范：字段名禁止下划线；详细中文 COMMENT；多态用 0/1/2…（见开发规范/数据库开发规范.md）
-- ============================================================

-- 管理员表
CREATE TABLE IF NOT EXISTS `{prefix}admin` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `username` varchar(50) NOT NULL COMMENT '管理员用户名',
    `password` char(32) NOT NULL COMMENT '密码哈希（MD5，非明文）',
    `email` varchar(100) NOT NULL COMMENT '管理员邮箱',
    `avatar` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    `binduid` int(10) unsigned DEFAULT NULL COMMENT '绑定的前台用户ID（后台发布内容所用身份）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '账号状态：0禁用 1启用',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_binduid` (`binduid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- 用户表
CREATE TABLE IF NOT EXISTS `{prefix}user` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `username` varchar(50) NOT NULL COMMENT '用户名',
    `password` char(32) NOT NULL COMMENT '密码哈希（MD5，非明文）',
    `email` varchar(100) NOT NULL COMMENT '用户邮箱',
    `avatar` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接',
    `qqopenid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ登录OpenID',
    `giteeid` varchar(64) NOT NULL DEFAULT '' COMMENT 'Gitee登录用户ID',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '账号状态：0禁用 1启用',
    `role` varchar(16) NOT NULL DEFAULT 'user' COMMENT '用户角色：user普通用户 developer开发者',
    `points` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '积分余额',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `lastlogin` datetime DEFAULT NULL COMMENT '最后登录时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 系统配置表
CREATE TABLE IF NOT EXISTS `{prefix}config` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `key` varchar(50) NOT NULL COMMENT '配置键名',
    `value` text COMMENT '配置值（文本或JSON）',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- 初始系统配置
INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('site_name', 'ApiNexus'),
('site_description', '基于 PHP + MySQL 的轻量级 Web 管理系统'),
('site_keywords', 'ApiNexus,PHP,MySQL,管理系统'),
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
('mail_from_name', 'ApiNexus'),
('mail_notify_submit', '1'),
('mail_notify_pass', '1'),
('mail_notify_fail', '1'),
('frontend_theme', 'default'),
('themesettings', '{}'),
('site_runtime_start', ''),
('footer_html_left', ''),
('footer_html_center', ''),
('footer_html_right', ''),
('footer_qr1_enabled', '0'),
('footer_qr1_name', ''),
('footer_qr1_url', ''),
('footer_qr2_enabled', '0'),
('footer_qr2_name', ''),
('footer_qr2_url', ''),
('sponsor_qr_alipay', ''),
('sponsor_qr_wechat', ''),
('sponsor_qr_qq', ''),
('pay_url', ''),
('pay_pid', ''),
('pay_key', ''),
('pay_channel', '{"alipay":"","wxpay":"","qqpay":""}'),
('pay_methods', '["alipay","wxpay"]'),
('pay_rate', '1000'),
('pay_packages', '[{"id":"base1","name":"体验包","money":"1.00","points":"1000","hot":0},{"id":"base10","name":"常用包","money":"10.00","points":"11000","hot":1},{"id":"base50","name":"超值包","money":"50.00","points":"60000","hot":0}]'),
('apilog_detail', '1'),
('apilog_query_days', '7'),
('apilog_hot_days', '30'),
('apilog_archive_enabled', '1'),
('apilog_shard_rows', '5000'),
('apilog_cron_key', '');

-- 邮箱验证码发信频率限制记录
CREATE TABLE IF NOT EXISTS `{prefix}mailrate` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `limitkey` varchar(64) NOT NULL COMMENT '限流键（SHA256）',
    `createtime` int unsigned NOT NULL COMMENT '命中时间（Unix时间戳）',
    PRIMARY KEY (`id`),
    KEY `idx_limitkey_createtime` (`limitkey`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮箱验证码发信频率限制记录';

-- API 接口表
CREATE TABLE IF NOT EXISTS `{prefix}api` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `name` varchar(100) NOT NULL COMMENT '接口名称',
    `description` text COMMENT '接口描述',
    `endpoint` varchar(500) NOT NULL DEFAULT '' COMMENT '调用地址（本地为路径；代理为/proxy.php?s=短码）',
    `apitype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '接口类型：0本地路径 1代理外链',
    `targeturl` varchar(500) NOT NULL DEFAULT '' COMMENT '代理上游完整地址（仅代理类型）',
    `proxyslug` varchar(64) NOT NULL DEFAULT '' COMMENT '代理短码（仅代理类型）',
    `method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT '请求方式：GET或POST',
    `params` mediumtext COMMENT '请求参数（JSON数组）',
    `response` mediumtext COMMENT '返回参数示例',
    `doc` mediumtext COMMENT '普通文档',
    `aidoc` mediumtext COMMENT 'AI文档',
    `calls` bigint unsigned NOT NULL DEFAULT 0 COMMENT '累计请求次数',
    `needkey` tinyint(1) NOT NULL DEFAULT 0 COMMENT '密钥要求：0不需要 1必须 2可选',
    `charge` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否收费：0免费 1收费',
    `price` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '每次调用扣除积分（收费时有效）',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '接口状态：0正常 1禁用 2维护',
    `audit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '审核状态：0待审核 1通过 2不通过（管理员发布默认1）',
    `rejectreason` varchar(500) NOT NULL DEFAULT '' COMMENT '审核不通过原因（管理员可选填写）',
    `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标（链接或本地SVG路径）',
    `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称（对应category.name，可空）',
    `userid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建者用户ID（0表示未绑定前台用户）',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_audit` (`audit`),
    KEY `idx_category` (`category`),
    KEY `idx_userid` (`userid`),
    KEY `idx_apitype` (`apitype`),
    KEY `idx_proxyslug` (`proxyslug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API接口表';

-- API 调用日志表
CREATE TABLE IF NOT EXISTS `{prefix}apilog` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `apiid` int unsigned NOT NULL DEFAULT 0 COMMENT '接口ID（对应api.id）',
    `apiname` varchar(100) NOT NULL DEFAULT '' COMMENT '接口名称快照',
    `apitype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '接口类型：0本地 1代理',
    `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '调用用户ID（0匿名，预留）',
    `apikey` varchar(128) NOT NULL DEFAULT '' COMMENT '调用密钥（有则记录并校验归属）',
    `method` varchar(16) NOT NULL DEFAULT '' COMMENT 'HTTP方法',
    `ip` varchar(45) NOT NULL DEFAULT '' COMMENT '客户端IP',
    `iploc` varchar(120) NOT NULL DEFAULT '' COMMENT 'IP中文归属地（预留，后续可开启解析）',
    `host` varchar(255) NOT NULL DEFAULT '' COMMENT '请求Host',
    `path` varchar(500) NOT NULL DEFAULT '' COMMENT '请求路径',
    `url` varchar(1000) NOT NULL DEFAULT '' COMMENT '完整请求URL',
    `referer` varchar(1000) NOT NULL DEFAULT '' COMMENT 'Referer',
    `origin` varchar(500) NOT NULL DEFAULT '' COMMENT 'Origin',
    `domain` varchar(255) NOT NULL DEFAULT '' COMMENT '来源域名',
    `ua` varchar(500) NOT NULL DEFAULT '' COMMENT 'User-Agent',
    `ok` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否成功：0失败 1成功',
    `httpcode` smallint NOT NULL DEFAULT 200 COMMENT 'HTTP状态码',
    `charged` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否扣费：0否 1是',
    `cost` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '本次扣费积分数',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '调用时间',
    PRIMARY KEY (`id`),
    KEY `idx_apiid` (`apiid`),
    KEY `idx_userid` (`userid`),
    KEY `idx_ip` (`ip`),
    KEY `idx_createtime` (`createtime`),
    KEY `idx_createtime_id` (`createtime`, `id`),
    KEY `idx_ok_createtime` (`ok`, `createtime`),
    KEY `idx_apiid_createtime` (`apiid`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API调用日志';

-- 用户 API 调用密钥表
CREATE TABLE IF NOT EXISTS `{prefix}apikey` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `userid` int(10) unsigned NOT NULL COMMENT '所属用户ID（对应user.id）',
    `remark` varchar(100) NOT NULL DEFAULT '' COMMENT '密钥备注名称',
    `secret` varchar(64) NOT NULL COMMENT '密钥明文（SK-开头，后接32位随机字符）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '密钥状态：0禁用 1启用',
    `calls` bigint unsigned NOT NULL DEFAULT 0 COMMENT '累计调用次数',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_secret` (`secret`),
    KEY `idx_userid` (`userid`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户API调用密钥';

-- API 接口分类表
CREATE TABLE IF NOT EXISTS `{prefix}category` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `name` varchar(50) NOT NULL COMMENT '分类名称',
    `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '分类图标URL或本地路径',
    `description` varchar(255) NOT NULL DEFAULT '' COMMENT '分类描述',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（数值越小越靠前）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '分类状态：0禁用 1启用',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API接口分类表';

-- 积分与支付订单
CREATE TABLE IF NOT EXISTS `{prefix}orders` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `orderno` varchar(64) NOT NULL COMMENT '订单号（全局唯一）',
    `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联用户ID（对应user.id）',
    `direct` tinyint(1) NOT NULL COMMENT '方向：0减少 1增加',
    `kind` tinyint(1) NOT NULL COMMENT '类型：增加时0用户充值1管理员加款；减少时0API调用1管理员扣款2AI调用(预留)',
    `amount` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '变动积分（正数）',
    `balance` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '变动后积分余额',
    `money` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '实付金额（元，充值订单）',
    `apiid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联接口ID（API扣费时）',
    `keyid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联密钥ID（API扣费时）',
    `paytype` varchar(16) NOT NULL DEFAULT '' COMMENT '支付方式：alipay/wxpay/qqpay',
    `tradeno` varchar(64) NOT NULL DEFAULT '' COMMENT '上游平台订单号',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '订单状态：0待支付 1已完成 2已取消',
    `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注说明',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `paytime` datetime DEFAULT NULL COMMENT '支付完成时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_orderno` (`orderno`),
    KEY `idx_userid` (`userid`),
    KEY `idx_direct` (`direct`),
    KEY `idx_kind` (`kind`),
    KEY `idx_status` (`status`),
    KEY `idx_createtime` (`createtime`),
    KEY `idx_createtime_id` (`createtime`, `id`),
    KEY `idx_userid_status_id` (`userid`, `status`, `id`),
    KEY `idx_direct_kind_status_id` (`direct`, `kind`, `status`, `id`),
    KEY `idx_status_id` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分与支付订单';

-- 友情链接与合作伙伴（共用表，kind 区分）
CREATE TABLE IF NOT EXISTS `{prefix}link` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `name` varchar(50) NOT NULL COMMENT '名称',
    `siteurl` varchar(255) NOT NULL COMMENT '跳转链接',
    `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标链接',
    `description` varchar(200) NOT NULL DEFAULT '' COMMENT '简介：友情链接简介 / 赞助说明（金额或其它支持）',
    `contact` varchar(100) NOT NULL DEFAULT '' COMMENT '联系方式（仅友情链接使用）',
    `kind` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型：0友情链接 1合作伙伴 2赞助',
    `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用：0禁用 1启用',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '审核状态：0待审 1通过 2拒绝（合作伙伴与赞助固定为1）',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（越小越前）',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请/创建时间',
    `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_kind` (`kind`),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='友情链接、合作伙伴与赞助';
