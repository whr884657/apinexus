# ApiNexus 2.10.1 发行说明

**发布日期：** 2026-07-12  
**类型：** 小版本（安全修复）

---

## 变更摘要

### 邮箱验证码发信安全

| 项目 | 说明 |
|------|------|
| 限流加固 | IP 45s 间隔、邮箱 120s 间隔、IP 5次/时、邮箱 3次/时、8次/日 |
| 防绕过 | `recordMailCodeAttempt` 在检查通过后立即计入，未注册邮箱也无法无限探测 |
| 前端同步 | 验证码按钮倒计时 120s，限流错误解析服务端剩余秒数 |
| 文档 | 新增根目录《邮箱发信规范.md》 |

### 涉及文件

- `core/AuthSecurity.php`
- `user/forgot.php`、`user/register.php`、`admin/forgot.php`
- 各主题认证页 `forgot.php` / `register.php`

---

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v2.10.1/apinexus2.10.1.zip

---

## 升级说明

- 无数据库变更，直接覆盖升级即可
- 限流数据保存在 `config/.security/rate_limit.json`，升级后自动沿用
