# ApiNexus 12.1.0

**发布日期：** 2026-07-28  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v12.1.0/apinexus12.1.0.zip

## 本版摘要

- 管理员「实时数据监控中心」按参考 UI 全面重制
- ECharts 中国 / 世界地图飞线 + 四角玻璃面板
- 核心指标走 `statday` 聚合；飞线按实时调用量动态生成
- 深浅色太阳/月亮 SVG；电脑与手机双端自适应

## 变更说明

1. **布局：** 中心全幅地图；左上核心指标（今日调用 / 累计调用 / 成功率 / 失败率）；右上今日 TOP；左下近 24h 趋势；右下实时调用日志  
2. **地图：** 本地 ECharts 5.4.3；geoJSON 仅用官方主源（jsdelivr `echarts@5.4.3` china/world）；无 4.9.0 备用源选择器、无底部调试条  
3. **实时：** live 轮询刷新 KPI / 日志 / 飞线；软刷约 45 秒更新趋势与 TOP，且不覆盖 live KPI  
4. **主题：** 大屏内深浅色切换使用 SVG 图标（非文字）  
5. **规范：** 《开发规范与功能优化》§2.23 / §3.3、易错点 E141；参考目录仍不进仓库

## 升级注意

- **无数据库结构变更**（`db_changes: false`）
- 需可访问外网加载 geoJSON（或自行镜像同路径资源）
- 建议清除浏览器缓存或硬刷新以加载新 `admin-screen.js` / CSS

## 相关文件

- `admin/screen.php`
- `assets/js/admin-screen.js`
- `assets/css/admin-dashboard.css`
- `assets/vendor/echarts/echarts.min.js`
- `core/DashboardStats.php`
