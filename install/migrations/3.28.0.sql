-- ApiNexus 3.28.0
-- 删除 apilog.source（来源类型）；跨域情况看 domain / referer / origin 即可

ALTER TABLE `{prefix}apilog` DROP INDEX `idx_source`;
ALTER TABLE `{prefix}apilog` DROP COLUMN `source`;
