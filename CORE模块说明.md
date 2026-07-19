# ApiNexus · core 核心模块说明

> **文档位置：** 项目根目录 `CORE模块说明.md`  
> **适用读者：** 主题开发者、二次开发者、维护者  
> **当前版本：** 以 `core/version.php` 中 `VS_VERSION` 为准  

---

## 一、core 目录是做什么的？

`core/` 是 ApiNexus 的**业务内核**：所有与数据库、认证、配置、前台数据调度相关的 PHP 类都集中在这里。  
入口页（如 `index.php`、`admin/`、`user/`）只需：

```php
define('VS_ROOT', __DIR__);
require_once VS_ROOT . '/core/bootstrap.php';
```

`bootstrap.php` 会按固定顺序加载下方全部核心类，并启动 Session、CSRF。

### 设计原则

| 层级 | 目录/类 | 职责 |
|------|---------|------|
| **后台管理** | `ApiCategoryManager`、`ApiManager`、`UserManager`… | 后台 CRUD、审核、配置 |
| **前台主题** | `FrontendCategory`、`FrontendApi`、`ThemeManager` | 主题只调这些类，**不直接写 SQL/表名** |
| **认证安全** | `Auth`、`UserAuth`、`AuthSecurity` | 管理员/用户登录、CSRF、限流 |
| **基础设施** | `Database`、`Config`、`InstallChecker`… | 连接、配置、安装、迁移 |

### core 与 theme 的边界（必须遵守）

| 放在 `core/` | 放在 `core/theme/{id}/` |
|--------------|-------------------------|
| 读写的数据库逻辑、业务规则 | HTML 结构、CSS、JS、页面布局 |
| 后台管理类（`*Manager`） | 主题配置项（`theme.json` settings） |
| 前台调度类（`Frontend*`） | 调用 core 类展示数据 |
| 在 `bootstrap.php` 注册 | **禁止**直接 `Database::connect()` / 写表名 |
| 全主题共用的数据格式约定 | 各主题独立的视觉与交互 |

**一句话：** core 负责「数据从哪来、规则是什么」；主题负责「数据怎么展示」。

---

```
version.php → helpers.php → InstallChecker → Database → DatabaseInstaller
→ DatabaseMigrator → SiteContext → RegisterPolicy → Config
→ Mailer → Auth → UserAuth → RateLimitStore → AuthSecurity → AjaxResponse
→ SystemInfo → Updater → UpdateLog → UserAvatar → UserManager
→ AdminUserBinding → ApiManager → ApiCategoryManager
→ ApiStats → ApiKeyManager → FrontendCategory → FrontendApi → ThemeManager
→ oauth/* → Session 启动
```

---

## 二、core 开发规范与后续流程

> **核心要求：** 任何需要读库的前台能力，必须**先在 `core/` 开发完成**，再由主题调用。主题不得绕过 core 直接访问数据库。

### 2.1 core 的核心作用

`core/` 是整个 ApiNexus 的**后端数据中心与规则引擎**，承担：

1. **统一数据出口** — 主题、入口页、AJAX 都通过 core 类取数，避免各主题各写一套 SQL  
2. **统一业务规则** — 审核状态、启禁、排序、可见性等逻辑只写一次  
3. **统一命名与格式** — 如分类键 `all` + 数据库 `id`，全主题一致  
4. **可扩展性** — 新增主题三、用户自研主题时，只需调用已有 `Frontend*` 类  

### 2.2 两类 core 类（命名约定）

每块业务能力通常拆成 **一对** 类（后台 + 前台）：

| 类型 | 命名模式 | 放置位置 | 调用方 | 示例 |
|------|----------|----------|--------|------|
| **后台管理类** | `XxxManager` | `core/XxxManager.php` | `admin/` 后台页、AJAX | `ApiCategoryManager` |
| **前台调度类** | `FrontendXxx` | `core/FrontendXxx.php` | `core/theme/*/pages/` | `FrontendCategory` |

**规则：**

- 后台类：CRUD、审核、配置、图标上传等**管理操作**  
- 前台类：只读、已格式化、适合模板/`json_encode` 的**展示数据**  
- 主题**只调用 `Frontend*` 类**；不要调用 `*Manager` 类渲染前台页面  

### 2.3 标准开发流程（新增业务能力时）

以「文章」「友链」等为例，**必须按以下顺序**，不可颠倒：

```
① 数据库 / 迁移 SQL（如有新表）
       ↓
② core/XxxManager.php        ← 后台 CRUD、审核、状态
       ↓
③ admin/ 后台管理页 + AJAX    ← 运营人员维护数据
       ↓
④ core/FrontendXxx.php       ← 前台只读调度，格式化输出
       ↓
⑤ bootstrap.php 注册 require
       ↓
⑥ 各主题 pages/*.php 调用 FrontendXxx
       ↓
⑦ 更新本文档 + README
```

**禁止做法：**

- 在主题 `pages/articles.php` 里直接 `SELECT * FROM vs_article`  
- 在主题里复制一份分类/接口的 SQL 逻辑  
- 只做主题 UI、不补 `Frontend*` 类  

### 2.4 当前能力与进度

