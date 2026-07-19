-- ApiNexus 1.8.0：管理员绑定用户账号（后台发布内容身份）

ALTER TABLE `{prefix}admin`
    ADD COLUMN `bound_user_id` int(10) unsigned DEFAULT NULL COMMENT '绑定的用户账号ID（后台发布内容身份）' AFTER `avatar_url`,
    ADD UNIQUE KEY `uk_bound_user_id` (`bound_user_id`);
