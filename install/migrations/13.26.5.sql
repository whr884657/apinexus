-- 13.26.5：用户注册开放开关 + 注册邮箱验证开关
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('register_enabled', '1'),
('register_email_verify', '1');

-- 13.26.5 兼容性：按本库热点查询补索引（幂等；重复创建时 Migrator 忽略 1061）
-- 前台公开列表：status IN + audit + ORDER BY id
ALTER TABLE `{prefix}api` ADD KEY `idx_status_audit_id` (`status`, `audit`, `id`);
-- 控制台按日新增接口
ALTER TABLE `{prefix}api` ADD KEY `idx_createtime` (`createtime`);
-- 大屏今日 TOP / 地理聚合：按 createtime 范围 GROUP BY apiname / iploc
ALTER TABLE `{prefix}apilog` ADD KEY `idx_createtime_apiname` (`createtime`, `apiname`);
ALTER TABLE `{prefix}apilog` ADD KEY `idx_createtime_iploc` (`createtime`, `iploc`(64));
-- 控制台按日新增用户
ALTER TABLE `{prefix}user` ADD KEY `idx_createtime` (`createtime`);
