-- misc-api 3.7.1：require_key 三态语义（0=不需要 1=必须 2=可选）
-- 列类型本身已是 tinyint，仅更新注释；既有 0/1 数据保持兼容

ALTER TABLE `{prefix}api`
    MODIFY COLUMN `require_key` tinyint(1) NOT NULL DEFAULT 0 COMMENT '密钥要求：0不需要 1必须 2可选';
