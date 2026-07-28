# ApiNexus 13.1.0

**发布日期：** 2026-07-28  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v13.1.0/apinexus13.1.0.zip

## 本版摘要

- 数据大屏 Redis 分层缓存与飞线双模（实时 / 今日）
- 雨点飞线着色、地图描边、TOP 三行、底栏切换

## 变更说明

1. **缓存：** `screen_full`、`geo_dist_today`、`geo_dist_live`（短 TTL）  
2. **底栏：** 中国/世界移至地图正下方；新增实时 / 今日切换  
3. **飞线：** 雨点（circle）拖尾；按调用量绿/黄/红  
4. **描边：** 中国/世界地图 border 加粗  
5. **TOP：** 约三行可视，超出轮循  

## 升级注意

- 无数据库结构变更  
- 建议硬刷新浏览器缓存  

## 相关文件

- `core/DashboardStats.php`、`admin/screen.php`
- `assets/js/admin-screen.js`、`assets/css/admin-dashboard.css`
