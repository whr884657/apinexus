-- ApiNexus 13.26.22
-- 每账号 API 密钥创建数量上限（config.apikey_max）
-- 幂等：INSERT IGNORE，不覆盖站长已改值；不改动 apikey 表数据行

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('apikey_max', '3');
