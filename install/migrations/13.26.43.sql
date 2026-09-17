-- 13.26.43：全局哀悼 + 页脚友链开关迁入默认主题设置
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('site_mourning', '0');

-- 确保 themesettings 行存在（缺行时先补空 JSON，避免迁完偏好却 UPDATE 0 行）
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('themesettings', '{}');

-- 将旧系统键 home_footer_links 一次性迁入 themesettings.default.show_footer_friend_links 后删除
SET @hf := (SELECT `value` FROM `{prefix}config` WHERE `key` = 'home_footer_links' LIMIT 1);

UPDATE `{prefix}config`
SET `value` = JSON_SET(
  IF(`value` IS NULL OR TRIM(`value`) = '' OR JSON_VALID(`value`) = 0, '{}', `value`),
  '$.default.show_footer_friend_links',
  IF(@hf = '0', CAST('false' AS JSON), CAST('true' AS JSON))
)
WHERE `key` = 'themesettings'
  AND @hf IS NOT NULL;

-- 仅当旧键存在时删除（可重跑；与上方迁移配套）
DELETE FROM `{prefix}config` WHERE `key` = 'home_footer_links';
