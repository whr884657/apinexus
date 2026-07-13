# misc-api 2.15.1 发行说明

**发布日期：** 2026-07-13  
**类型：** 小版本（补丁，含数据库迁移）

## 下载

- ZIP：https://gitee.com/xunjinlu/misc-api/releases/download/v2.15.1/misc-api2.15.1.zip
- 标签：`v2.15.1`

## 变更摘要

1. **表名精简**  
   接口分类表由 `{prefix}api_category` 改为 `{prefix}category`，与 `user`、`api`、`config` 等命名风格一致。

2. **升级兼容**  
   - 新安装：`install/database.sql` 直接创建 `category`  
   - 自 v2.15.0 升级：执行 `2.15.1.sql` 自动 `RENAME TABLE`  
   - 已存在 `category` 表：跳过迁移

3. **开发规范**  
   新增 `开发规范/数据库命名规范.md`（本地维护）：表名能短则短，避免 `api_` 等冗余前缀。

## 升级说明

1. 覆盖或在线更新至 v2.15.1  
2. **系统管理 → 系统升级**，执行数据库结构更新（应用 `2.15.1`）  
3. 无需改业务配置；`ApiCategoryManager` 已指向新表名

## 相关文件

| 路径 | 说明 |
|------|------|
| `install/migrations/2.15.1.sql` | 表重命名 |
| `core/ApiCategoryManager.php` | `Database::table('category')` |
| `开发规范/数据库命名规范.md` | 命名规范 |
