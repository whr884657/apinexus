# ApiNexus 10.9.1

## 本版要点

- 修复控制台首屏 AJAX 加载中点击「刷新」：按钮永久禁用、强制刷新被 `loading` 闸门吞掉
- 首屏 `snapshot` 成功后再启动 `live` 轮询，避免半截 KPI
- 首屏加载失败给出 Toast，软刷可自动重试

## 升级注意

- 无数据库结构变更
- 强刷 `assets/js/admin-dashboard.js`