| 业务模块 | 后台类 | 前台调度类 | 后台管理页 | 主题可调用 | 状态 |
|----------|--------|------------|------------|------------|------|
| 接口分类 | `ApiCategoryManager` | `FrontendCategory` | `admin/api/categories.php` | ✅ 是 | **已完成** |
| 公开 API 接口 | `ApiManager` / `ApiNotify` / `ApiProxy` / `ApiStats` | `FrontendApi` / `FrontendStats` | `admin/api/list.php`、`review.php`、`user/api-manage.php`、`apis.php`、`detail.php` | ✅ 是 | **已完成**（本地/外链、详情 PATH_INFO、多选方法、审核三态、统计、双端 UI、主题二可配统计/配色） |
| 用户调用密钥 | `ApiKeyManager` | —（统计内校验） | `user/keys.php`、`admin/api/keys.php` | 用户中心/后台 | **已完成**（表 `apikey`；每账号最多 3 个；`sk-`+32；本地/代理校验与计数；页面勿用 `tokens` 命名） |
| 积分与支付 | `PointsManager` / `OrderManager` / `PayConfig` / `CodePayClient` | `FrontendUser`（余额） | `admin/finance/*`、`user/recharge`、`user/points`、`core/play/codeplay/notify.php` / `return.php` | 用户中心/后台 | **已完成**（`user.points`、`api.charge/price`、表 `orders`；码支付扫码充值；API 调用扣费） |
| 站点信息 | `Config` / `SiteContext` | `SiteContext` | `admin/settings.php` | ✅ 是 | **已完成** |
| 用户认证 | `UserAuth` / `UserManager` | `UserAuth` + `FrontendUser` | `user/`、`admin/users.php` | ✅ 是 | **已完成**（含角色 user/developer） |
| 管理员认证 | `Auth` | — | `admin/` | 后台专用 | **已完成** |
| 第三方登录 | `oauth/*` | `OAuthService` | 系统设置 | ✅ 是 | **已完成** |
| 文章 | — | — | 占位 | ❌ 否 | **待开发** |
| 友情链接 | — | — | 占位 | ❌ 否 | **待开发** |
| 公告 | — | — | 占位 | ❌ 否 | **待开发** |
| Redis 缓存 | — | `RedisService` / `RedisCache` | `admin/system/redis.php` | 后台专用 | **业务缓存已接入**（公开接口 / 前台展示 / 分类 / 日志分页 / 限流） |
| 贡献者 | — | — | 占位 | ❌ 否 | **待开发** |

> 上表「待开发」项：须先完成 `XxxManager` + `FrontendXxx` 并注册 bootstrap，主题才能接入；在此之前主题页仅能做静态占位。

### 2.5 已完成的参考范例：接口分类

**后台（管理数据）：**

- `ApiCategoryManager` — 增删改查、图标、描述、排序、启禁  
- 后台页 `admin/api/categories.php`  

**前台（主题读数据）：**

- `FrontendCategory` — 主题唯一入口  

```php
// 任意主题 pages/home.php — 仅示例，勿写 SQL
foreach (FrontendCategory::listTags() as $tag) {
    echo vs_e($tag['name']);  // id + name 已格式化
}
```

**主题不需要知道：** 表名 `vs_category`、字段 `sort_order` / `status`、图标解析逻辑。

### 2.6 后续扩展示例：文章模块（规划）

当需要开发「文章列表/详情」时，建议按下列文件规划（**先 core，后主题**）：

| 步骤 | 文件 | 说明 |
|------|------|------|
| 1 | `install/migrations/x.y.z.sql` | 文章表结构（若尚无） |
| 2 | `core/ArticleManager.php` | 后台：发布、下架、分类、CRUD |
| 3 | `admin/content/articles.php` | 后台管理界面 |
| 4 | `core/FrontendArticle.php` | 前台：`listForTheme()`、`findById()`、`listPaged()` |
| 5 | `bootstrap.php` | `require_once .../FrontendArticle.php` |
| 6 | `core/theme/*/pages/articles.php` | 各主题调用 `FrontendArticle::listForTheme()` |

**`FrontendArticle` 预期方法（规划，尚未实现）：**

```php
FrontendArticle::listForTheme($limit = 10);   // 首页摘要
FrontendArticle::listPaged($page, $pageSize); // 列表页分页
FrontendArticle::findBySlug($slug);           // 详情页
```

主题只负责排版；摘要用多少字、是否只显示已发布，由 `FrontendArticle` 内部决定。

### 2.7 新增 core 类检查清单

开发者在提交 core 新类前，请确认：

- [ ] 类文件位于 `core/` 根目录（oauth 等多文件模块可放子目录）  
- [ ] 文件头注释：文件名、作用、主要 public 方法  
- [ ] 已在 `bootstrap.php` 追加 `require_once`  
- [ ] 前台类以 `Frontend` 开头；后台类以 `Manager` 或职责命名  
- [ ] 不依赖任何 `core/theme/` 下的文件（core 不得引用主题）  
- [ ] 数据库访问通过 `Database::table()`，不硬编码 `vs_` 前缀  
- [ ] 返回数组结构稳定、文档化，便于主题与 JS 使用  
- [ ] 已更新本文档「文件总览」「能力进度表」  
- [ ] 若有表变更：迁移 SQL + `update.json` 的 `db_changes`  

### 2.8 主题开发者只需记住

1. **读分类** → `FrontendCategory`  
2. **读公开接口** → `FrontendApi`  
3. **读站点名/描述** → `SiteContext`（或模板注入的 `$siteName`）  
4. **当前登录用户** → `FrontendUser::current()`（推荐）或 `UserAuth::user()`  
5. **是否开发者** → `UserRole::currentCanPublishApi()`
5. **未来读文章** → 等 `FrontendArticle` 开发完成后再调用  
6. **永远不要**在主题里写 SQL 或直接调 `*Manager` 做前台展示  

