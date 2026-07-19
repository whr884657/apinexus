# ApiNexus 3.8.0 发行说明

**发布日期：** 2026-07-14  
**类型：** 中版本（库表结构大改：中文注释、状态数字化、审核字段）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v3.8.0/apinexus3.8.0.zip
- 标签：`v3.8.0`

## 变更摘要

1. **全库中文注释**  
   所有表、所有字段补齐详细中文 `COMMENT`（含 `id`、时间戳；去掉纯英文注释如 `password hash`）。

2. **接口状态数字化**  
   `api.status`：`0` 正常 · `1` 禁用 · `2` 维护（原 `normal`/`disabled`/`maintenance` 升级时自动转换）。

3. **审核字段**  
   新增 `audit_status`：`0` 审核不通过 · `1` 审核通过。  
   管理员后台发布默认审核通过；未通过接口前台不展示。  
   后台「接口审核」页可筛选并切换通过/不通过。

4. **开发规范**  
   《数据库开发规范》明确：注释必须中文；多态判断字段必须用 `0/1/2…` 并写清含义。

## 升级说明

1. 覆盖或在线更新至 v3.8.0  
2. **系统管理 → 系统升级**，执行数据库结构更新（应用 `3.8.0`）  
3. 升级后请在库表工具中确认字段注释与 `audit_status` 已存在

## 相关文件

| 路径 | 说明 |
|------|------|
| `install/database.sql` | 新装结构（全中文 COMMENT + 数字状态 + 审核） |
| `install/migrations/3.8.0.sql` | 存量升级迁移 |
| `core/ApiManager.php` / `FrontendApi.php` | 数字状态与审核过滤 |
| `admin/api/list.php` / `review.php` | 后台列表与审核 |
| `开发规范/数据库开发规范.md` | 本地规范（不进 ZIP） |
