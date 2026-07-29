-- ApiNexus 13.17.2：面板监控配置键显式入库（键值行，非新字段）
-- 说明：系统配置存于 `{prefix}config` 的 key/value 行；此前首次保存才插入。
-- 本脚本用 INSERT IGNORE 预置空行，便于库内可见；不改变已有值。

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('panelmonitor_enabled', '0'),
('panelmonitor_provider', ''),
('panelmonitor_baseurl', ''),
('panelmonitor_apikey', ''),
('dashboard_live_interval', '5');
