-- ApiNexus 3.18.0
-- 新增 API 调用日志表 apilog（本地注入 + 代理网关共用）

CREATE TABLE IF NOT EXISTS `{prefix}apilog` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `apiid` int unsigned NOT NULL DEFAULT 0 COMMENT '接口ID（对应api.id）',
    `apiname` varchar(100) NOT NULL DEFAULT '' COMMENT '接口名称快照',
    `apitype` tinyint(1) NOT NULL DEFAULT 0 COMMENT '接口类型：0本地 1代理',
    `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '调用用户ID（0匿名，预留）',
    `apikey` varchar(128) NOT NULL DEFAULT '' COMMENT '调用密钥（有则记录，本版不校验）',
    `method` varchar(16) NOT NULL DEFAULT '' COMMENT 'HTTP方法',
    `ip` varchar(45) NOT NULL DEFAULT '' COMMENT '客户端IP',
    `host` varchar(255) NOT NULL DEFAULT '' COMMENT '请求Host',
    `path` varchar(500) NOT NULL DEFAULT '' COMMENT '请求路径',
    `url` varchar(1000) NOT NULL DEFAULT '' COMMENT '完整请求URL',
    `referer` varchar(1000) NOT NULL DEFAULT '' COMMENT 'Referer',
    `origin` varchar(500) NOT NULL DEFAULT '' COMMENT 'Origin',
    `domain` varchar(255) NOT NULL DEFAULT '' COMMENT '来源域名',
    `ua` varchar(500) NOT NULL DEFAULT '' COMMENT 'User-Agent',
    `source` tinyint(1) NOT NULL DEFAULT 0 COMMENT '来源类型：0直访 1网页引用 2跨域 3其他',
    `ok` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否成功：0失败 1成功',
    `httpcode` smallint NOT NULL DEFAULT 200 COMMENT 'HTTP状态码',
    `charged` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否扣费：0否 1是（预留）',
    `cost` int NOT NULL DEFAULT 0 COMMENT '扣费积分数（预留）',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '调用时间',
    PRIMARY KEY (`id`),
    KEY `idx_apiid` (`apiid`),
    KEY `idx_userid` (`userid`),
    KEY `idx_ip` (`ip`),
    KEY `idx_createtime` (`createtime`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API调用日志';
