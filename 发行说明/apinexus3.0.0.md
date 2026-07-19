# ApiNexus 3.0.0 发行说明

**发布日期：** 2026-07-13  
**版本类型：** 大版本（用户权限体系）  
**数据库变更：** 是（`install/migrations/3.0.0.sql`）

---

## 概述

本版本建立**普通用户 / 开发者**双角色权限体系，为后续「用户发布接口」能力铺路。权限以数据库 `role` 字段为准，每次请求从服务端读取，无法通过改 URL 或抓包绕过。

---

## 用户角色

| 角色 | 数据库值 | 能力 |
|------|----------|------|
| 普通用户 | `user` | 生成密钥、调用平台全部公开接口；**无** API 管理入口 |
| 开发者 | `developer` | 普通用户全部能力 + 用户中心「API 管理」（界面仍为占位） |

- 注册时默认 **普通用户**，可在注册页选择 **开发者**
- 管理员可在「用户管理」将身份在两种角色间转换

---

## 新增 core 类

| 文件 | 作用 |
|------|------|
| `core/UserRole.php` | 角色常量、`normalize()`、`currentCanPublishApi()` |
| `core/FrontendUser.php` | `current()` / `format()` 输出用户名、头像、邮箱、角色 |

---

## 主要改动文件

| 区域 | 文件 |
|------|------|
| 数据库 | `install/database.sql`、`install/migrations/3.0.0.sql` |
| 注册 | `user/register.php`、两主题 `user/auth/register.php` |
| 用户中心 | `user/init.php`、`user/includes/layout.php`、`user/api-manage.php`、`user/account.php` |
| 后台 | `admin/users.php`、`assets/js/users.js` |
| 菜单 | `core/ThemeManager.php`（按角色过滤 API 管理） |
| 样式 | `assets/css/auth-login.css`、`assets/css/admin.css`、slate `auth.css` |

---

## 升级注意

1. 在线更新或覆盖代码后，执行数据库迁移（后台「安装更新」会自动应用 `3.0.0.sql`）
2. 已有用户迁移后 `role` 默认为 `user`（普通用户）
3. 需将贡献者升级为开发者时，在后台用户管理点击「设为开发者」

---

## 下载

- Tag：`v3.0.0`
- 附件：`apinexus3.0.0.zip`