---

## 三、文件总览

| 文件 | 一句话 |
|------|--------|
| `bootstrap.php` | 系统引导，加载全部 core 类 |
| `version.php` | 版本常量 `VS_VERSION` |
| `helpers.php` | 全局辅助函数（转义、页面渲染、前台入口） |
| `InstallChecker.php` | 安装状态检测 |
| `Database.php` | PDO 连接、表名前缀 |
| `DatabaseInstaller.php` | 安装向导执行 `database.sql` |
| `DatabaseMigrator.php` | 版本迁移 SQL（含清理旧系统残留） |
| `Config.php` | 系统配置读写（`vs_config` 表） |
| `SiteContext.php` | 站点名称、描述、Logo 等展示信息 |
| `RegisterPolicy.php` | 注册邮箱后缀策略 |
| `Mailer.php` | SMTP 发信 |
| `Auth.php` | **管理员**登录与会话 |
| `UserAuth.php` | **用户**登录、注册、重置密码 |
| `UserRole.php` | 用户角色常量与权限判断（普通用户/开发者） |
| `FrontendUser.php` | 前台用户资料调度（用户名、头像、邮箱、角色） |
| `AuthSecurity.php` | CSRF、限流、Session 安全、邮件票据 |
| `RateLimitStore.php` | 限流计数存储（MySQL） |
| `AjaxResponse.php` | 后台 AJAX 统一 JSON 响应 |
| `AdminUserBinding.php` | 管理员绑定用户身份（发布内容用） |
| `UserManager.php` | 后台用户列表/封禁/删除/身份转换 |
| `UserAvatar.php` | 用户头像 URL 解析 |
| `ApiManager.php` | API 接口数据与审核状态（后台 / 用户投稿） |
| `ApiNotify.php` | 接口投稿与审核结果的邮件通知（受 mail_notify_* 开关控制） |
| `ApiProxy.php` | 外链网关：出站 `/apis/{短码}`；入站优先 `_vs_slug`（伪静态）/ PATH_INFO；跳转前 `ApiStats::hitProxy` |
| `ApiStats.php` | 本地/代理调用统计：`api.calls++` + 写 `apilog`；本地注入 ≤3 行向上查找或 `api/hit.php` |
| `ApiKeyManager.php` | 用户 API 调用密钥 CRUD（表 `apikey`；每用户最多 3 条；格式 `sk-`+32；含调用次数） |
| `ApiCategoryManager.php` | API 分类 CRUD（**后台向**） |
| `FrontendCategory.php` | 前台分类标签（**主题向**） |
| `FrontendApi.php` | 前台公开接口列表与详情（**主题向**） |
| `FrontendStats.php` | 前台统计：注册用户数、今日调用次数（**主题向**） |
| `RedisCache.php` | 业务数据缓存（公开接口、前台展示格式化列表、分类、日志分页）；键空间自动维护 |
| `ApiLogManager.php` | API 调用日志分页查询、搜索、详情格式化（列表走短 TTL Redis） |
| `RedisService.php` | Redis 连接、监控快照、运行时长格式化（天/时/分/秒）与限流键清理（**后台向**） |
| `ThemeManager.php` | 主题发现、切换、模板渲染 |
| `SystemInfo.php` | 关于页环境信息 |
| `Updater.php` | 在线更新检测与安装；覆盖后按 `install/obsolete-files.json` 清理废弃文件 |
| `UpdateLog.php` | 版本更新记录读取 |
| `oauth/*` | QQ / Gitee 第三方登录 |

---

## 四、各文件详细说明

### 4.1 bootstrap.php

**作用：** 定义 `VS_ROOT`（若未定义），依次 `require_once` 全部核心类，配置 Session Cookie 并 `session_start()`，初始化 CSRF Token。

**何时使用：** 每个 Web 入口文件第一行之后立即引入。

**注意：** 新增 core 类时，须在此文件中追加 `require_once`，否则其他代码无法使用。

---

### 4.2 version.php

**作用：** 定义常量 `VS_VERSION`（如 `2.17.1`）。在线更新、关于页、`update.json` 均以此为准。

**用法：**

```php
echo VS_VERSION;           // 2.17.1
echo 'v' . VS_VERSION;     // v2.17.1
```

**发版时：** 须同步修改 `update.json`、`update-log.json`、`README.md` 徽章。

---

### 4.3 helpers.php

**作用：** 全局函数库，不封装为类。

| 函数 | 作用 |
|------|------|
| `vs_e($value)` | HTML 转义，模板输出必用 |
| `vs_base_url()` | 站点根 URL（含协议域名） |
| `vs_redirect($url)` | HTTP 重定向 |
| `vs_render_head()` / `vs_render_foot()` | 输出 HTML 头尾 |
| `vs_frontend_page($pageKey, $title)` | **前台页面统一入口**（自动选主题、加载 CSS/JS） |
| `vs_render_notice()` | 后台提示块 |
| `vs_render_site_logo()` | 站点 Logo |
| `vs_require_secure_post()` | 校验 POST + CSRF |
| `vs_password_hash()` | 密码哈希 |

**主题开发常用：**

