-- 13.26.34：调用日志记录出口节点 IP:端口
ALTER TABLE `{prefix}apilog`
    ADD COLUMN `egress` varchar(64) NOT NULL DEFAULT '' COMMENT '出口节点（IP:端口；空=未走出口代理）' AFTER `iploc`;
