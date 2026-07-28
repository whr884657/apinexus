# ApiNexus 13.4.0

**发布日期：** 2026-07-29

## 变更摘要

- **代理网关：** 所有代理接口一律经本站服务器中继，取消对调用方的 302 跳转（无论上游是否需要密钥）
- **上游认证：** 界面明确 Query API Key / Header API Key / Bearer Token
- **出站身份：** 可配置 User-Agent（系统默认 / 约 20 套内置设备与浏览器 / 自定义 / 按分钟轮询）与 Referer（不发送 / 自定义 / 转发客户端）
- **数据库：** 新增字段 `upuamode`、`upuapreset`、`upua`、`upreferermode`、`upreferer`（迁移 `13.4.0.sql`，升级后请执行系统升级）
- **数据大屏 / 控制台：** TOP 排行进入 live 实时刷新；控制台 TOP 与系统概览固定高度，带排名，超出自动滚动

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v13.4.0/apinexus13.4.0.zip