```php
// 前台页面 index.php
vs_frontend_page('home', '首页');

// 模板内输出
echo vs_e($siteName);
```

---

### 4.4 InstallChecker.php

**作用：** 判断系统是否已安装（`config/install.lock` + `config/database.php` 均存在）。

| 方法 | 说明 |
|------|------|
| `isInstalled()` | 是否已安装 |
| `requireInstalled()` | 未安装则跳转 `/install/` |
| `requireNotInstalled()` | 已安装则禁止进入安装向导 |
| `lockFile()` / `configFile()` | 路径常量 |

---

### 4.5 Database.php

**作用：** PDO 单例连接；表名统一加前缀 `vs_`（常量 `TABLE_PREFIX`）。

| 方法 | 说明 |
|------|------|
| `connect()` | 获取 PDO 实例 |
| `table('user')` | 返回 `vs_user` |
| `connectWithConfig($config)` | 安装阶段临时连接 |
| `loadConfig()` | 读取 `config/database.php` |

**规范：** 业务类通过 `Database::table('xxx')` 拼表名，**主题和页面不要直接 new PDO**。

---

### 4.6 DatabaseInstaller.php

**作用：** 安装向导调用，读取 `install/database.sql` 建表。

| 方法 | 说明 |
|------|------|
| `install($pdo, $prefix, $dbname)` | 执行建表 |
| `sqlFile()` | SQL 文件路径 |

---

### 4.7 DatabaseMigrator.php

**作用：** 在线更新或后台触发的**增量数据库迁移**（`install/migrations/*.sql`）。

| 方法 | 说明 |
|------|------|
| `runPending()` | 执行全部待执行迁移 |
| `hasPendingMigrations()` | 是否有未执行迁移 |
| `getPendingFiles()` | 待执行文件列表 |

**发版含表结构变更时：** 新增 `install/migrations/x.y.z.sql`，并在 `update.json` 标记 `db_changes: true`。

---

### 4.8 Config.php

**作用：** 读写 `vs_config` 键值对（站点名、SMTP、主题 ID 等），带内存缓存。

| 方法 | 说明 |
|------|------|
| `get($key, $default)` | 读取配置 |
| `set($key, $value)` | 写入并更新缓存 |
| `all()` | 全部配置 |
| `isMailEnabled()` | SMTP 是否已配置 |

**示例：**

```php
$themeId = Config::get('frontend_theme', 'default');
Config::set('site_name', '我的 API 站');
```

---

### 4.9 SiteContext.php

> **说明：** 旧版多域名类 `Domain.php` 已于 v1.2.0 移除；站点信息一律由本类从单站 `config` 读取。结构更新时 `DatabaseMigrator::purgeLegacyArtifacts()` 会清理残留的 `domain` 表与 `bound_domains` 等配置键。


**作用：** 前台展示用的站点信息（名称、描述、关键词、Logo、备案号、运行时间、页脚扩展等），从 Config 读取并缓存。

| 方法 | 说明 |
|------|------|
| `siteName()` | 站点名称 |
| `siteDescription()` | 站点描述 |
| `siteKeywords()` | SEO 关键词 |
| `siteLogo()` | Logo 路径 |
| `siteRuntimeStart()` | 网站运行起点时间 |
| `footerHtmlLeft/Center/Right()` | 自定义底栏三栏 HTML |
| `footerQr1*` / `footerQr2*` | 页脚二维码启用、名称、图片地址 |
| `currentHost()` | 当前访问 Host |

**主题模板变量：** `ThemeManager::renderBody()` 会向模板注入 `$siteName`、`$siteDesc` 等；页脚扩展用 `vs_render_footer_custom_bar()` / `vs_render_footer_qrs()`。

---

### 4.11 RegisterPolicy.php

**作用：** 用户注册策略，主要是**允许的邮箱后缀白名单**。

| 方法 | 说明 |
|------|------|
| `getPolicy()` | 读取策略 |
| `saveEmailSuffixes($suffixes)` | 保存后缀列表 |
| `isEmailAllowed($email)` | 邮箱是否允许注册 |

---

### 4.12 Mailer.php

**作用：** 通过 SMTP 发送邮件（注册验证码、找回密码等）。

| 方法 | 说明 |
|------|------|
| `send($to, $subject, $body)` | 发送邮件，未配置 SMTP 时抛异常 |

**前置条件：** 后台已配置 SMTP（`Config::isMailEnabled()` 为 true）。

---

### 4.13 Auth.php（管理员认证）

**作用：** **后台管理员**登录、登出、会话、资料修改。

| 方法 | 说明 |
|------|------|
| `login($account, $password)` | 登录，成功返回 true |
| `logout()` | 登出 |
| `check()` | 是否已登录 |
| `requireLogin()` | 未登录跳转后台登录页 |
| `user()` | 当前管理员信息数组 |
| `id()` | 管理员 ID |

**后台页面开头：**

```php
Auth::requireLogin();
$admin = Auth::user();
```

---

### 4.14 UserAuth.php（用户认证）

**作用：** **前台用户中心**登录、注册、找回密码、资料修改。

| 方法 | 说明 |
|------|------|
| `login($account, $password)` | 登录 |
| `register($username, $email, $password)` | 注册 |
| `check()` / `requireLogin()` | 会话检测 |
| `user()` / `id()` | 当前用户 |
| `findByEmail($email)` | 按邮箱查用户 |
| `resetPasswordById($userId, $newPassword)` | 重置密码 |

