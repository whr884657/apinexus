-- 13.26.30：用户自备出口 IP 代理（表 ipproxy + user.proxystrategy）
-- 平台不提供免费代理节点；用户自填隧道/提取配置，调用时传 vsproxy=1 启用

ALTER TABLE `{prefix}user`
    ADD COLUMN `proxystrategy` tinyint(1) NOT NULL DEFAULT 0 COMMENT '出口代理选用策略：0轮询 1随机 2优先首条启用' AFTER `ipallow`;

CREATE TABLE IF NOT EXISTS `{prefix}ipproxy` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `userid` int(10) unsigned NOT NULL COMMENT '所属用户ID（对应user.id）',
    `title` varchar(60) NOT NULL DEFAULT '' COMMENT '备注名称',
    `mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '对接模式：0隧道(host:port) 1提取API',
    `proto` tinyint(1) NOT NULL DEFAULT 0 COMMENT '代理协议：0http 1https 2socks5 3socks4',
    `host` varchar(255) NOT NULL DEFAULT '' COMMENT '隧道主机（域名或IP）；提取模式可空',
    `port` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '隧道端口；提取模式可空',
    `username` varchar(200) NOT NULL DEFAULT '' COMMENT '代理账号（可含厂商用户名参数）',
    `password` varchar(200) NOT NULL DEFAULT '' COMMENT '代理密码（仅服务端持有，界面不回显）',
    `extract` varchar(1000) NOT NULL DEFAULT '' COMMENT '提取API完整URL（mode=1时使用）',
    `extfmt` tinyint(1) NOT NULL DEFAULT 0 COMMENT '提取返回格式：0自动 1纯文本ip:port 2JSON',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重（越小越前；轮询顺序）',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updatetime` datetime DEFAULT NULL COMMENT '最后更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_userid` (`userid`),
    KEY `idx_userid_status` (`userid`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户自备出口IP代理配置（每用户最多5条）';
