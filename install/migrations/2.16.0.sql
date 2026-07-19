-- ApiNexus 2.16.0：分类表增加 icon、description 字段

ALTER TABLE `{prefix}category`
    ADD COLUMN `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '分类图标 URL' AFTER `name`,
    ADD COLUMN `description` varchar(255) NOT NULL DEFAULT '' COMMENT '分类描述' AFTER `icon`;