---

### 4.15 AuthSecurity.php（安全）

**作用：** CSRF、同源校验、登录/发信/OAuth 限流、邮件一次性票据、安全响应头。

| 方法 | 说明 |
|------|------|
| `csrfToken()` | 获取 CSRF Token |
| `validateCsrf($token)` | 校验 CSRF |
| `requireAuthPost()` | POST 必须带合法 CSRF |
| `checkLoginAllowed($username)` | 登录是否被限流 |
| `recordLoginFailure($username)` | 记录登录失败 |
| `checkMailCodeAllowed($email)` | 发验证码是否允许 |
| `issueMailTicket()` / `validateAndConsumeMailTicket()` | 邮件验证码票据 |

**表单/AJAX 示例：**

```php
AuthSecurity::requireAuthPost();
// 或 JSON 接口中：
if (!AuthSecurity::validateCsrf($_POST['csrf_token'] ?? '')) { ... }
```

---

### 4.16 RateLimitStore.php

**作用：** 限流数据的底层存储（按 bucket + 时间窗口计数），供 `AuthSecurity` 调用。

| 方法 | 说明 |
|------|------|
| `allow($bucket, $windowSeconds, $maxAttempts)` | 是否允许并可选记录 |
| `countHits($bucket, $windowSeconds)` | 窗口内次数 |

---

### 4.17 AjaxResponse.php

**作用：** 后台 AJAX 统一 JSON 格式。

| 方法 | 返回格式 |
|------|----------|
| `success($msg, $extra)` | `{ code: 1, msg: "...", ... }` |
| `error($msg)` | `{ code: 0, msg: "..." }` |
| `json($data, $httpCode)` | 自定义 JSON |

**约定：** 后台 JS 判断 `code === 1` 为成功。

---

### 4.18 AdminUserBinding.php

**作用：** 管理员账号与前台用户账号绑定，用于后台以某用户身份发布内容。

| 方法 | 说明 |
|------|------|
| `getBoundUser($adminId)` | 获取绑定的用户 |
| `bind($adminId, $account)` | 绑定 |
| `unbind($adminId)` | 解绑 |
| `publishUserId($adminId)` | 发布时使用的 user_id |

---

### 4.19 UserManager.php

**作用：** 后台**用户管理**（列表、封禁、删除）。

| 方法 | 说明 |
|------|------|
| `all()` | 全部用户 |
| `findById($userId)` | 按 ID 查找 |
| `setStatus($userId, $status)` | 封禁/解封 |
| `delete($userId)` | 删除用户 |

---

### 4.20 UserAvatar.php

**作用：** 解析用户头像 URL（QQ 邮箱自动匹配 QQ 头像 → 自定义链接 → 本地随机图）。

| 方法 | 说明 |
|------|------|
| `resolve($user)` | 传入含 `id`、`email`、`avatar_url` 的用户数组，返回头像 URL |

---

### 4.21 ApiManager.php（后台 / 用户投稿 · 接口）

**作用：** API 接口表的读写、运营状态与审核（后台「接口列表 / 接口审核」、用户中心「API 管理」）。**前台主题请优先用 `FrontendApi`**。

**接口状态 `status`（数字）：** `0` 正常 / `1` 禁用 / `2` 维护  
**审核 `audit`（数字）：** `0` 待审核 / `1` 通过 / `2` 不通过（管理员发布默认通过；用户投稿为待审核）  
**拒绝原因 `rejectreason`：** 不通过时可填，邮件与用户 API 管理页可见  
**密钥 `needkey`（数字）：** `0` 不需要 / `1` 必须 / `2` 可选  

| 方法 | 说明 |
|------|------|
| `listPublic()` | 前台可见：审核通过且非禁用（含维护中） |
| `listAll` / `listByAudit` / `listByUser` / `listFiltered` | 列表筛选（支持 userid） |
| `create` / `update` / `delete` / `setStatus` / `setAuditStatus` | 写操作（`setAuditStatus` 可带拒绝原因） |
| `formatRow` | 格式化（含 `rejectreason` / `audit_class`） |
| `normalizeRequireKey` / `requireKeyLabel` 等 | 数字归一与中文标签 |
| `apiTypeBadge` / `requireKeyBadge` | 列表短标签：代理/本地；KEY可选/必填 |
| `countPendingReview()` | 待审核投稿数（侧边栏红点） |

### 4.21.1 ApiNotify.php（邮件通知）

**作用：** 投稿待审通知管理员；审核结果通知投稿用户。依赖 `Mailer` 与系统 SMTP；发信失败不阻断审核主流程。

| 方法 | 说明 |
|------|------|
| `notifyAdminsPending($api)` | 通知全部启用中的管理员邮箱 |
| `notifyUserAuditResult($api, $audit, $reason)` | 通知投稿用户通过/不通过 |

---

### 4.22 ApiCategoryManager.php（后台 · 分类）

**作用：** 接口分类的**后台 CRUD**（名称、图标、描述、排序、启禁）。  
**前台主题请用 `FrontendCategory`，不要直接调本类渲染标签。**

| 方法 | 说明 |
|------|------|
| `listAll()` | 全部分类（含 api_count） |
| `listEnabled()` | 已启用分类（按 sort_order） |
| `findById($id)` / `findByName($name)` | 查找 |
| `create()` / `update()` / `delete()` | CRUD |
| `setStatus($id, $status)` | 启用/禁用 |
| `defaultIconPaths()` / `defaultIcons()` / `resolveIconUrl()` | 图标库自动扫描（`assets/img/category-icons/*.svg`） |
| `formatRow($row)` | 格式化单行 |

