# ApiNexus 12.0.0 发行说明

**发布日期：** 2026-07-27  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v12.0.0/apinexus12.0.0.zip

## 本版摘要

重度重构 AI 代码示例生成：由前端按「鉴权×语言」分片请求（可单线程/并行），进程实时显示当前任务，解决整包超时与假进度问题。

## 变更要点

| 项 | 说明 |
|----|------|
| 分片生成 | 每片只写一种鉴权 + 一种语言的 `:::qs`；最多 3×9=27 片 |
| 调度开关 | 系统设置 → AI 对接：`单线程` / `多线程` + 并发数 1～6 |
| 单片超时 | `ai_timeout` 表示单片上限（建议 60～180），不再指望一次请求撑满 27 片 |
| 实时进程 | 终端日志显示 `[n/27] 开始/完成 · Bearer · python` 等真实进度 |
| 接口 | 新增 `ai_gen_code_piece`；页面注入 `window.VS_AI_CODE` |

## 同版复查修复

| 项 | 说明 |
|----|------|
| Session 锁 | `ai_gen_*` 在 CSRF 通过后 `session_write_close()`，并行分片不再被会话文件串行 |
| 单片载荷 | 分片请求不再附带 `doc`/`aidoc`，降低并行流量 |
| Toast | 部分失败改用 `info`（`VS.showMessage` 无 warning） |
| 任务过滤 | `buildCodeJobs` 仅接受 query/header/bearer |

## 升级注意

1. **无数据库结构变更**（`db_changes: false`）。配置项 `ai_code_mode` / `ai_code_concurrency` 首次使用时按默认写入。
2. 请刷新后台接口列表页后再点「AI 生成代码示例」，以加载新前端脚本。
3. 若上游限流明显，请改用「单线程」或降低并发数。
4. 单片仍失败时，可适当加大「单片超时」或更换更稳模型。

## 相关文件

- `core/AiConfig.php` / `core/AiApiDoc.php` / `core/AiClient.php`
- `admin/api/list.php` / `admin/settings.php` / `assets/js/api-list.js`
