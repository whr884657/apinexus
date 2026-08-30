-- 13.26.31：提取 JSON 字段路径 + 调用短码 + 提取节点 TTL 缓存
ALTER TABLE `{prefix}ipproxy`
    ADD COLUMN `jsonhost` varchar(80) NOT NULL DEFAULT '' COMMENT 'JSON主机字段名或点路径（空=自动识别常见键）' AFTER `extfmt`,
    ADD COLUMN `jsonport` varchar(80) NOT NULL DEFAULT '' COMMENT 'JSON端口字段名或点路径（空=自动或主机内含端口）' AFTER `jsonhost`,
    ADD COLUMN `proxycode` char(3) NOT NULL DEFAULT '' COMMENT '调用短码（三位随机；vsproxyid仅认此码，不认数字主键）' AFTER `jsonport`,
    ADD COLUMN `ttlmin` int(11) NOT NULL DEFAULT 10 COMMENT '提取节点缓存分钟（0=每次重新提取；隧道忽略）' AFTER `proxycode`,
    ADD COLUMN `cachehost` varchar(255) NOT NULL DEFAULT '' COMMENT '提取缓存主机' AFTER `ttlmin`,
    ADD COLUMN `cacheport` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '提取缓存端口' AFTER `cachehost`,
    ADD COLUMN `cacheexp` datetime DEFAULT NULL COMMENT '提取缓存过期时间' AFTER `cacheport`;

ALTER TABLE `{prefix}ipproxy`
    ADD UNIQUE KEY `uk_userid_code` (`userid`, `proxycode`);

-- 安装双保险：config.install_done=1（与 config/install.lock 并列；删锁 alone 不可重装）
INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('install_done', '1')
ON DUPLICATE KEY UPDATE `value` = '1';
