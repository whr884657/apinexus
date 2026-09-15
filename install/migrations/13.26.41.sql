-- 13.26.41：注册开放改为单键 register_enabled 数字模式
-- 1=全部开启 2=全部关闭 3=仅普通用户 4=仅开发者
-- 若曾写入 register_allow_user / register_allow_developer，按子开关合并后删除
-- 另：种子 apiorder（0=随机 1=按分类权重）
-- 另：种子 redis_host / redis_port / redis_password / redis_database（E317）

UPDATE `{prefix}config` AS t
INNER JOIN (
    SELECT
        CASE
            WHEN MAX(CASE WHEN `key` = 'register_allow_user' THEN `value` END) = '1'
             AND MAX(CASE WHEN `key` = 'register_allow_developer' THEN `value` END) = '1' THEN '1'
            WHEN MAX(CASE WHEN `key` = 'register_allow_user' THEN `value` END) = '1' THEN '3'
            WHEN MAX(CASE WHEN `key` = 'register_allow_developer' THEN `value` END) = '1' THEN '4'
            ELSE '2'
        END AS mode
    FROM `{prefix}config`
    WHERE `key` IN ('register_allow_user', 'register_allow_developer')
    HAVING COUNT(*) > 0
) AS m
SET t.`value` = m.mode
WHERE t.`key` = 'register_enabled';

-- 旧二进制总闸：0 → 2（全关）；已是 1～4 的保持不变
UPDATE `{prefix}config`
SET `value` = '2'
WHERE `key` = 'register_enabled' AND `value` = '0';

DELETE FROM `{prefix}config`
WHERE `key` IN ('register_allow_user', 'register_allow_developer');

-- 接口目录排序：0=随机（默认）1=按分类 sort 权重
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('apiorder', '0');

-- Redis 连接参数种子（已有行不覆盖；E311 / E317）
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('redis_host', '127.0.0.1'),
('redis_port', '6379'),
('redis_password', ''),
('redis_database', '0');
