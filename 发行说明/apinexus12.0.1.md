# ApiNexus 12.0.1 发行说明

**发布日期：** 2026-07-27  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v12.0.1/apinexus12.0.1.zip

## 本版摘要

小版本修复：默认主题 API 详情「快速上手」在切换鉴权方式（Query ↔ Header ↔ Bearer）后，九种开发语言图标丢失的问题。

## 变更要点

| 项 | 说明 |
|----|------|
| 根因 | 切换鉴权会重绘语言 Tab；若样本缺图标字段或浏览器仍缓存旧 `detail-quickstart.js`（同版 `?v=`），图标会被抹掉且无法恢复 |
| 兜底 | `ApiQuickstart::langIconMap()` → `window.detailQsLangIcons`；`enrichItem` 按语言 id 补齐 `icon_gray` / `icon_color` |
| 首屏 | 已有 PHP 渲染的图标时只绑定事件，避免无谓重绘 |
| 缓存 | 升至 12.0.1，资源 `?v=` 强制刷新主题 JS |

## 升级注意

1. **无数据库结构变更**（`db_changes: false`）。
2. 升级后请强刷前台 API 详情页（或清缓存），确认语言 Tab 图标在切换鉴权后仍在。

## 相关文件

- `core/ApiQuickstart.php`
- `core/theme/default/pages/detail.php`
- `core/theme/default/assets/js/pages/detail-quickstart.js`
