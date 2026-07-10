# misc-api

<p align="center">
  <strong>轻量 Web 管理 · 安装向导 · 安全认证 · 云端在线更新</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-1.3.0-blue" alt="version">
  <img src="https://img.shields.io/badge/License-开源-green" alt="license">
  <a href="https://gitee.com/xunjinlu/misc-api"><img src="https://img.shields.io/badge/Gitee-代码仓库-C71D23?logo=gitee" alt="Gitee"></a>
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white" alt="MySQL">
</p>

---

## 项目简介

**misc-api** 是一套可自部署的轻量级 Web 管理系统：安装后在浏览器中管理站点信息、管理员与用户账号、注册策略与邮件发信，并支持从**云端**检测与安装系统更新。

**主要能力：**

- Web 五步安装向导，自动创建数据表与初始配置
- **双端认证**：管理员后台（安装时创建）+ 用户中心（邮箱验证码注册 + QQ/Gitee OAuth）
- 分组侧边栏管理后台（控制台、用户管理、系统设置、系统升级、关于）
- 用户中心：自适应侧边栏 + 顶栏，控制台与账号设置
- 用户头像：QQ 邮箱自动匹配 / 自定义链接 / 默认头像
- 用户登录支持 QQ / Gitee 第三方登录（须先注册并绑定）
- 管理员认证：登录、忘记密码（邮箱验证码）、CSRF 与登录频率限制
- 站点信息、注册邮箱后缀白名单、SMTP 邮箱发信
- **云端在线更新**：后台检测新版本、分步下载安装、可选数据库结构迁移
- 角色动画登录页、主题切换、统一弹窗与 Toast 提示
- 简洁白色后台主题，纯 CSS 图标，适配电脑端与手机端

### UI 规范（弹窗 / 布局）

- **手机端（≤767px）**：侧边栏默认隐藏，点击顶栏菜单滑出；表单与弹窗全宽适配
- **电脑端（≥768px）**：侧边栏默认展开，可收缩；弹窗居中，内容区可滚动
- **登录页**：可交互角色动画背景，支持主题色切换

---

## 代码仓库与下载

