# ApiNexus 11.0.0 发行说明

**发布日期：** 2026-07-27  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v11.0.0/apinexus11.0.0.zip

## 本版摘要

业务错误码与 HTTP 网络状态码分离；AI 代码示例分片生成；鉴权文案与后台昼夜更替。

## 变更要点

| 项 | 说明 |
|----|------|
| 业务错误码 | JSON：`{ code:0, msg, errcode }`；传输 HTTP 固定 200 |
| 鉴权方式错误 | `errcode=11012` |
| 其它常用码 | 11001 未提供密钥 · 11002 密钥错误 · 11003 密钥禁用 · 11004 积分不足 · 11005 QPM · 11006 维护 · 11007 禁用 |
| AI 代码示例 | 按 keyways 分片请求模型，避免一次生成过多块超时 |
| 鉴权文案 | 禁止「全部支持」，列出实际勾选的 Query / Header / Bearer |
| 昼夜更替 | 管理员顶栏调色盘旁；夜间文字反色；认证页仍禁小人色，后台可黑 |

## 同版复查修复（二次检查后）

| 项 | 说明 |
|----|------|
| Playground 徽章 | 解析 body.`errcode`，业务失败不再误显示「200 OK」 |
| 首页在线测试 | 按接口 `keyways` 传 Header / Bearer |
| AUTH_WAY | 允许通道无一有效密钥且错通道有密钥时优先 11012 |
| 中继 | 按 keyways 注入鉴权；响应透传 `errcode`；`http` 固定 200 |
| AI | PHP 时限按分片动态抬高；部分鉴权失败返回 warning |

## 升级注意（不兼容说明）

1. **调用方若依赖旧字段 `http:401/403/503`：** 请改为读取 `errcode`（11xxx）。传输层 HTTP 状态不再用 401/403 表示业务失败。
2. **AI 超时：** 建议在「系统设置 → AI 对接」将超时调到 120～300 秒。
3. **无数据库结构变更**（`db_changes: false`）。

## 相关文件

- `core/ApiError.php`（新建）
- `core/AiApiDoc.php` / `core/ApiStats.php` / `assets/js/theme-picker.js`
