# ApiNexus 13.0.0

**发布日期：** 2026-07-28  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v13.0.0/apinexus13.0.0.zip

## 本版摘要

- 数据大屏飞线城市库史诗级全量增强（国内地级市 + 全球主要城市）
- 新增独立坐标模块 `GeoCityCoords.php`，匹配与 payload 同步优化

## 变更说明

1. **国内：** 由省会级扩展至约 370+ 地级市/州盟（如云南曲靖、玉溪、大理等）  
2. **全球：** 约 290+ 主要城市覆盖亚欧美非澳；中英别名约 180+  
3. **匹配：** `resolveCityName` 支持管道分隔归属地与英文城市名；短 ASCII 整词匹配  
4. **性能：** live geo 只下发有调用城市；飞线条数上限提升至 14  
5. **枢纽：** 合并国内外坐标解析服务器位置  

## 升级注意

- 无数据库结构变更  
- 飞线仍依赖调用日志 `iploc` 与 IP 归属地开关  
- 建议硬刷新浏览器缓存  

## 相关文件

- `core/GeoCityCoords.php`、`core/DashboardStats.php`、`core/bootstrap.php`
