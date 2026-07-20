-- ApiNexus 5.3.0：主题设置迁入 MySQL config.themesettings（按主题 ID 分段的 JSON）
-- 说明：业务只认库；旧 core/theme/{id}/data/settings.json 由 ThemeManager 一次性迁入后废弃

INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('themesettings', '{}')
ON DUPLICATE KEY UPDATE `value` = `value`;
