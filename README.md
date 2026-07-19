<div align="center">
  <h1>APINEXUS</h1>
</div>

<p align="center">
  <strong>开放接口平台 · 自部署管理 · 云端在线更新</strong>
</p>

<p align="center">
  <code>version 4.1.1</code>
  ·
  <code>License 开源</code>
  ·
  <a href="https://gitee.com/xunjinlu/apinexus"><code>Gitee</code></a>
  ·
  <a href="https://gitcode.com/xunjinlu/apinexus"><code>GitCode</code></a>
  ·
  <a href="https://github.com/whr884657/apinexus"><code>GitHub</code></a>
  ·
  <code>PHP 7.4+</code>
  ·
  <code>MySQL 5.7+</code>
</p>

---

## 项目简介

**ApiNexus** 是一套可自部署的**开放 API 接口平台**管理系统。基于 PHP + MySQL，无重型框架依赖，提供前台接口目录与在线调试、后台接口审核与分类管理，以及用户体系与云端在线更新。

**主要能力：**

- Web 五步安装向导，自动创建数据表与初始配置
- **双端认证**：管理员后台（安装时创建）+ 用户中心（邮箱验证码注册 + QQ/Gitee OAuth）
- **API 管理（已实现）**：后台接口列表与分类；接口审核（待审核/通过/不通过，可选拒绝原因）；用户中心开发者投稿与邮件通知
- **调用统计（v3.18+）**：本地脚本头 ≤3 行 `ApiStats::hit()`（见 `api/统计代码使用说明.md`）+ 代理 `/apis/{短码}` 自动记账；日志表 `apilog`（含 `iploc` 预留）
- **用户令牌（v3.29+）**：表 `apikey`；用户中心与管理员后台均可管理；格式 `sk-`+32 位（小写前缀）；每账号最多 3 个；本地/代理调用已校验密钥并累计次数
- **积分计费与充值（v3.33+ / v3.34）**：接口收费扣积分；用户充值中心扫码支付；订单管理与积分变动分栏；回调直访 `core/play/codeplay/notify.php`

- **前台双主题**：默认主题（FeerApi 风：粒子背景、终端 Hero、接口目录、在线调试）+ 主题二 slate（API 平台风：搜索与**数据库分类**筛选、接口卡片列表）；首页与全部接口页分类标签默认显示 15 个、超出「更多」展开；各主题 CSS/JS/shell **完全独立**
- 前台页面：首页、全部接口、文章、贡献者、友情链接、赞助、关于（导航支持伪静态，URL 无 `.php` 后缀）
- 分组侧边栏管理后台（控制台、数据大屏、API 管理、内容运营、交易财务、系统管理）
- 用户中心侧边栏：控制台、API 管理（仅开发者，可投稿）、**令牌管理**（创建/重置/禁用，每账号最多 3 个）、积分变动、接口列表、账号设置（部分为占位页）
- **用户角色**：普通用户（调用全站接口、管理令牌）/ 开发者（可进入 API 管理提交接口待审）；注册页横条分段选择身份，管理员可转换身份
- 用户管理：列表查看、搜索、封禁/解封/删除、身份转换（AJAX 无整页刷新）
- 用户头像：QQ 邮箱自动匹配 / 自定义链接 / 默认头像
- 用户登录支持 QQ / Gitee 第三方登录（须先注册并绑定）
- 管理员认证：登录、忘记密码（邮箱验证码）、CSRF 与登录频率限制
- 邮箱验证码发信限流（MySQL 表 `mail_code_rate_log` + 一次性 mail_ticket）
- 站点信息、注册邮箱后缀白名单、SMTP 邮箱发信
- **站点扩展（v3.28+）**：自定义底栏左/中/右 HTML、站点运行时间、页脚 1～2 个二维码；主题可开关运行时间 / 二维码 / 合作伙伴
- **SEO（v3.28+）**：公开页 description / OG / Twitter 卡片；后台与登录 `noindex`；根目录 `robots.txt`
- **云端在线更新**：后台检测新版本、分步下载安装、可选数据库结构迁移
- 认证页角色动画背景；后台 `vs-overlay` 大弹窗（电脑约 92% 视口 / 手机全宽 85vh 抽屉）与 Toast 提示
- 简洁白色后台主题，纯 CSS 图标，适配电脑端与手机端

### UI 规范（弹窗 / 布局）

- **手机端（≤900px）**：侧边栏默认隐藏，点击顶栏菜单从右侧滑出；点击遮罩关闭
- **电脑端（≥768px）**：侧边栏默认展开，可收缩
- **后台弹窗**：`vs-overlay--lg`——电脑端约 92% 视口居中（上限 1440×960），手机端 100% 宽底部抽屉（85vh），内容区可滚动
- **参数类型选择（v3.32+）**：嵌套选择器——电脑端弹窗中的弹窗、手机端抽屉中的抽屉；预设类型自适应网格 + 自定义输入
- **认证页**：可交互角色动画背景，随前台主题联动

