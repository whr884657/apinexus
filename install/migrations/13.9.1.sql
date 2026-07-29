-- 13.9.1：密码列扩容；废除双 MD5；已有账号须忘记密码重置
ALTER TABLE `{prefix}admin` MODIFY COLUMN `password` varchar(255) NOT NULL COMMENT '密码哈希（password_hash）';
ALTER TABLE `{prefix}user` MODIFY COLUMN `password` varchar(255) NOT NULL COMMENT '密码哈希（password_hash）';

-- 作废全部旧密码（含双 MD5 / 任何非 password_hash 格式）；登录失败后走忘记密码
UPDATE `{prefix}admin` SET `password` = '!';
UPDATE `{prefix}user` SET `password` = '!';

-- 默认开启本地验证码（管理端 + 用户端各场景）
INSERT INTO `{prefix}config` (`k`, `v`) VALUES
('captcha_mode', 'local'),
('captcha_mode_admin', 'local'),
('captcha_mode_user', 'local'),
('captcha_on_admin_login', '1'),
('captcha_on_admin_forgot', '1'),
('captcha_on_user_login', '1'),
('captcha_on_user_register', '1'),
('captcha_on_user_forgot', '1')
ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);
