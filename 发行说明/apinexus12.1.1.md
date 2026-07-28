# ApiNexus 12.1.1

**发布日期：** 2026-07-28  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v12.1.1/apinexus12.1.1.zip

## 本版摘要

- 数据大屏四角对调与 TOP 固定轮循
- 飞线枢纽改为服务器所在城市，国内匹配增强
- 去掉双标题；全屏并入昼夜按钮旁；刷新率与控制台设置绑定

## 变更说明

1. **布局：** 左下「实时调用日志」↔ 右下「实时调用量趋势」对调  
2. **TOP：** 右上角高度固定，列表无缝轮循滚动  
3. **KPI：** 百分比右上角；核心指标区缩小  
4. **飞线：** 枢纽=服务器归属城市；禁止最高调用城作枢纽回退；扩大城市匹配/同城微偏；live 近窗加权  
5. **标题：** 删除布局「数据大屏」行与刷新；全屏 SVG 放昼夜旁  
6. **设置：** `dashboard_live_interval` 文案标明控制台与大屏共用  

## 升级注意

- 无数据库结构变更  
- 飞线依赖调用日志 `iploc`；请在系统设置开启 IP 归属地并确保解析正常  
- 建议硬刷新浏览器缓存  

## 相关文件

- `admin/screen.php`、`assets/js/admin-screen.js`、`assets/css/admin-dashboard.css`
- `core/DashboardStats.php`、`admin/settings.php`