| 平台 | 链接 |
|------|------|
| **Gitee（主仓库）** | [xunjinlu/misc-api](https://gitee.com/xunjinlu/misc-api) |
| **发行版下载** | [Releases · 下载 ZIP](https://gitee.com/xunjinlu/misc-api/releases) |

压缩包命名：`misc-api{版本号}.zip`（如 `misc-api1.0.0.zip`）。各版本详细说明见 `发行说明/` 目录。

---

## 功能列表

| 功能 | 路径 | 说明 |
|------|------|------|
| 前台首页 | `/` | 引导进入用户中心（不展示管理后台入口） |
| Web 安装向导 | `/install/` | 五步安装，执行 `install/database.sql` |
| 用户登录 | `/user/login.php` | 账号密码登录 + QQ/Gitee 第三方登录 |
| OAuth 回调 | `/user/oauth/callback.php` | 第三方授权回调（由平台配置） |
| OAuth 绑定 | `/user/oauth/bind.php` | 首次第三方登录绑定已有账号 |
| 用户注册 | `/user/register.php` | 邮箱验证码注册（需管理员已配置发信） |
| 用户忘记密码 | `/user/forgot.php` | 邮箱验证码重置密码 |
| 用户中心 | `/user/index.php` | 登录后控制台首页 |
| 用户账号设置 | `/user/account.php` | 修改资料、头像、密码 |
| 管理员登录 | `/admin/login.php` | 管理员登录（安装时创建账号，无开放注册） |
| 管理员忘记密码 | `/admin/forgot.php` | 邮箱验证码重置（需配置 SMTP） |
| 管理控制台 | `/admin/index.php` | 后台首页，展示站点与版本信息 |
| 用户管理 | `/admin/users.php` | 查看全部用户、OAuth 绑定状态 |
| 账号设置 | `/admin/account.php` | 修改用户名、邮箱、头像、密码 |
| 系统设置 | `/admin/settings.php` | 站点信息、注册策略、OAuth、邮箱发信 |
| 系统升级 | `/admin/upgrade.php` | 手动检测更新、安装更新、查看更新记录 |
| 关于 | `/admin/about.php` | 系统与环境信息 |
| 更新 API | `/admin/update.php` | 在线更新接口（版本检测 / 分步更新） |

---

## 后台框架特性

- **自定义 PHP 架构**：无 Laravel / ThinkPHP 等重型框架依赖
- **白色主题**：顶部栏 + 可收缩分组侧边栏
- **电脑端**：侧边栏默认展开，点击左上角可收缩/展开
- **手机端**：侧边栏默认隐藏，点击顶栏菜单滑出
- **会话超时**：长时间无操作自动退出（可配置）
- **系统可配置**：名称、描述、关键词、Favicon、Logo 可在后台修改
- **源码开放**：全部逻辑可阅读、可二次开发

---

## 环境要求

- **PHP** 7.4 / 8.0 / 8.2（推荐 8.0+）
- **MySQL** 5.7+ 或 MariaDB 10.3+
- **PHP 扩展**：pdo、pdo_mysql、mbstring、json、session、curl、openssl、zip
- **目录权限**：`config/` 可写；安装后自动生成 `config/database.php`

---

## 目录结构

```
misc-api/
├── README.md
├── LICENSE                     # 开源协议
├── update.json                 # 远程版本清单（在线更新检测）
├── update-log.json             # 版本更新记录
├── index.php                   # 前台首页
├── .htaccess                   # Apache 伪静态（可选）
├── admin/                      # 后台
│   ├── init.php                # 后台统一引导
│   ├── includes/
│   │   ├── layout.php          # 侧边栏布局
│   │   └── auth_layout.php     # 登录/注册/忘记密码布局
│   ├── index.php               # 控制台
│   ├── login.php / forgot.php
│   ├── account.php             # 账号设置
│   ├── settings.php            # 系统设置
│   ├── upgrade.php             # 系统升级
│   ├── update.php              # 更新 API
│   └── about.php               # 关于
├── user/                       # 用户中心
│   ├── init.php
│   ├── includes/layout.php
│   ├── index.php
│   ├── account.php
│   └── login.php / register.php / forgot.php
├── assets/
│   ├── css/                    # common, admin, modal, toast, install …
│   ├── js/                     # common.js, vs-update.js, upgrade.js …
│   └── img/                    # 头像、站点图片等
├── config/
│   ├── database.php            # 安装后生成（更新时不覆盖）
│   └── install.lock            # 安装锁定文件
├── core/
│   ├── bootstrap.php
│   ├── version.php             # VS_VERSION 版本常量
│   ├── Auth.php / UserAuth.php # 管理员与用户认证
│   ├── Updater.php             # 云端在线更新
│   ├── UpdateLog.php           # 更新记录读取
│   └── DatabaseMigrator.php    # 数据库增量迁移
├── data/                       # 运行时数据（更新临时文件等，自动创建）
├── install/
│   ├── index.php               # 五步安装向导
│   ├── database.sql            # 全新安装数据库结构
│   └── migrations/             # 在线升级增量 SQL
└── 发行说明/                   # 各版本发行说明 Markdown
```

---

## 安装说明

1. 上传代码到 Web 服务器（或 `git clone` 后部署）
2. 确保 `config/` 目录可写
3. 创建 MySQL 空数据库
4. 访问 `https://你的域名/install/` 完成五步安装
5. 安装完成后访问 `/admin/login.php` 登录后台

---

## 伪静态 / URL 重写

### Apache

项目根目录已包含 `.htaccess` 推荐配置，启用 `mod_rewrite` 即可。

### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## 在线更新

登录后台后会向**云端**检测最新版本（读取 `update.json`）。若本地 `core/version.php` 中的版本号**低于**远程版本，可在「系统升级」中安装更新；若本地**高于**远程（开发测试环境），则提示无需更新。

**更新过程（分步进度）：**

1. 从云端下载资源包
2. 解压更新包
3. 覆盖系统文件（**绝不替换** `config/database.php`、`config/install.lock`，**不覆盖** 运行时 `data/`）
4. 若该版本含数据库结构变更，则执行 `install/migrations/` 增量 SQL
5. 完成后自动清理 `data/update/` 临时文件

**若在线更新失败：** 请从 [发行页](https://gitee.com/xunjinlu/misc-api/releases) 手动下载最新 `misc-api{版本}.zip` 覆盖（保留 `config/database.php`）。

**服务器要求：** PHP `ZipArchive` 扩展、可写项目目录、可访问云端更新源。

---

## 版本记录

### v1.3.0（2026-07-11）

**类型：** 大版本（OAuth 聚合登录 + 用户管理）

**变更说明：**

- 用户登录页支持 QQ / Gitee OAuth（须先注册，首次绑定验证账号密码）
- 用户表新增 `oauth_qq_openid`、`oauth_gitee_id`
- 后台新增用户管理页（响应式表格/卡片）
- 系统设置新增 OAuth 配置

### v1.2.0（2026-07-11）

**类型：** 次大版本（修复 + UI + 注册策略 + 架构简化）

**变更说明：**

- 修复系统升级页更新记录无法显示
- 统一界面提示组件（`vs-notice`），新增《界面提示规范.md》
- 注册邮箱后缀白名单（JSON 配置）
- 移除多域名/子域名绑定功能

### v1.1.1（2026-07-11）

**类型：** 小版本（用户后台 UI 与账号能力）

**变更说明：**

- 用户后台自适应侧边栏 + 顶栏布局
- 新增用户账号设置页与头像支持
- 官网隐藏管理后台入口

### v1.1.0（2026-07-10）

**类型：** 大版本（用户体系）

**变更说明：**

- 新增用户模块：登录、邮箱注册、忘记密码、用户中心
- 新增 `vs_user` 数据表
- 移除管理员开放注册
- 用户注册需管理员配置邮箱发信

### v1.0.0（2026-07-10）

**类型：** 初始版本

**变更说明：**

- misc-api 初始版本发布
- 管理员认证与安全防护
- 系统设置（站点信息、邮箱发信）
- 在线更新机制
- 安装向导（五步 Web 安装）
- 响应式后台管理界面
- 角色动画登录页面
- 主题切换

---

## 开源协议

本项目采用 **[misc-api 开源许可协议](LICENSE)**。

### 您可以

- **学习研究**：阅读、学习本项目全部源码
- **个人使用**：在个人网站、项目中免费使用
- **商业使用**：在商业项目中免费使用
- **修改分发**：可修改代码并再发布（需保留版权声明与协议全文）

### 作者声明（免责）

- 本项目按 **「原样（AS IS）」** 提供，**不提供任何明示或暗示的担保**
- 因使用、无法使用或依赖本项目而产生的任何直接、间接、附带、特殊或后果性损害（包括但不限于数据丢失、业务中断、安全漏洞、法律纠纷等），**作者不承担任何责任**
- 使用者应自行评估安全风险，并在生产环境中做好备份、加固与合规审查

完整法律条文见仓库根目录 **[LICENSE](LICENSE)** 文件。

---

## 作者与仓库

- 仓库地址：[https://gitee.com/xunjinlu/misc-api](https://gitee.com/xunjinlu/misc-api)
- 问题反馈：请通过 Gitee Issues 提交

---

> 维护者本地文档（README 编写要点、发版检查清单等）不随仓库发布，请在本地查阅。
