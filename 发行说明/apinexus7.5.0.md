# ApiNexus 7.5.0 发行说明

**版本：** 7.5.0  
**日期：** 2026-07-23  
**数据库：** 无结构变更

## 变更摘要

- 修复反向代理下 OG/图标变成 `http://`，导致微信 / QQ / 百度等抓不到站点图标
- 分享图优先站点 Logo，补齐 `apple-touch-icon` 与 `og:image` 类型/尺寸
- 分享描述强制系统设置「站点描述」，禁止主题 Hero 徽章（如「完全免费」）渗入
- 默认主题首页：H1 静态输出、徽章 `aria-hidden`、SEO 兜底文案

**运营建议：** 系统设置同时配置 Favicon + 正方形 PNG Logo（≥300px），并填写系统描述。微信等平台有缓存，更新后需清缓存或换参再测。

下载：https://gitee.com/xunjinlu/apinexus/releases/download/v7.5.0/apinexus7.5.0.zip
