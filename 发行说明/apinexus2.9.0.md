# ApiNexus 2.9.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（后台主题配置体系）

---

## 变更摘要

### 后台主题设置页

| 项目 | 说明 |
|------|------|
| 当前使用 | 仅右上角「当前使用」徽章，无默认边框 |
| 悬停 | 黑色边框高亮 |
| 设置按钮 | 每张主题卡片下方；仅**已启用（当前使用）**主题可打开 |
| 未启用点击设置 | 提示「该主题未启用，不可以进行设置」 |
| 弹窗 | 手机端底部抽屉 75vh；电脑端居中弹窗 |

### 主题专属数据

- 路径：`core/theme/{id}/data/settings.json`
- 与 MySQL `vs_config` 站点通用配置分离
- `theme.json` 新增可选 `settings` schema
- API：`ThemeManager::themeSetting()` / `readThemeData()` / `writeThemeData()`

### 前台联动

- default / slate 首页支持 `hero_title`、`hero_lead` 等主题配置项

---

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v2.9.0/apinexus2.9.0.zip

---

*ApiNexus 2.9.0*
