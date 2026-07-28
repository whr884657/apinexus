-- 13.4.0：代理出站身份（User-Agent / Referer）
ALTER TABLE `{prefix}api`
  ADD COLUMN `upuamode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '出站UA模式：0系统默认 1内置预设 2自定义 3轮询内置' AFTER `upkey`,
  ADD COLUMN `upuapreset` varchar(32) NOT NULL DEFAULT '' COMMENT '内置UA预设键' AFTER `upuamode`,
  ADD COLUMN `upua` varchar(512) NOT NULL DEFAULT '' COMMENT '自定义User-Agent' AFTER `upuapreset`,
  ADD COLUMN `upreferermode` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Referer模式：0不发送 1自定义 2转发客户端' AFTER `upua`,
  ADD COLUMN `upreferer` varchar(500) NOT NULL DEFAULT '' COMMENT '自定义Referer地址' AFTER `upreferermode`;
