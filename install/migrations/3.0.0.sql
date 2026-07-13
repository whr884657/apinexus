-- misc-api 3.0.0：用户角色（普通用户 / 开发者）

ALTER TABLE `{prefix}user`
    ADD COLUMN `role` varchar(16) NOT NULL DEFAULT 'user' COMMENT '用户角色：user=普通用户 developer=开发者' AFTER `status`;