---

### 4.23 FrontendCategory.php（前台 · 分类）★ 主题开发重点

**作用：** 为**所有前台主题**提供统一的分类数据，内部读库，主题**无需知道表名和字段**。

**统一约定：**

- 「全部」键：`FrontendCategory::ALL_ID` → `'all'`
- 各分类键：数据库 **id** 的字符串（如 `'3'`）
- 已启用分类**始终返回**，与下属接口数量无关
- 默认可见 15 个，超出由主题 UI 做「更多」展开

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `listTags()` | `[['id'=>'3','name'=>'生活服务'], ...]` | 渲染标签循环 |
| `nameMap()` | `['all'=>'全部', '3'=>'生活服务']` | 供 JS `categoryNames` |
| `tagVisibleLimit()` | `15` | 默认可见数量 |
| `countEnabled()` | `int` | 分类个数 |
| `resolveIdByName($name)` | 分类 id 或 `''` | 接口行 category 名称 → id |

**主题页面示例：**

```php
<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>

<div class="my-cats">
    <button type="button" data-cat="<?php echo vs_e(FrontendCategory::ALL_ID); ?>">
        <?php echo vs_e(FrontendCategory::ALL_NAME); ?>
    </button>
    <?php foreach (FrontendCategory::listTags() as $tag): ?>
        <button type="button" data-cat="<?php echo vs_e($tag['id']); ?>">
            <?php echo vs_e($tag['name']); ?>
        </button>
    <?php endforeach; ?>
</div>
```

**JS 筛选：** 点击「全部」传 `all`；点击某分类传对应 `id` 字符串；接口数据的 `category` 字段与 `FrontendApi` 一致。

---

### 4.24 FrontendApi.php（前台 · 公开接口）★ 主题开发重点

**作用：** 输出已通过审核的公开接口，字段已标准化，分类 id 与 `FrontendCategory` 对齐。

| 方法 | 说明 |
|------|------|
| `listForTheme()` | 接口数组，供模板或 `json_encode` 给 JS |
| `countForTheme()` | 公开接口数量 |

**返回字段（每条）：**

| 字段 | 说明 |
|------|------|
| `id` | 接口 ID |
| `name` | 名称 |
| `desc` | 描述 |
| `category` | 分类 id 字符串（空表示未匹配到库内分类） |
| `method` / `methods` | 请求方式 |
| `endpoint` / `full_url` | 地址 |
| `maintenance` / `require_api_key` / `points_cost` | 扩展字段（默认 0） |

**主题首页示例：**

```php
$apiData = FrontendApi::listForTheme();
$categoryNames = FrontendCategory::nameMap();
?>
<script>
var apiData = <?php echo json_encode($apiData, JSON_UNESCAPED_UNICODE); ?>;
var categoryNames = <?php echo json_encode($categoryNames, JSON_UNESCAPED_UNICODE); ?>;
</script>
```

**说明：** 用户侧「提交接口」等功能未上线时，`listForTheme()` 可能返回空数组，**分类标签仍应正常显示**。

---

### 4.25 ThemeManager.php（主题引擎）

**作用：** 主题发现、切换、模板渲染、资源 URL。

**主题目录：** `core/theme/{themeId}/`（须含 `theme.json`）

| 方法 | 说明 |
|------|------|
| `activeId()` | 当前主题 ID（读 Config `frontend_theme`） |
| `listThemes()` | 已安装主题列表 |
| `setActive($themeId)` | 切换主题 |
| `renderBody($pageKey, $title, $data)` | 渲染 layout + pages |
| `themeSetting($key, $default)` | 读主题 settings |
| `assetUrl($themeId, $relative)` | 主题静态资源 URL |
| `navItems()` | 前台导航项 |
| `defaultFrontendAssets($pageKey)` | 默认主题专用 CSS/JS  bundle |

**新建主题三步骤：**

1. 复制 `core/theme/default/` 或 `slate/` 为 `core/theme/mytheme/`
2. 编写 `theme.json`（id、name、settings 等）
3. 在 `pages/` 下写 PHP，**分类与接口只调 `FrontendCategory` / `FrontendApi`**
4. 后台「系统设置 → 前台主题」切换，或 `Config::set('frontend_theme', 'mytheme')`

**主题隔离：** 各主题 CSS/JS **完全独立**，无跨主题文件回退。默认主题认证页使用本包 `auth.css` / `auth.js`，角色交互由全局 `assets/js/auth-characters.js` 提供（v3.5.1 起不再依赖 anime.js）。  
**用户中心壳层：** 公共样式用 `/assets/css/admin.css`；各主题 `assets/user.css` **只写增量覆盖**，禁止整份复制 `admin.css`（见 E25、《主题资源隔离规范》）。

---

### 4.26 RedisService.php（后台 · Redis 监控）

**作用：** 连接 Redis 并采集 INFO / 业务缓存快照，供 `admin/system/redis.php` 与关于页「Redis 版本」使用。前端 `assets/js/redis.js` 渲染交互式 SVG 环形图（悬停/点击扇区高亮并在图内提示区展示明细；避免引出线溢出），并对运行时长与剩余 TTL 做每秒本地计时；「刷新周期」文案不参与滚动。缓存项状态图中心默认展示业务缓存占用。

