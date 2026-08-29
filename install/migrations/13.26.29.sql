-- ApiNexus 13.26.29
-- 用户调用 IP 白名单（user.ipallow；空=不限制）

ALTER TABLE `{prefix}user`
    ADD COLUMN `ipallow` varchar(2000) NOT NULL DEFAULT '' COMMENT '调用IP白名单（逗号分隔；空表示不限制）' AFTER `stat7`;
