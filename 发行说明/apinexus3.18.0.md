# ApiNexus 3.18.0 发行说明

**日期：** 2026-07-16  
**类型：** 中版本（调用统计 + 新表 `apilog`）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.18.0/apinexus3.18.0.zip

## 变更

1. **调用日志表 `apilog`**  
   记录调用时间、接口 ID/名称/类型、用户、密钥（有则记）、IP、Host/Path/URL、Referer/Origin/来源域名、UA、来源类型、成败、HTTP 码；扣费字段预留（本版不扣费）。

2. **本地接口**  
   文件头两行：`bootstrap` + `ApiStats::hit()`；可按脚本路径自动匹配 `endpoint`，或传数字 ID。

3. **代理接口**  
   `/apis/{短码}` 在 302 前自动 `ApiStats::hitProxy`；转发上游前去掉 `key` / `api_key` / `apikey`。

4. **次数**  
   每次成功记账同步 `api.calls + 1`。

## 升级注意

- 后台「系统升级」执行**数据库结构更新**（应用 `3.18.0` 迁移）。
- 本地接口须在业务前注入统计两行，且后台 `endpoint` 与脚本路径一致（如 `/api/yiyan/v1.php`）。
- 密钥体系与扣费尚未接入，界面勿宣传已扣费。

## 库变更

是（新建 `{prefix}apilog`）。
