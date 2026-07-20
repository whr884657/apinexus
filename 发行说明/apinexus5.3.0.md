# ApiNexus 5.3.0 发行说明

**版本：** 5.3.0  
**日期：** 2026-07-21  
**类型：** 中版本（主题设置存储架构变更，需数据库更新）

## 变更

主题专属设置（Hero 文案、统计开关等）**不再**写入 `core/theme/{id}/data/settings.json`，改为存入 MySQL 配置表键 **`themesettings`**：

```json
{
  "default": { "hero_title": "...", "show_runtime": true },
  "slate": { "color_preset": "green", "nav_expand_mode": "top_drawer" }
}
```

- 后台打开「主题设置」时扫描主题包，缺失的主题 ID 自动补 `{}`  
- 若磁盘仍有旧 `settings.json`，首次读取时一次性迁入数据库  
- 只备份 MySQL 即可带走主题配置  

## 升级

1. 覆盖代码  
2. 后台执行 **数据库结构更新**（写入 `themesettings` 配置键）  
3. 打开一次「主题设置」页，确认各主题配置仍在  
4. 可选：删除各主题下废弃的 `data/settings.json`（在线更新会按 `obsolete-files.json` 尝试清理）  

## 数据库变更

有：`install/migrations/5.3.0.sql`（插入 `themesettings` 配置键，默认 `{}`）
