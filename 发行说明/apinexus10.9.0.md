# ApiNexus 10.9.0

## 本版要点

- **平滑曲线**：调用类型趋势、成功率趋势改为 Catmull-Rom 平滑曲线（含 KPI 火花图）
- **实时更新**：控制台 `action=live` 约 5 秒刷新时钟 / 今日 KPI / 最近调用；约 45 秒软刷趋势与 TOP
- **首屏加速**：PHP 仅输出 `consoleBootShell()`，重统计进页后 AJAX `snapshot`，缓解首次登录后台卡住
- **手机 KPI**：四个指标改为 **2×2 田字格**，不再单列竖排
- **成功率色**：成功绿、失败红

## 升级注意

- 无数据库结构变更
- 强刷 `admin-dashboard.js` / `admin-dashboard.css` / `admin-screen.js`
