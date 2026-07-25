# ApiNexus 10.7.0

## 本版要点

- **控制台重做**：KPI（接口/用户/今日调用/成功率）、近 7 日类型与成功率趋势、彩色 TOP10、系统概览、最近调用筛选；适配手机端
- **数据大屏落地**：实时 KPI、近 24 小时调用趋势、中国/世界示意飞线地图、接口 TOP、滚动日志；支持全屏与深浅色；约 5 秒轻量轮询
- **性能**：`DashboardStats` + Redis 分层 TTL，避免每次打开/轮询扫爆 `apilog`
- **路径**：数据大屏改为 `/admin/screen`（文件 `screen.php`）；旧 `data-screen.php` 升级时自动清理

## 升级注意

- 无数据库结构变更
- 强刷后台静态资源（`admin-dashboard.css` / `admin-dashboard.js` / `admin-screen.js`）
- 书签若指向旧 `/admin/data-screen` 请改为 `/admin/screen`

## 同版本补丁（复查）

- 近 24 小时趋势改为按整点时间桶聚合，修复跨日同小时叠算
- 7 日趋势 / 火花图改为单次 SQL 聚合，降低大日志表超时风险
- 累计调用涨跌比不再用 `max(1, …)` 扭曲分母；统计异常时页面保底不白屏

## 同版本补丁（安全复查 R3）

- 管理端统一安全响应头；控制台/大屏 POST 增加管理员维度限流
- 仪表盘 boot/AJAX 最近调用不下发 `path`/`method`；`data-boot` 使用 `JSON_HEX_*`
- 规范强制：此后每版本必须 R1～R4 多次复查（含安全）
