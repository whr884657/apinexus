# ApiNexus 3.9.0 发行说明

**发布日期：** 2026-07-14  
**类型：** 中版本（全库字段重命名，属大改）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v3.9.0/apinexus3.9.0.zip
- 标签：`v3.9.0`

## 变更摘要

1. **字段命名简化（去下划线）**  
   例：`require_key`→`needkey`，`audit_status`→`audit`，`created_at`→`createtime`，`user_id`→`userid`，`bound_user_id`→`binduid`，`avatar_url`→`avatar` 等。

2. **表重命名**  
   `mail_code_rate_log` → `mailrate`

3. **策略**  
   新版本代码**不再**长期兼容旧字段名；升级后执行结构更新即可。详见开发规范《版本升级不兼容旧版》。

## 升级说明

1. 更新至 v3.9.0  
2. **系统管理 → 系统升级**，执行数据库结构更新（`3.9.0`）  
3. 清空前台 Redis 缓存后刷新页面（若启用）

## 字段对照（节选）

| 旧名 | 新名 |
|------|------|
| require_key | needkey |
| audit_status | audit |
| created_at / updated_at | createtime / updatetime |
| user_id | userid |
| bound_user_id | binduid |
| avatar_url | avatar |
| call_count | calls |
| request_params | params |
| response_example | response |
| doc_normal / doc_ai | doc / aidoc |
| sort_order | sort |
