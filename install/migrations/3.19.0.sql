-- ApiNexus 3.19.0
-- apilog 增加 IP 归属地字段（预留，后续系统设置开启解析后写入）

ALTER TABLE `{prefix}apilog`
    ADD COLUMN `iploc` varchar(120) NOT NULL DEFAULT '' COMMENT 'IP中文归属地（预留，后续可开启解析）' AFTER `ip`;
