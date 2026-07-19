-- ApiNexus 1.1.1：用户表增加头像字段
ALTER TABLE `{prefix}user` ADD COLUMN `avatar_url` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义头像链接' AFTER `email`;
