# ApiNexus 2.14.0 发行说明

**发布日期：** 2026-07-12  
**版本类型：** 大版本（主题一参考 UI 完整复刻）

## 概述

本版本将 `主题一UI参考设计` 的前台 UI **完整迁入** ApiNexus 默认主题，布局、组件、交互与参考稿一致；仅将绿色极客色板替换为 ApiNexus **白色主题**（主色 `#111111`、背景 `#f8fafc`）。用户中心样式保持不变。

## 主要变更

### 默认主题 v1.5.0

- 迁入参考 CSS/JS：`front-common.css`、各 `pages/*.css`、`index.js`、`apis-page.js` 等
- 顶栏 + 右侧抽屉 + 主题切换 + 粒子 canvas + 网格 overlay
- 首页：公告条、Glitch 标题、终端 Hero、统计动画、接口目录、在线调试终端、合作伙伴
- 子页面：全部接口 / 文章 / 关于 / 友链 / 贡献者 / 赞助 — 对齐参考 `content-wrapper` 布局
- `theme-tokens.css` 统一白色色板覆盖参考绿色变量

### 系统

- `ThemeManager::defaultFrontendAssets()` + `vs_frontend_page()` 支持 Tailwind + 多 CSS/JS
- `api-proxy.php`：首页在线调试同源代理
- `includes/api-payload.php`：公开接口数据注入 `apiData`

## 升级说明

- **数据库：** 无变更
- 覆盖更新后强制刷新浏览器缓存

## 下载

- 标签：`v2.14.0`
- 附件：`apinexus2.14.0.zip`
