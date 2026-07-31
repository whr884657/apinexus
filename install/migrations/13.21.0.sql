-- 13.21.0：缓存键前缀（同机多站 Redis 隔离）
-- 配置存 `{prefix}config` 的 key/value；已有值不覆盖。

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('redis_prefix', 'apinexus:');
