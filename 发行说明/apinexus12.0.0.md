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

## 同版复查2 修复

| 项 | 说明 |
|----|------|
| AI 解析 | 漏写 auth / 包 markdown 围栏 / 语言落在其它 auth 下时可收回改写；失败重试 1 次 |
| 提示词 | 强制中文注释；禁止 emoji 与 ``` 包裹；php/cpp 明确 lang 字面值 |
| stripEmoji | 入库前清除表情与装饰符号 |
| 快速上手图标 | `detailQsBundle` 带 icon 字段；`detail-quickstart.js` 重绘 Tab 时渲染灰/彩图标 |

## 同版复查3 修复

| 项 | 说明 |
|----|------|
| 升级页按钮 | 「执行数据库结构更新」改为标准 `.vs-btn--default`，与另两键同高 44px |
| 系统名称 | 后台关于首行、管理员登录标题、忘记密码邮件文案用 `systemName()` |

## 升级注意

1. **无数据库结构变更**（`db_changes: false`）。配置项 `ai_code_mode` / `ai_code_concurrency` 首次使用时按默认写入。
2. 请刷新后台接口列表页后再点「AI 生成代码示例」，以加载新前端脚本。
3. 若上游限流明显，请改用「单线程」或降低并发数。
4. 单片仍失败时，可适当加大「单片超时」或更换更稳模型。

## 相关文件

- `core/AiConfig.php` / `core/AiApiDoc.php` / `core/AiClient.php`
- `admin/api/list.php` / `admin/settings.php` / `assets/js/api-list.js`