---

## 代码仓库与下载

| 平台 | 链接 | 说明 |
|------|------|------|
| **Gitee（主仓库 / 默认更新源）** | [xunjinlu/apinexus](https://gitee.com/xunjinlu/apinexus) | 国内主源；发版与在线更新优先 |
| **GitCode（镜像）** | [xunjinlu/apinexus](https://gitcode.com/xunjinlu/apinexus) | Gitee 不可用时的第二更新源 |
| **GitHub（镜像）** | [whr884657/apinexus](https://github.com/whr884657/apinexus) | 海外兜底更新源 |
| **发行版下载** | [Gitee Releases](https://gitee.com/xunjinlu/apinexus/releases) | 推荐手动下载入口 |

压缩包命名：`apinexus{版本号}.zip`（如 `apinexus4.1.0.zip`）。各版本详细说明见 `发行说明/` 目录。

完整版本历史见 **[更新记录.md](更新记录.md)**。

---

## 后台框架特性

- **自定义 PHP 架构**：无 Laravel / ThinkPHP 等重型框架依赖
- **API 业务层**：`ApiManager`（接口列表 CRUD 与状态）、`ApiCategoryManager`（分类与 `category` 表）
- **白色主题**：顶部栏 + 可收缩分组侧边栏
- **电脑端**：侧边栏默认展开，点击左上角可收缩/展开
- **手机端**：侧边栏默认隐藏，点击顶栏菜单滑出
- **弹窗体系**：`assets/css/modal.css` 中的 `vs-overlay` / `vs-overlay--lg`，电脑约 92% 视口、手机全宽抽屉
- **会话超时**：长时间无操作自动退出（可配置）
- **系统可配置**：名称、描述、关键词、Favicon、Logo 可在后台修改
- **源码开放**：全部逻辑可阅读、可二次开发

---

## 环境要求

- **PHP** 7.4 / 8.0 / 8.2（推荐 8.0+）
- **MySQL** 5.7+ 或 MariaDB 10.3+
- **PHP 扩展**：pdo、pdo_mysql、**redis**、mbstring、json、session、curl、openssl、zip
- **目录权限**：`config/`、`data/` 可写；安装后自动生成 `config/database.php`

---

## 目录结构

```
ApiNexus/
├── README.md
├── 更新记录.md                 # 完整版本历史（README 仅保留最新一条）
├── CORE模块说明.md             # core/ 下全部 PHP 类说明与主题对接指南
├── LICENSE                     # 开源协议
├── update.json                 # 远程版本清单（在线更新检测）
├── update-log.json             # 版本更新记录
├── 404.php                     # 全站 404（含安全法律提示）
├── index.php                   # 前台首页（主题驱动）
├── apis.php                    # 全部接口列表 + 代理网关（对外 /apis/{短码}，内记统计）
├── api/                        # 本地业务接口脚本（头部注入 ApiStats::hit）+ 统计代码使用说明.md
│   └── yiyan/                  # 示例：随机一言
├── articles.php                # 前台 · 文章
├── links.php                   # 前台 · 友情链接
├── sponsor.php                 # 前台 · 赞助
├── contributors.php            # 前台 · 贡献者
├── about.php                   # 前台 · 关于
├── .htaccess                   # Apache 伪静态（可选）
├── admin/                      # 后台
│   ├── init.php                # 后台统一引导
│   ├── includes/
│   │   ├── layout.php          # 侧边栏布局
│   │   └── auth_layout.php     # 登录/注册/忘记密码布局
│   ├── api/                    # API 管理
│   │   ├── list.php            # 接口列表（添加/编辑/状态）
│   │   ├── categories.php      # 接口分类
│   │   └── review.php / docs.php / feedback.php  # 占位
│   ├── content/                # 内容运营（占位）
│   ├── finance/                # 交易财务（支付配置/订单/积分）
│   ├── system/                 # 系统管理扩展（日志等）
│   ├── index.php               # 控制台
│   ├── data-screen.php         # 数据大屏（占位）
│   ├── users.php               # 用户管理
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
│   ├── api-manage.php / keys.php     # API 投稿 / 令牌管理（已实现）
│   ├── points.php / apis.php         # 占位
│   ├── account.php
│   └── login.php / register.php / forgot.php
├── assets/
│   ├── css/                    # common, admin, modal (vs-overlay), toast, install …
│   ├── js/                     # common.js, user-keys.js, user-api-manage.js, admin-keys.js …
│   └── img/
│       ├── category-icons/     # 内置分类 / 接口 SVG 图标库（自动扫描）
│       └── …                   # 头像、站点图片等
├── config/
│   ├── database.php            # 安装后生成（更新时不覆盖）
│   └── install.lock            # 安装锁定文件
├── core/
│   ├── bootstrap.php
│   ├── version.php             # VS_VERSION 版本常量
│   ├── ThemeManager.php        # 前台主题加载与切换
│   ├── ApiManager.php          # 接口列表 CRUD 与状态
│   ├── ApiStats.php            # 本地/代理调用统计（apilog）
│   ├── ApiProxy.php            # 代理网关 /apis/{短码}
│   ├── ApiCategoryManager.php  # 接口分类（后台 CRUD）
│   ├── FrontendCategory.php    # 前台分类（主题调用）
│   ├── FrontendApi.php         # 前台公开接口（主题调用）
│   ├── FrontendUser.php        # 前台用户资料（主题/用户中心调用）
│   ├── UserRole.php            # 用户角色与权限判断
│   ├── RedisService.php        # Redis 连接与监控采集
│   ├── RedisCache.php          # 业务数据缓存（接口/分类/日志分页/限流）
│   ├── ApiLogManager.php       # API 调用日志查询与格式化
│   ├── theme/default/          # 默认主题（FeerApi 风白色 UI）
│   ├── theme/slate/            # 主题二（API 平台风）
│   │   └── preview.png         # 主题预览图
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

**core 各 PHP 类详细说明、用法与主题对接规范见根目录 [CORE模块说明.md](CORE模块说明.md)。**

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

项目根目录 `.htaccess` 已含：全站可用规则 + `/apis/{短码}` → `apis.php?_vs_slug=短码`。启用 `mod_rewrite` 即可。

### Nginx（宝塔「伪静态」请只粘贴英文规则，不要带中文注释）

**可直接粘贴（推荐完整）：**

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

若站点配置里已有 `location / { try_files ... }`，**只追加**上面的 `apis` 段，并放在 `location /` **上面**。

> **注意：** 不要写 `[a-z0-9]{3,64}`。宝塔伪静态保存时会吞掉 `{…}`，正则变成 `^/apis/([a-z0-9]` 导致 `pcre_compile() failed: missing )`。长度校验由 PHP 完成即可。  
> **注意：** 不要 rewrite 到 `/apis.php/$1`（PATH_INFO），面板 PHP 常只匹配以 `.php` 结尾的 URI。  
> **接口详情不需要伪静态：** 出站地址为 `/detail.php/{id}`（PATH_INFO），由 PHP 解析。

详情见 [`nginx伪静态配置.md`](nginx伪静态配置.md)。

---

## 在线更新

登录后台后会向**云端**检测最新版本（读取 `update.json`）。若本地 `core/version.php` 中的版本号**低于**远程版本，可在「系统升级」中安装更新；若本地**高于**远程（开发测试环境），则提示无需更新。

**更新过程（分步进度）：**

1. 从云端下载资源包
2. 解压更新包
3. 覆盖系统文件（**绝不替换** `config/database.php`、`config/install.lock`，**不覆盖** 运行时 `data/`）
4. 若该版本含数据库结构变更，则执行 `install/migrations/` 增量 SQL
5. 完成后自动清理 `data/update/` 临时文件

**更新源顺序：** Gitee（默认）→ GitCode → GitHub；清单与更新包均按此顺序自动兜底。

**若在线更新失败：** 请从 [Gitee 发行页](https://gitee.com/xunjinlu/apinexus/releases) 手动下载最新 `apinexus{版本}.zip` 覆盖（保留 `config/database.php`）；也可从 [GitCode](https://gitcode.com/xunjinlu/apinexus) / [GitHub](https://github.com/whr884657/apinexus) 获取同步发行包。

**服务器要求：** PHP `ZipArchive` 扩展、可写项目目录、可访问云端更新源。

---

## 版本记录

### v4.1.1（2026-07-19）

- 修复在线更新因 `发行说明` 等文档不可写导致整次覆盖失败
- 非关键文档写入失败时跳过并继续；写入链路多级兜底

更多历史版本请查看 **[更新记录.md](更新记录.md)**。

---

## 开源协议

本项目采用 **[ApiNexus 开源许可协议](LICENSE)**。

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

- Gitee（主）：[https://gitee.com/xunjinlu/apinexus](https://gitee.com/xunjinlu/apinexus)
- GitCode：[https://gitcode.com/xunjinlu/apinexus](https://gitcode.com/xunjinlu/apinexus)
- GitHub：[https://github.com/whr884657/apinexus](https://github.com/whr884657/apinexus)
- 问题反馈：优先通过 Gitee Issues 提交

---

> 维护者本地文档（README 编写要点、发版检查清单等）不随仓库发布，请在本地查阅。
