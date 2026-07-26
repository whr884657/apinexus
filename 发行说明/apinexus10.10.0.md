# ApiNexus 10.10.0

## 本版要点

- **图表**：手机端趋势图不再右侧溢出
- **刷新**：控制台标题行改为刷新图标按钮
- **时钟**：本地每秒走动
- **最近调用**：仅 20 条；字段 ID / 接口名 / IP / 时间；紧凑列表；与日志查询首页共用 Redis 页缓存
- **设置**：控制台 live 间隔可配 1～5 秒
- **修复**：默认主题 `detail.php` IDE 空访问告警

## 升级注意

- 无数据库结构变更
- 强刷 `admin-dashboard.js` / `admin-dashboard.css` / `admin/index.php` / `admin/settings.php`