| 方法 | 说明 |
|------|------|
| `extensionLoaded()` | PHP redis 扩展是否可用 |
| `connectionConfig()` | 读取 host/port/db/prefix（不含密码明文） |
| `collectMonitorSnapshot()` | 完整监控快照（含 `uptime_seconds` / `uptime_human`、业务缓存项 TTL） |
| `formatUptime($seconds)` | 格式化为「N 天 N 小时 N 分 N 秒」 |
| `versionLabel()` | 关于页一行摘要 |

**配置键（`vs_config`，可选）：** `redis_host`、`redis_port`、`redis_password`、`redis_database`、`redis_prefix`（默认 `127.0.0.1:6379`、db0、`apinexus:`）。

**业务缓存项（`RedisCache`）：**

| 逻辑键 | TTL | 写入入口 | 说明 |
|--------|-----|----------|------|
| `cache:api:public_list` | 120s | `ApiManager::listPublic` | MySQL 公开接口原始行 |
| `cache:frontend:api_list` | 120s | `FrontendApi::listForTheme` | 主题用格式化列表（**须单独 remember，勿只依赖 public_list**） |
| `cache:frontend:category_tags` | 300s | `FrontendCategory::listTags` | 前台分类标签 |
| `cache:apilog:query:{md5}` | 45s | `ApiLogManager::listPaged` | 日志查询结果（后台列表 / 后续图表等凡读均可复用） |
| `cache:apilog:today_count` | 30s | `ApiLogManager::countToday` | 今日调用次数（首页统计等） |

接口/分类变更时调用 `RedisCache::invalidateFrontend()`。日志相关调用 `RedisCache::invalidateApiLog()`；高并发写入时亦可依赖短 TTL，勿每次 INSERT 全量 SCAN。

监控页环形图「缓存了什么」按业务项分扇区；列表展示中文名称与用途说明，**禁止**对外展示「轻量」等实现向形容词。

---

### 4.27 SystemInfo.php

**作用：** 收集 PHP、MySQL、操作系统等环境信息，供关于页展示。

```php
$rows = SystemInfo::collect(); // [['label'=>'PHP 版本','value'=>'8.2'], ...]
```

---

### 4.28 Updater.php

**作用：** 从 Gitee 检测新版本、下载 `apinexus{版本}.zip`、解压覆盖（保护 `config/`、`data/`），并按清单清理废弃文件。

| 方法 | 说明 |
|------|------|
| `localVersion()` | 本地版本 |
| `checkForUpdate()` | 检测是否有新版本 |
| `downloadAndApply($version)` | 下载并应用更新包 |
| `removeObsoleteFiles()` | 覆盖后删除 `install/obsolete-files.json` 声明的残留文件 |
| `protectedRelativePaths()` | 更新时绝不可覆盖的路径 |

**废弃文件：** 发行包内维护 `install/obsolete-files.json`（`files` 数组为相对项目根的路径）。部署步骤在 `copyTree` 之后执行删除；不会触及受保护路径。

---

### 4.28 UpdateLog.php

**作用：** 读取版本历史（优先云端 `update-log.json`，失败读本地）。

| 方法 | 说明 |
|------|------|
| `getVersions()` | 版本列表 |
| `getVersion($ver)` | 单个版本详情 |

---

## 五、oauth 子目录（第三方登录）

| 文件 | 作用 |
|------|------|
| `oauth/HttpClient.php` | OAuth HTTP 请求封装 |
| `oauth/OAuthConfig.php` | QQ/Gitee AppId、Secret、开关 |
| `oauth/OAuthState.php` | state 参数防 CSRF |
| `oauth/OAuthService.php` | **统一入口**：授权 URL、回调处理、绑定 |
| `oauth/qq/QQOAuth.php` | QQ 互联实现 |
| `oauth/gitee/GiteeOAuth.php` | Gitee OAuth 实现 |

**用法：**

```php
$url = OAuthService::authorizeUrl('gitee');
$providers = OAuthService::enabledProviders(); // ['qq'=>bool,'gitee'=>bool]
// 回调页：
$result = OAuthService::handleCallback($provider, $code, $state);
```

**规则：** 仅**已注册用户**可绑定；首次 OAuth 需走绑定页 `user/oauth/bind.php`。

---

## 六、主题开发对接指南

### 6.1 推荐分层

> 完整开发顺序见 **第二章「core 开发规范与后续流程」**。主题处于最上层，只消费 core 已提供的 `Frontend*` 类。

```
主题 pages/*.php          ← 只做展示，不写 SQL
    ↓ 只调用
FrontendCategory / FrontendApi / Frontend*（未来）/ SiteContext / UserAuth
    ↓ 内部调用
XxxManager / Config / Database
    ↓
MySQL
```

### 6.2 分类标签标准写法（两主题已统一）

1. 循环 `FrontendCategory::listTags()` 输出按钮/链接  
2. 「全部」使用 `FrontendCategory::ALL_ID`（`'all'`）  
3. 超过 `FrontendCategory::tagVisibleLimit()`（15）个时，主题 CSS/JS 做「更多」展开  
4. **不要**在主题里写 `ApiCategoryManager::listEnabled()` 或直接 SQL  

### 6.3 接口列表标准写法

