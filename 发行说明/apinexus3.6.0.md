# ApiNexus 3.6.0 发行说明

**发布日期：** 2026-07-14  
**版本类型：** 大版本（接口数据模型重构 + 后台列表落地）  
**数据库变更：** 是（`install/migrations/3.6.0.sql`，会清空并重建 `{prefix}api` 表）

---

## 概述

清除旧版「审核向」API 表结构，按运营侧「接口列表」重新设计字段与状态；完成后台添加/编辑/状态管理，并对接前台展示与维护拦截。

---

## 变更

| 项 | 说明 |
|----|------|
| 数据库 | 重建 `api` 表：名称、描述、地址、GET/POST、请求参数、返回示例、普通/AI 文档、`call_count`、`require_key`、状态、`icon`、可选分类 |
| 状态 | `normal` / `disabled`（前台不展示）/ `maintenance`（前台可见，请求返回「维护中」） |
| 后台 | `/admin/api/list.php` 大弹窗 CRUD；接口审核页改回占位 |
| 前台 | `FrontendApi` 输出新字段；默认主题调试区拦截维护中接口 |
| 安全 | 接口地址限 http(s)；列表不回显大文档字段，编辑时按需拉取 |

---

## 升级说明

1. 本版 `db_changes: true`，在线升级或覆盖后请执行「系统管理 → 系统升级」中的数据库结构更新。
2. **迁移会 DROP 旧 `api` 表**：旧审核数据不会保留，请提前备份如有需要。
3. 升级后到后台「接口列表」重新添加接口。

---

## 下载

- Tag：`v3.6.0`
- 附件：`apinexus3.6.0.zip`
- 直链：https://gitee.com/xunjinlu/apinexus/releases/download/v3.6.0/apinexus3.6.0.zip
