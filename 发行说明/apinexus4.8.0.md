# ApiNexus 4.8.0

**发布日期：** 2026-07-20  
**下载：** [apinexus4.8.0.zip](https://gitee.com/xunjinlu/apinexus/releases/download/v4.8.0/apinexus4.8.0.zip)

## 变更摘要

- **在线测试浏览器直连**：首页终端与详情页在线测试改为用户浏览器直连公开 `endpoint`，不再默认走 `core/playground/relay.php`
- **调用日志纠偏**：`apilog.path` 记录真实 `/api/...` 或 `/apis/{短码}`，不再被记成中继入口
- **中继不再记账**：`PlaygroundRelay` 内禁止写 `apilog`（仅兼容旧主题）
- **媒体预览**：直连响应按 Content-Type + 文件魔数识别图/音/视频

## 升级说明

- 无数据库结构变更
- 升级后请硬刷新前台缓存（Ctrl+F5），再测在线终端与详情测试
- 代理接口若上游未允许浏览器跨域，预览可能失败（属浏览器安全策略）；统计仍会正确记在 `/apis/{短码}`

## 相关文档

- `CORE模块说明.md` §4.21.2
- 开发规范：主题规范 · 在线测试；开发易错点 E57；本地与代理接口统计机制 §九
