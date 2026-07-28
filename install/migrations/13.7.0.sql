-- 13.7.0：验证码管理员/用户分方式配置
-- 从旧单一 captcha_mode 复制到两侧；无旧键则默认 local

INSERT INTO `{prefix}config` (`key`, `value`)
SELECT 'captcha_mode_admin', `value` FROM `{prefix}config` WHERE `key` = 'captcha_mode'
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO `{prefix}config` (`key`, `value`)
SELECT 'captcha_mode_user', `value` FROM `{prefix}config` WHERE `key` = 'captcha_mode'
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('captcha_mode_admin', 'local'),
('captcha_mode_user', 'local')
ON DUPLICATE KEY UPDATE `key` = `key`;
