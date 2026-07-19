# ApiNexus 2.11.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（数据库命名 + 主题登录 UI）

---

## 变更摘要

### 数据库

| 旧 | 新 |
|----|-----|
| `{prefix}security_rate_hit` | `{prefix}mail_code_rate_log` |
| 字段 `bucket` / `hit_at` | 字段 `limit_key` / `created_at` |

升级时自动迁移数据并删除旧表（`install/migrations/2.11.0.sql`）。

### 青绿主题登录页

| 端 | 效果 |
|----|------|
| 手机端（≤900px） | 保持原居中无卡片样式 |
| 电脑端（≥901px） | 右侧磨砂表单区，左侧留白动效（渐变网格、旋转环、浮动光点、鼠标视差） |

---

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v2.11.0/apinexus2.11.0.zip

---

## 升级说明

1. 备份数据库
2. 覆盖代码并执行在线更新/结构迁移
3. 确认新表 `{prefix}mail_code_rate_log` 存在，旧表 `security_rate_hit` 已移除
