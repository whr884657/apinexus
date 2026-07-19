-- ApiNexus 3.29.0
-- 新增用户 API 调用密钥表 apikey（勿命名为 token，避免与大模型计费 token 冲突）
-- 若站点已误建 token 表，由 DatabaseMigrator::reconcileSchemaState 重命名为 apikey

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
