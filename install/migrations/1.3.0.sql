-- misc-api 1.3.0：用户 OAuth 绑定字段

ALTER TABLE `{prefix}user`
    ADD COLUMN `oauth_qq_openid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ OpenID' AFTER `avatar_url`,
    ADD COLUMN `oauth_gitee_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'Gitee 用户 ID' AFTER `oauth_qq_openid`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('oauth_config', '{"qq":{"enabled":false,"app_id":"","app_key":""},"gitee":{"enabled":false,"client_id":"","client_secret":""}}')
ON DUPLICATE KEY UPDATE `key` = `key`;
