# misc-api 2.10.2 发行说明

**发布日期：** 2026-07-12  
**类型：** 小版本（安全修复 + 数据库结构）

---

## 变更摘要

### 邮箱验证码安全

| 项目 | 说明 |
|------|------|
| 限流存储 | 弃用 `config/.security/rate_limit.json`，改用 MySQL 表 `{prefix}security_rate_hit` |
| 防重放 | `send_code` 须携带一次性 `mail_ticket`，每次响应下发新票据 |
| IP 日限额 | 同 IP 24 小时内最多 8 次发信请求，防轮换邮箱轰炸 |
| IP 小时限额 | 收紧为 3 次/小时，最短间隔 60 秒 |

### 仓库清理

- 移除误提交的 `主题规范.md`、`邮箱发信规范.md`
- 本地维护文档统一放入 `开发规范/`（不提交 Git、不打进 ZIP）

---

## 数据库

升级后自动执行 `install/migrations/2.10.2.sql`，新建表 `{prefix}security_rate_hit`。

---

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v2.10.2/misc-api2.10.2.zip

---

## 升级说明

1. 备份站点与数据库
2. 覆盖代码后于后台执行在线更新（或手动触发结构迁移）
3. 可删除旧文件 `config/.security/rate_limit.json`（已不再使用）