1. PHP：`$apiData = FrontendApi::listForTheme();`  
2. 模板循环输出卡片，或 `json_encode` 交给主题 JS  
3. 卡片上 `data-category="<?php echo vs_e($api['category']); ?>"` 用于筛选  
4. 无公开接口时显示空状态，**分类栏仍保留**  

### 6.4 新建主题 checklist

- [ ] `core/theme/{id}/theme.json`  
- [ ] `pages/home.php`、`pages/apis.php` 等  
- [ ] `layout/header.php`、`layout/footer.php`  
- [ ] `assets/theme.css`、`assets/theme.js`  
- [ ] 分类：`FrontendCategory::listTags()`  
- [ ] 接口：`FrontendApi::listForTheme()`  
- [ ] 不引用其他主题的 CSS/JS  
- [ ] 输出用户内容处使用 `vs_e()`  

### 6.5 与后台的关系

| 能力 | 后台管理类 | 前台主题类 | 状态 |
|------|------------|------------|------|
| 接口分类 | `ApiCategoryManager` | `FrontendCategory` | ✅ 可调用 |
| 接口审核/上下线 | `ApiManager` | `FrontendApi` | ✅ 可调用 |
| 用户管理 | `UserManager` | `UserAuth`（当前用户） | ✅ 可调用 |
| 站点配置 | 后台设置页 → `Config` | `SiteContext` / `ThemeManager::themeSetting()` | ✅ 可调用 |
| 文章 | `ArticleManager`（规划） | `FrontendArticle`（规划） | ⏳ 待 core 开发 |
| 友情链接 | `LinkManager`（规划） | `FrontendLink`（规划） | ⏳ 待 core 开发 |
| 公告 | `AnnouncementManager`（规划） | `FrontendAnnouncement`（规划） | ⏳ 待 core 开发 |

---

## 七、常见问题

**Q：默认主题用户中心 UI 突然全乱了？**  
A：多半是主题包里错误地整份复制了过期的 `admin.css`（E25）。用户中心须加载 `/assets/css/admin.css`，主题 `user.css` 只写增量。见《主题资源隔离规范》。

**Q：主题里可以直接 `Database::connect()` 吗？**  
A：不推荐。请使用 `FrontendCategory`、`FrontendApi` 等已封装类；新能力应在 core 新增类后在 bootstrap 注册。

**Q：为什么分 `ApiCategoryManager` 和 `FrontendCategory`？**  
A：前者负责后台 CRUD 与图标；后者负责前台展示规则（all/id 键、可见数量、无接口仍显示）。职责分离，主题不依赖后台实现细节。

**Q：分类下没有接口，标签会消失吗？**  
A：不会。`FrontendCategory::listTags()` 返回全部**已启用**分类。

**Q：新增 core 类后主题用不了？**  
A：检查是否已在 `bootstrap.php` 中 `require_once`。

**Q：为什么文章/友链主题页还是占位？**  
A：这些模块的 `Frontend*` 类尚未在 core 开发完成。须先按 **§2.3 标准开发流程** 完成 `XxxManager` + `FrontendXxx`，主题才能读取真实数据。

**Q：我可以先在主题里写 SQL 赶进度吗？**  
A：不可以。临时 SQL 会导致多主题不一致、后续难以维护；必须先补 core 类再改主题。

**Q：`*Manager` 和 `Frontend*` 必须成对出现吗？**  
A：凡涉及数据库、且前台需要展示的业务，**强烈建议成对**。纯后台能力（如 `Updater`）可只有 Manager/Service 类，无需 Frontend 类。

---

## 八、相关文档

| 文档 | 位置 |
|------|------|
| 项目说明 | `README.md` |
| **主题开发（数据来源）** | `开发规范/主题规范.md` §十（本地维护） |
| 数据库开发 | `开发规范/数据库开发规范.md`（禁止字段下划线；中文 COMMENT；数字状态） |
| 升级策略 | `开发规范/版本升级不兼容旧版.md`（新版不长期兼容旧字段/旧代码） |
| 请求与表单 | `开发规范/请求与表单规范.md`（本地维护） |
| 发版流程 | `开发规范/Gitee推送与发行流程.md`（本地维护） |
| 弹窗规范 | `开发规范/弹窗开发规范.md`（本地维护） |
| 按钮样式 | `开发规范/按钮样式规范.md`（本地维护） |
| 界面提示 / Toast | `开发规范/界面提示规范.md` §8（本地维护） |
| 界面勿泄露实现细节 | `开发规范/界面勿泄露实现细节.md`（禁止把库枚举写到页面） |
| 查询串转路径样式 | `开发规范/查询串转路径样式规范.md`（**主体：PATH_INFO `/脚本.php/标识`，无需伪静态**；代理去 .php 见 `nginx伪静态配置.md`） |
| 本地/代理调用统计 | `开发规范/本地与代理接口统计机制.md`（`ApiStats` + `apilog`） |
| 主题资源隔离 | `开发规范/主题资源隔离规范.md` |
| 开发易错点 | `开发规范/开发易错点备忘.md` |
| 版本记录 | `update-log.json`、`发行说明/` |

---

**文档维护：** 新增或重构 core 类时，须同步更新：

1. 本文档 **§三 文件总览**、对应 **§四 详细说明**  
2. **§2.4 当前能力与进度** 表  
3. **§6.5 与后台的关系** 表  
4. `README.md` 目录结构（如有新 core 文件）  
5. `开发规范/主题规范.md` §10.3（若新增或变更 `Frontend*` 对外 API）
