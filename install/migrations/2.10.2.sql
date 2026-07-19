-- ApiNexus 2.10.2：安全频率限制表（替代 config/.security/rate_limit.json）
CREATE TABLE IF NOT EXISTS `{prefix}security_rate_hit` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `bucket` varchar(64) NOT NULL COMMENT 'bucket sha256',
    `hit_at` int unsigned NOT NULL COMMENT 'unix timestamp',
    PRIMARY KEY (`id`),
    KEY `idx_bucket_hit_at` (`bucket`, `hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安全频率限制命中记录';
