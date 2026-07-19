-- ApiNexus 1.9.0：前台主题配置项
INSERT INTO `{prefix}config` (`key`, `value`) VALUES ('frontend_theme', 'default')
ON DUPLICATE KEY UPDATE `value` = `value`;
