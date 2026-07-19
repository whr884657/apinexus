# ApiNexus 1.8.0 发行说明

**发布日期：** 2026-07-11  
**类型：** 大版本（安装检测 + 数据库迁移 + 管理员用户绑定）

---

## 变更摘要

### 安装环境检测（Step 1）

| 检测项 | 要求 |
|--------|------|
| `pdo` / `pdo_mysql` | **必选**（MySQL 数据库） |
| `redis` | **必选**（后续 Redis 缓存，未安装禁止进入下一步） |
| `config/`、`data/` | **目录可写** |
| `curl`、`openssl`、`zip` 等 | 必选 |

### 数据库（`db_changes: true`）

- `vs_admin` 表新增 `bound_user_id`（唯一索引）
- 迁移脚本：`install/migrations/1.8.0.sql`
- 新安装已写入 `install/database.sql`

### 管理员用户绑定

- 路径：系统管理 → 账号设置 → **发布身份绑定**
- 管理员可绑定一个已注册用户；后台发布 API/文章等内容将使用该用户身份
- 绑定用户对该管理员发布的内容拥有同等增删改查权限（内容模块后续落地）
- 普通用户可自主注册并发布 API/TAPI（接口模块后续落地）

### 新增文件

- `core/AdminUserBinding.php` — 绑定/解绑/发布身份解析

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v1.8.0/apinexus1.8.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

1. 备份数据库与 `config/`
2. 覆盖业务文件后访问 **系统升级**，执行 `1.8.0` 数据库迁移
3. 服务器须已安装 **PHP Redis 扩展**，否则安装向导与环境检测将无法通过
4. 管理员登录后在账号设置完成 **用户账号绑定**，否则后续后台发布接口将不可用

---

*ApiNexus 1.8.0*
