# ApiNexus · core 核心模块说明

> **文档位置：** 项目根目录 `CORE模块说明.md`  
> **适用读者：** 主题开发者、二次开发者、维护者  
> **当前版本：** 以 `core/version.php` 中 `VS_VERSION` 为准（本文档同步至 **13.26.17**）  
>  
> **主题开发请先读：** [**§六、主题开发对接指南（完整 API）**](#六主题开发对接指南完整-api) — 入口管道、目录结构、全部 `Frontend*` 方法与返回字段、禁止事项与 Checklist。主题 **禁止直连数据库**，只对接 core。

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
| 读写的数据库逻辑、业务规则 | HTML 结构、**本主题** CSS/JS/shell、页面布局 |
| 后台管理类（`*Manager`） | 主题配置项（`theme.json` settings） |
| 前台调度类（`Frontend*`） | 调用 core 类展示数据 |
| `SiteMedia` 等资源出站 | **禁止**手写拼 `/assets/img/…`；**禁止**引用其它主题或根目录前台 CSS/JS |
| 在 `bootstrap.php` 注册 | **禁止**直接 `Database::connect()` / 写表名 |
| 全主题共用的数据格式约定 | 各主题独立的视觉与交互 |

**主题资源隔离（13.22.3；加载方式 13.22.6；字体/工具类 13.26.5～13.26.6）：** 前台 / 用户中心只加载**当前主题包**内 `assets/shell`、`assets/css`、`assets/js`；根目录 `assets/css|js` **仅**管理员后台与安装等系统页。内置图标物理文件仍在 `assets/img/`，出站须经 `SiteMedia`（或 `UserAvatar` / 分类图标等核心类）。**浏览器按文件逐个请求**（`ThemeManager::frontendShell*Hrefs` / `defaultFrontendAssets` 等清单），**不再**使用 HTTP 打包入口；磁盘源文件保持分立，禁止为维护方便合并成单个大 CSS。默认主题由 `ThemeManager::defaultFrontendAssets` 加载主题包内 **`assets/css/feer-compat.css`**（静态工具类，**替代**运行时 Tailwind，禁止再挂 CDN/运行时 Tailwind）；等宽字体用本地 `fonts-local.css`（`assets/vendor/fonts-local.css`）+ JetBrains Mono woff2，中文走系统字体栈；**禁止**再链境外 Google Fonts。

**默认主题 UI 改动边界：** 详情免责声明开关、快速上手鉴权 Tab、Hero 文案等**仅改** `core/theme/default/`；其它主题须自行对齐 `theme.json` settings，core 不提供跨主题样式回退。

**一句话：** core 负责「数据从哪来、规则是什么」；主题负责「数据怎么展示」。

---

## 1.1 bootstrap 加载顺序（与 `core/bootstrap.php` 一致）

```
version.php
→ helpers.php
→ date_default_timezone_set('Asia/Shanghai')   ← 业务时间统一东八区（与 MySQL session time_zone 对齐）
→ InstallChecker → Database → DatabaseInstaller → DatabaseMigrator
→ SiteContext → RegisterPolicy → Config
→ Mailer → RedisService → RedisCache
→ Auth → UserRole → UserAuth → FrontendUser
→ UserDashHello → SiteMedia
→ RateLimitStore → AuthSecurity → Captcha → AjaxResponse
→ SystemInfo → AboutCatalog → Updater → UpdateLog
→ UserAvatar → UserManager → AdminUserBinding
→ ApiManager → ApiError → ApiQuickstart
→ AiConfig → AiClient → AiChatSession → AiSse → AiApiDoc
→ ApiNotify → ProxyClientProfile → ProxyJsonRewrite → JsonpGuard → ApiOutboundSanitize → ApiProxy → ApiStats → IpLocator
→ StatDayManager → UserStat7Manager → UserCallStats → ApiLogManager → ApiLogArchive → ApiKeyManager
→ ApiFeedbackManager → FrontendFeedback → FeedbackNotify
→ ApiCategoryManager
→ PayConfig → OrderManager → PointsManager
→ CodePayClient（core/play/codeplay/）
→ FrontendCategory → FrontendApi → FrontendStats → GeoCityCoords → DashboardStats → PanelMonitor
→ LinkManager → LinkSiteMeta → LinkNotify
→ FrontendLink → FrontendPartner → FrontendSponsor → FrontendContributor
→ ContentManager → CommentManager → CommentNotify → FrontendComment
→ CheckinManager
→ Markdown（core/markdown/）
→ FrontendAnnouncement → FrontendArticle → FrontendAbout
→ PlaygroundRelay → ThemeManager → Sitemap
→ oauth/*（HttpClient → OAuthConfig → OAuthState → OAuthService → QQ/Gitee）
→ Session 启动 + CSRF
→（已安装时）DatabaseMigrator::pruneAppliedAboveCodeVersion
```

### core 子目录一览（含 HTTP 入口）

下列子目录**多数不在**上方 bootstrap `require` 列表中逐文件展开；类文件由门面 / 父类 `require_once`，或由独立 HTTP 脚本自行引入。

```
core/
├── captcha/          验证码实现与 HTTP
│   ├── local.php / gt3/* / gt4/LoginController.php / helper.php
│   ├── image.php     ← HTTP：本地图形出图
│   └── register.php  ← HTTP：极验 register / 校验中转
├── oauth/            第三方登录（由 bootstrap 加载类；入口在 user/oauth/）
├── markdown/         Markdown.php + Parsedown（bootstrap 加载）
├── play/codeplay/    CodePayClient.php + notify.php / return.php（支付回调 HTTP）
├── playground/       relay.php（在线测试同源中继 HTTP）、media.php
├── front/            catalog.php（公开目录）；playground-key.php（登录按需取 KEY，禁 SSR；v13.26.16）
├── cron/             apilogarchive.php 等计划任务 HTTP（密钥校验）
├── theme/{id}/       主题包（pages / layout / assets；非 bootstrap 类）
├── ping.php          ← HTTP：贡献者延迟检测等（含 IP 频控）
└── vx/               隐蔽数据（如 AboutCatalog 本地 JSON），勿当公开入口
```

**说明：** `image.php` / `register.php`、`playground/relay.php`、`front/catalog.php`、`front/playground-key.php`、`cron/*`、`ping.php`、码支付 `notify.php`/`return.php` 等是**独立 HTTP 入口**，各自 `require bootstrap` 或按需加载，**不会**出现在 bootstrap 类清单里。主题包 `theme/{id}/` 只被 `ThemeManager` / `vs_frontend_page` 调度，不在 bootstrap 逐文件 require。

**`core/front/catalog.php`（v13.26.16）：** 首页 / apis **禁止**首屏 `json_encode` 全量 `apiData`；须 `POST` 本端点（`vs_require_secure_post` + `front_catalog_ip` 60/60s）一次拉取后 JS 本地筛选。数据经 `FrontendApi::listForCatalog()`（`listForTheme` + `slimForCatalog`，去掉 `doc`/`aidoc`/`response`）、`FrontendCategory::nameMap()`；可选 `partners=1` → `FrontendPartner::listForTheme()`。页脚 / `vs_render_foot` 注入 `VS_FRONT_CATALOG`；主题壳提供 `VS.fetchFrontCatalog`。详见《前端页面渲染与源码规范》。

**`core/front/playground-key.php`（v13.26.16 / E253）：** 在线测试**禁止**把 API KEY 明文 SSR 进首页/详情 HTML。SSR 仅 `playgroundKeyContext`（loggedIn / apiKeyCount / urls）；调试时 `POST` 本端点（登录 + CSRF + UID/IP 频控）按需取钥。前台页 `sendFrontendSecurityHeaders` 固定 `private, no-store` + `Vary: Cookie`，防 CDN 缓存放大。

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
| 公开 API 接口 | `ApiManager` / `ApiNotify` / `ApiProxy` / `PlaygroundRelay` / `ApiStats` | `FrontendApi` / `FrontendStats` | `admin/api/list.php`、`review.php`、`user/api-manage.php`、`apis.php`、`detail.php`、**`core/front/catalog.php`** | ✅ 是 | **已完成**（本地/外链、详情 `/detail/{id}`、多选 method、**keyways**、needkey/qpm/charge、审核三态、统计、在线测试浏览器直连、双端 UI；**v13.26.16** 首页/apis 经 catalog 异步目录，`listForCatalog`/`slimForCatalog`） |
| 用户调用密钥 | `ApiKeyManager` | —（统计内校验） | `user/keys.php`、`admin/api/keys.php` | 用户中心/后台 | **已完成**（表 `apikey`；每账号最多 3 个；`sk-`+32；本地/代理校验与计数；页面勿用 `tokens` 命名） |
| 积分与支付 | `PointsManager` / `PointsNotify` / `OrderManager` / `CheckinManager` / `PayConfig` / `CodePayClient` | `FrontendUser`（余额 / 签到 / 控制台） | `admin/finance/*`、`admin/settings`、`user/recharge`、`user/points`、`user/index`、`core/play/codeplay/notify.php` / `return.php` | 用户中心/后台 | **已完成**（充值扣费；注册赠送 / 每日签到；积分归零/充值成功邮件；表 `orders` + `checkin`） |
| 站点信息 | `Config` / `SiteContext` | `SiteContext` | `admin/settings.php` | ✅ 是 | **已完成** |
| 用户认证 | `UserAuth` / `UserManager` / `Auth` | `UserAuth` + `FrontendUser`；管理员 `Auth::loginById` | `user/`、`admin/login.php`、`admin/users.php` | ✅ 是 | **已完成**（含角色；**13.26.7** 双端邮箱验证码登录） |
| 用户控制台统计 | `UserStat7Manager` / `ApiKeyManager` / `ApiLogManager` | `FrontendUser::dashboardStats` / `myLogsPaged` | `user/index.php`、`user/logs.php`、双主题 dashboard/logs | ✅ 是 | **已完成（13.26.7 数据 / 13.26.8 UI / 13.26.9 实时刷新）**：KPI 7/8；固定 3s live + 同款图标刷新 |
| 注册策略 | `RegisterPolicy` | （入口注入 `$registerOpen` 等） | `admin/settings`、`user/register.php` | 入口/后台 | **已完成**（开放/关停、邮箱验证、后缀白名单 `register_policy`） |
| 验证码 | `Captcha` + `captcha/*` | `vs_captcha_field` / `vs_captcha_js`（`captcha/helper.php`） | 系统设置分端 mode；`captcha/image.php` | 认证页 | **已完成**（local / gt3 / gt4；场景 SCENE_USER_*；v13.26.6 大小写不敏感 + 首次 focus 换图） |
| 站点地图 | `Sitemap` | — | 根 `sitemap.php` → `/sitemap.xml` | SEO | **已完成**（四处伪静态同步；**v13.26.16 起不再提供 robots.txt**） |
| 管理员认证 | `Auth` | — | `admin/` | 后台专用 | **已完成** |
| 第三方登录 | `oauth/*` | `OAuthService` | 系统设置 | ✅ 是 | **已完成**（UI 出站 `/user/oauth/start`；回调仍 `callback.php`） |
| AI 文档 / 快速上手 | `AiApiDoc` / `AiConfig` / `AiClient` / `AiChatSession` / `AiSse` / `ApiQuickstart` | （编辑页 SSE；详情读 aidoc） | 接口编辑（管理端/用户端） | 后台/开发者 | **已完成**（7 章 id 固定；代码最多 27 片；TTL=600；`clearAllForActor`） |
| 面板监控 | `PanelMonitor` / `DashboardStats` | — | `admin/index`、`admin/screen` | 后台专用 | **已完成**（宝塔 / 1Panel 快照；v13.16.0） |
| 文章 | `ContentManager`（kind=1） | `FrontendArticle` / `FrontendAbout` | `admin/content/articles.php`、`articles.php`、`about.php` | ✅ 是 | **已完成**（封面；可绑定关于页；隐藏态） |
| 友情链接 | `LinkManager` / `LinkSiteMeta` / `LinkNotify` | `FrontendLink` | `admin/content/links.php`、`links.php`、`applylink.php`、`core/theme/default/api/sitemeta.php` | ✅ 是 | **已完成**（表 `link`；`kind=0`；审核 + 启禁；一键 TDK；邮件通知） |
| 合作伙伴 | `LinkManager`（共用） | `FrontendPartner` | `admin/content/partners.php`、默认主题首页（**catalog `partners=1`**） | ✅ 是 | **已完成**（表 `link`；`kind=1`；无审核；仅编辑/启禁；**v13.26.16** 首页伙伴随目录接口拉取，禁首屏灌包） |
| 赞助 | `LinkManager`（共用） | `FrontendSponsor` | `admin/finance/sponsor.php`、`sponsor.php`、默认主题赞助页、系统设置收款码 | ✅ 是 | **已完成**（表 `link`；`kind=2`；简介=赞助说明；收款码配置） |
| 公告 | `ContentManager`（kind=0） | `FrontendAnnouncement` | `admin/content/announcements.php`、首页弹窗/跑马灯 | ✅ 是 | **已完成**（置顶/弹窗；Markdown；与文章共用表） |
| Markdown | `Markdown`（`core/markdown/`） | 编辑器 + 渲染 | 公告/文章/API 文档编辑 | ✅ 是 | **已完成**（本地 marked/purify/Parsedown；短码扩展） |
| Redis 缓存 | — | `RedisService` / `RedisCache` / `DashboardStats` / `StatDayManager` | `admin/system/redis.php`、`admin/index.php`、`admin/screen.php` | 后台专用 | **业务缓存已接入**（公开接口 / 前台展示 / 分类 / 日志分页 / 今日调用←statday / 控制台 `cache:dashboard:*` + `statday` 日聚合） |
| 贡献者 | `FrontendContributor` | `FrontendContributor` | `contributors.php`、`profile.php`、`core/ping.php` | ✅ 是 | **已完成**（开发者卡片、公开主页、加入时间、壁纸、延迟检测） |
| 主题资源 / 媒体 | `ThemeManager` / `SiteMedia` | （主题 layout 调用） | 各主题 `assets/shell|js|css`（逐文件 link/script） | ✅ 是 | **已完成**（双主题完全隔离；逐文件加载；图标经 SiteMedia；**v13.26.16** 同站出站改 `vs_site_path`，SEO 仍绝对 https） |
| 用户控制台问候 | `UserDashHello` | — | `user/index`（双主题） | 用户中心 | **已完成**（24×1h 槽 + 打字动效；头像点击抖动） |

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

### 2.6 文章模块（已完成，v5.x / v7.x）

文章/公告/关于页已由 `ContentManager` + `FrontendArticle` / `FrontendAnnouncement` / `FrontendAbout` 落地；新主题直接调用 `Frontend*` 类，勿再按下方「规划」重复造轮子。

| 步骤 | 文件 | 说明 |
|------|------|------|
| 1 | `install/migrations/*.sql` | 内容表（若升级自极旧版） |
| 2 | `core/ContentManager.php` | 后台：发布、下架、分类、CRUD（kind 区分公告/文章） |
| 3 | `admin/content/*.php` | 后台管理界面 |
| 4 | `core/FrontendArticle.php` 等 | 前台：`listForTheme()`、`findById()`、`listPaged()` |
| 5 | `bootstrap.php` | 已注册 |
| 6 | `core/theme/*/pages/articles.php` | 各主题调用 `FrontendArticle::listForTheme()` |

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

### 2.8 主题开发者速记（完整对接见 **§六**）

1. **读分类** → `FrontendCategory`  
2. **读公开接口** → 首页/apis：**禁止**首屏灌全量；`POST core/front/catalog.php` / `VS.fetchFrontCatalog`（服务端 `FrontendApi::listForCatalog`）；详情等单条仍 `FrontendApi::findForThemeById`  
3. **读统计 KPI** → `FrontendStats`（勿再调 `ApiManager` 计数）  
4. **读站点名/描述** → `SiteContext::siteName()`；壳层系统名 → `SiteContext::systemName()`  
5. **当前登录用户** → `FrontendUser::current()`；会话 → `UserAuth::check()`  
6. **是否开发者** → `UserRole::currentCanPublishApi()` 或用户字段 `can_publish_api`  
7. **文章 / 公告 / 关于** → `FrontendArticle` / `FrontendAnnouncement` / `FrontendAbout`  
8. **友链 / 伙伴 / 赞助** → `FrontendLink` / `FrontendPartner` / `FrontendSponsor`  
9. **贡献者** → `FrontendContributor`  
10. **图标** → `SiteMedia::imgUrl()`；**主题配置** → `ThemeManager::themeSetting*`  
11. **永远不要**在主题里写 SQL，或用 `*Manager` 做前台展示取数  

> 详细方法签名、返回字段、入口管道、目录结构、Checklist：**见第六章**。

---

## 三、文件总览

| 文件 | 一句话 |
|------|--------|
| `bootstrap.php` | 系统引导，加载全部 core 类 |
| `version.php` | 版本常量 `VS_VERSION` |
| `helpers.php` | 全局辅助函数（转义、页面渲染、前台入口；**v13.26.16** `vs_site_path` / `vs_site_base_path`；**v13.26.17** `vs_call_url_absolute` / `vs_call_url_host_path`；页脚注入 `VS_FRONT_CATALOG`） |
| `InstallChecker.php` | 安装状态检测 |
| `Database.php` | PDO 连接、表名前缀 |
| `DatabaseInstaller.php` | 安装向导执行 `database.sql` |
| `DatabaseMigrator.php` | 版本迁移 SQL（含清理旧系统残留） |
| `Config.php` | 系统配置读写（`vs_config` 表） |
| `SiteContext.php` | 站点名称（前台）、系统名称（后台壳层）、描述、Logo 等展示信息 |
| `RegisterPolicy.php` | 注册开放/邮箱验证/后缀策略（`CONFIG_KEY=register_policy`） |
| `Mailer.php` | SMTP 发信 |
| `Auth.php` | **管理员**登录与会话 |
| `UserAuth.php` | **用户**登录、注册、重置密码 |
| `UserRole.php` | 用户角色常量与权限判断（普通用户/开发者） |
| `FrontendUser.php` | 前台用户资料调度（用户名、头像、简介、博客、壁纸、角色）；`dashboardStats()` 控制台汇总；`myLogsPaged()` 本人日志 |
| `UserDashHello.php` | 用户控制台按时段问候（24 个 1 小时槽；文案池随机；双主题共用） |
| `SiteMedia.php` | 内置图片出站（`assets/img/` 物理文件；主题禁止手写路径；**v13.26.16** 同站返回根相对路径） |
| `FrontendContributor.php` | 贡献者列表与公开个人主页（接口数 / 调用量 / 加入时间；`bio_custom` 标记是否自填简介；归属含绑定身份下历史 userid=0） |
| `AuthSecurity.php` | CSRF、限流、Session 安全、邮件票据 |
| `Captcha.php` | 行为验证门面：分端 mode（管理员/用户可分别选）；`local` / `gt3` / `gt4`；场景 `SCENE_*`；helper 提供 `vs_captcha_*`（见 `captcha/helper.php`） |
| `captcha/*` | 本地图 `local.php`；极验3 `gt3/`；极验4 `gt4/`；挂载 `helper.php`；HTTP `image.php` / `register.php` |
| `RateLimitStore.php` | 限流计数存储（MySQL） |
| `AjaxResponse.php` | 后台 AJAX 统一 JSON 响应 |
| `AdminUserBinding.php` | 管理员绑定用户身份（发布内容用） |
| `UserManager.php` | 后台用户列表/封禁/删除/身份转换 |
| `UserAvatar.php` | 用户头像 URL 解析 |
| `AboutCatalog.php` | 关于页「开发与维护 / 相关链接 / 技术栈」目录（本地 JSON 优先，缺则云端；三仓链接，无页面 note） |
| `ApiManager.php` | API 接口数据与审核状态（后台 / 用户投稿） |
| `ApiError.php` | 公开 API 业务错误码（11001～11018）；`businessLabelMap` / `aiDetailDocErrcodeClause` 供 AI 详细文档全量写入 |
| `ApiQuickstart.php` | 从 `aidoc` 解析 `:::qs lang=… auth=…` 多语言快速上手（v10.15.0；auth v10.17.0） |
| `AiConfig.php` | 站点 AI 配置（启用/服务商/根地址/密钥/模型/单片超时/代码调度模式与并发） |
| `AiClient.php` | OpenAI 兼容 Chat Completions / Responses；流式 `chatStreamWithConfig`；连通测试须先 `session_write_close`（v13.26.0） |
| `AiChatSession.php` | AI 短时效多轮（Redis **TTL=600**）；接口保存时 `clearAllForActor` 整批清空 |
| `AiSse.php` | SSE 输出（`no-transform` / `Surrogate-Control` / 首包垫片 + 心跳），供文档与代码流式生成 |
| `AiApiDoc.php` | 详细文档按章生成（文首接口名标题、失败自动重试见前端）；代码示例最多 3×9；纯代码出站后服务端包 `:::qs`；按鉴权按钮同行短文案、用户端体验对齐管理端（v13.26.3） |
| `IpLocator.php` | IP 归属地：内置（仅 IPv4）或自定义；`probe()` 支持表单草稿测试；自定义超时加长；IPv6 提醒需自定义接口（v13.26.1） |
| `ApiNotify.php` | 接口投稿与审核结果的邮件通知 |
| `ProxyClientProfile.php` | 出站 UA/Referer 内置预设与解析；代理网关与本地 `ApiStats::outboundHeaders` 共用 |
| `ProxyJsonRewrite.php` | 代理响应 JSON 字段改写（set/del；仅 JSON；**v13.12.0**；**v13.25.0** 禁止 SET 后台路径；**v13.25.2** 业务错误体不改写） |
| `JsonpGuard.php` | JSONP 回调白名单与参数剥离（**v13.25.0**） |
| `ApiOutboundSanitize.php` | 出站 JSON 擦除 `/admin` 等敏感路径（**v13.25.0**）；业务错误体收窄三字段（**v13.25.2**） |
| `ApiProxy.php` | 外链网关：curl 中继上游；按 `upmethod` 选上游 GET/POST；可选 JSON 改写；剥离 JSONP 参数；出站消毒；3xx Location 透传；上游 TLS 不校验证书 |
| `PlaygroundRelay.php` | 在线测试同源中继；上游方法/TLS/JSONP 剥离/出站消毒与 ApiProxy 一致 |
| `ApiStats.php` | 本地/代理调用统计与守卫；本地须 `hit(接口ID)`；本地出站头 `outboundHeaders` / `outboundUa` / `outboundReferer`；`keyContext()` 供本地接口读本请求密钥用户 |
| `StatDayManager.php` | 控制台日聚合表 `statday` |
| `UserStat7Manager.php` | 用户近 7 日聚合 `user.stat7`（写入静默；读经 FrontendUser；含 calls/cost/success_rate） |
| `UserCallStats.php` | 个人调用/积分/排行只读查询（`api/index.php`）；短字段含 `rank`/`rank7`；`parseFields` / `query` / `resolveUserFromRequest` |
| `DashboardStats.php` | 控制台/大屏 KPI·趋势·TOP·live（含 TOP live / 服务器监控快照，**v13.4.0 / v13.16.0**）；geo 飞线三色 |
| `PanelMonitor.php` | 宝塔 / 1Panel 面板监控客户端；控制台「服务器」卡片快照与测试连接（**v13.16.0**） |
| `GeoCityCoords.php` | 大屏飞线全量城市坐标库；`resolveCityName` 地级优先 + 剥离运营商尾缀（v13.0.0 / **v13.2.0**） |
| `ApiKeyManager.php` | 用户 API 调用密钥 CRUD；含 `pointsspent` 密钥累计消耗与 `adjustPointsspent` |
| `ApiLogManager.php` | API 调用日志：keyset 翻页、热冷合并；管理端搜用户名先解析 `user.id`；`listPaged` 支持 `userid`；用户侧 `formatUserSafeRow`（含 IP/归属地）/ `listForUser` / `recentForUser`；LIKE 须转义+`ESCAPE`（E243） |
| `ApiLogArchive.php` | 调用日志冷热归档：开关、三层索引、SQLite 分片；冷库搜索同步 `user_ids` 与 LIKE 转义 |
| `ApiFeedbackManager.php` / `FrontendFeedback.php` / `FeedbackNotify.php` | 接口反馈后台 CRUD / 前台提交 / 邮件通知 |
| `ContentManager.php` | 文章/公告内容 CRUD（kind 区分） |
| `CommentManager.php` / `CommentNotify.php` / `FrontendComment.php` | 文章评论后台、通知、前台提交与列表 |
| `ApiCategoryManager.php` | API 分类 CRUD（**后台向**） |
| `PayConfig.php` | 码支付与积分充值相关系统配置读写 |
| `LinkManager.php` | 友情链接 / 合作伙伴 / 赞助共用 CRUD（`kind` 0/1/2；友链审核；前台申请）（**后台向**） |
| `LinkSiteMeta.php` | 抓取外站 HTML 解析 title/description/favicon（友链一键填充；防 SSRF） |
| `LinkNotify.php` | 友链申请通知管理员；通过后通知申请人邮箱 |
| `FrontendCategory.php` | 前台分类标签（**主题向**） |
| `FrontendApi.php` | 前台公开接口列表与详情（**主题向**）；`listForCatalog` / `slimForCatalog`（目录端点瘦身）；入口 SEO 见 `vs_page_seo_pack`（v10.8.2 / **v13.26.16**） |
| `front/catalog.php` | **HTTP**：前台公开接口目录（POST+CSRF+频控；非 bootstrap 类；**v13.26.16**） |
| `front/playground-key.php` | **HTTP**：登录用户按需取 Playground KEY（POST+CSRF+频控；禁 SSR；**v13.26.16 / E253**） |
| `FrontendLink.php` | 前台已通过且启用的友链列表与本站友链卡片（**主题向**） |
| `FrontendPartner.php` | 前台已启用合作伙伴列表（**主题向**） |
| `FrontendSponsor.php` | 前台赞助收款码 + 赞助名单（**主题向**） |
| `FrontendStats.php` | 前台统计四 KPI：注册用户、今日调用、审核通过接口数、累计调用（**主题向**；禁止主题直调 `ApiManager` 计数） |
| `FrontendAnnouncement.php` / `FrontendArticle.php` / `FrontendAbout.php` | 前台公告 / 文章 / 关于页绑定正文（**主题向**） |
| `markdown/Markdown.php` | Markdown 渲染（本地 Parsedown + 短码；公告/文章/API 文档） |
| `play/codeplay/CodePayClient.php` | 码支付下单/验签客户端（回调见同目录 `notify.php` / `return.php`） |
| `RedisCache.php` | 业务数据缓存（前台/公开列表 + apilog + `cache:userapilog:*` + orders + 控制台 dashboard + statday）；`invalidateApiLog` 同步清用户日志缓存 |
| `OrderManager.php` | 积分/充值订单：按每页条数 + keyset 翻页（无时间窗、无全表 COUNT）；写入后 `invalidateOrders`；kind 含注册赠送/每日签到；搜索先解析用户/类型再精确过滤 + `kind_class`（v10.6.0）；业务时区东八区（v10.6.1） |
| `PointsManager.php` | 余额读写、扣费、充值完成/取消（回调不比对金额，见支付规范 §2.6）、`giftOnRegister` / `checkin`；列表走 OrderManager；扣至零 / 充值履约后触发 `PointsNotify` |
| `PointsNotify.php` | 积分余额归零、充值成功邮件（`mail_notify_points_zero` / `mail_notify_recharge_success`；失败不阻断） |
| `CheckinManager.php` | 每日签到表：同用户同日唯一、横幅状态、失败回滚占位 |
| `RedisService.php` | Redis 连接、监控快照、运行时长格式化（天/时/分/秒）与限流键清理（**后台向**） |
| `ThemeManager.php` | 主题发现、切换、模板渲染、主题内资源 URL；前台/用户中心壳与页 CSS·JS 清单 |
| `Sitemap.php` | 前台 SEO 站点地图（静态页 + 公开接口详情 + 已发布文章）；入口 `sitemap.php` → `Sitemap::emit()` / `/sitemap.xml` |
| `SystemInfo.php` | 关于页环境信息 |
| `Updater.php` | 云端在线更新检测与安装；安全解压；覆盖后按废弃清单清理文件 |
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

**作用：** 定义常量 `VS_VERSION`（以 `core/version.php` 为准；本文档同步至 **13.26.16**）。在线更新、关于页、`update.json` 均以此为准。

**用法：**

```php
echo VS_VERSION;           // 例如 13.26.16（以当前 core/version.php 为准）
echo 'v' . VS_VERSION;     // 例如 v13.26.16
```

**发版时：** 须同步修改 `update.json`、`update-log.json`、`README.md` 徽章。

---

### 4.3 helpers.php

**作用：** 全局函数库，不封装为类。

| 函数 | 作用 |
|------|------|
| `vs_e($value)` | HTML 转义，模板输出必用 |
| `vs_sanitize_http_host($host)` | 清洗 HTTP Host（拒 CRLF/路径符号；允许域名/IPv4/`[IPv6]`+端口） |
| `vs_request_http_host()` | 合法 `HTTP_HOST` → 否则 `SERVER_NAME` → 否则 `localhost` |
| `vs_base_url()` | 站点根 **绝对** URL（**仅用清洗后 Host**，防邮件/绝对链 Host 污染，E244）；**SEO / og / 邮件**用 |
| `vs_site_base_path()` | 安装路径前缀（域名根为 `''`，子目录如 `/foo`）；**非**带域名的绝对 URL（**v13.26.16**） |
| `vs_site_path($path)` | 同站根相对路径（导航、主题资源、详情、本站图）；拒 `//evil` 协议相对；外链 http(s) 原样（**v13.26.16**） |
| `vs_sql_like_escape` / `vs_sql_like_contains` / `vs_sql_like_prefix` | LIKE 字面转义（配合 `ESCAPE '\\'`，防 `%`/`_` 放大扫描，E243） |
| `vs_path_resource_url($script, $id)` | 路径式资源 URL：`/{脚本}/{id}`（通用伪静态；内部走 `vs_site_path`） |
| `vs_api_detail_url($apiId)` | 接口详情 URL（→ `/detail/{id}`） |
| `vs_resolve_path_id()` | 入站解析资源数字 ID（GET 优先，兼容 PATH_INFO） |
| `vs_redirect($url)` | HTTP 重定向 |
| `vs_render_seo_meta()` / `vs_seo_defaults()` / `vs_seo_abs_url()` | SEO / OG / 分享 meta 统一输出（**绝对 https**；`vs_seo_abs_url` 防子目录前缀重复） |
| `vs_render_head()` / `vs_render_foot()` | 输出 HTML 头尾（head 支持 `$seoOpts`；foot 注入 `VS_FRONT_CATALOG` 等） |
| `vs_frontend_page($pageKey, $title)` | **前台页面统一入口**（自动选主题、加载 CSS/JS） |
| `vs_render_404_page()` | **全站 404 页**（根目录 `404.php` / Apache ErrorDocument；含安全法律提示；乱路径由 Nginx 默认页处理，不强制伪静态指到本页） |
| `vs_render_notice()` | 后台提示块 |
| `vs_render_site_logo()` | 站点 Logo |
| `vs_require_secure_post()` | 校验 POST + CSRF |
| `vs_decode_transport_field()` / `vs_decode_transport_fields()` | 解码 `VS64B:`/`VS64:` Base64 表单字段（防 WAF 误拦，v10.15.3） |
| `vs_api_error_exit($errcode, $msg)` | 守卫/代理统一错误 JSON：`{ code:0, msg, errcode }`，传输 HTTP 固定 200（v11.0.0；见 `ApiError`） |
| `vs_safe_embed_url()` / `vs_safe_css_color()` | Markdown 短码外链/色值白名单（防 XSS，v10.15.3 复查） |
| `vs_password_hash()` | 密码哈希 |

**两套 URL（v13.26.16，强制）：**

| 用途 | API | 形态 |
|------|-----|------|
| SEO / 社交 / 邮件 | `vs_base_url()` / `vs_seo_abs_url()` | `https://域名/...` |
| 同站导航 / CSS/JS / 本站图 / catalog | `vs_site_path()` / `vs_site_base_path()` | `/apis` 或 `/子目录/apis` |

**主题开发常用：**

```php
// 前台页面 index.php
vs_frontend_page('home', '首页');

// 模板内输出
echo vs_e($siteName);

// 同站链（源码不写死域名）
echo vs_e(vs_site_path('/apis'));
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
**13.26.7：** `ensureUserDashStatSchema` 幂等补 `user.stat7` / `apikey.pointsspent`（已 mark 迁移的站点也可补列）；密钥消耗按 orders 回填，禁止扫 apilog 回填七日窗。

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


**作用：** 前台展示用的站点信息，从 Config 读取并缓存。

| 概念 | 配置键 | 方法 | 用途 |
|------|--------|------|------|
| **站点名称** | `site_name` | `siteName()` | 前台标题、SEO、Hero 默认文案 |
| **系统名称** | `system_name` | `systemName()` | 后台侧栏/顶栏、关于页首行、管理员登录/忘记密码、用户中心壳层；缺省回落 `site_name` |

| 方法 | 说明 |
|------|------|
| `siteName()` | 站点名称（前台） |
| `systemName()` | 系统/产品名称（后台与用户中心） |
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

**作用：** 用户注册策略——**是否开放注册**、**是否须邮箱验证码**、邮箱后缀白名单。

| 方法 | 说明 |
|------|------|
| `isOpen()` | `register_enabled===1`（默认开放） |
| `requiresEmailVerify()` | `register_email_verify===1`（默认须验证） |
| `assertOpen()` / `closedMessage()` | 关闭时统一错误文案（「已停止注册，如有问题请联系管理员」） |
| `getPolicy()` | 读取后缀策略（`email_suffixes` 数组） |
| `hasEmailSuffixRestriction()` | 是否启用了邮箱后缀限制（列表非空） |
| `parseSuffixInput($input)` | 后台表单文本 → 后缀数组 |
| `formatSuffixInput($suffixes)` | 后缀数组 → 表单回显文本 |
| `saveEmailSuffixes($suffixes)` | 保存后缀列表到 `CONFIG_KEY` |
| `validateEmailSuffix($email)` | 后缀是否允许；不允许返回文案 |

**配置键：**  
- `register_enabled`、`register_email_verify`（`install/migrations/13.26.5.sql` 种子）  
- **`RegisterPolicy::CONFIG_KEY` = `register_policy`**：JSON 存邮箱后缀白名单等  

**硬闸门：** `user/register.php` 与 `UserAuth::register()` 双重拒绝关闭态；免邮箱验证时提交侧仍须 captcha。  
**主题：** 经入口注入 `$registerOpen`、`$emailVerify`、`$formEnabled`、`$registerClosedSub` 等；**禁止主题直连 Config/库**。

---

### 4.11b Sitemap.php

**作用：** 生成 SEO `sitemap.xml`（首页/列表等静态页 + 公开接口 `/detail/{id}` + 已发布文章 `/articles/{id}`）。

| 方法 | 说明 |
|------|------|
| `emit()` | 输出 XML 并结束 |
| `buildXml()` / `collectUrls()` | 组装 URL（上限约 5000） |

**入口：** 根目录 `sitemap.php` → `Sitemap::emit()`；伪静态 `/sitemap.xml`。  

**四处须同步（改 rewrite 时缺一不可）：**

1. 安装向导 Nginx 片段（`vs_install_nginx_rewrite_snippet()` / install 内嵌 snippet）  
2. 根目录 `nginx伪静态配置.md`  
3. 根目录 `.htaccess`（`RewriteRule ^sitemap\.xml$ sitemap.php`）  
4. `README.md` 伪静态说明  

另：站点地图对外地址为 `/sitemap.xml`（伪静态）。**v13.26.16 起不再提供根目录 `robots.txt`**（避免 Disallow 暴露目录结构）。

---

### 4.12 Mailer.php

**作用：** 通过 SMTP 发送邮件（注册验证码、找回密码、登录验证码等）。

| 方法 | 说明 |
|------|------|
| `send($to, $subject, $body)` | 发送邮件，未配置 SMTP 时抛异常 |
| `otpMailBody($displayName, $brandName, $actionDesc, $code, $ttlSeconds)` | 验证码邮件 HTML 正文（登录 / 重置 / 注册可复用） |

**前置条件：** 后台已配置 SMTP（`Config::isMailEnabled()` 为 true）。

---

### 4.13 Auth.php（管理员认证）

**作用：** **后台管理员**登录、登出、会话、资料修改。

| 方法 | 说明 |
|------|------|
| `login($account, $password)` | 账号密码登录，成功返回管理员数组 |
| `loginById($adminId)` | 按 ID 登录（邮箱验证码登录等） |
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
| `configureSessionCookies()` / `sessionCookieSecure()` | Session Cookie；`Secure` 跟随 `isHttps()`（对齐 v5.1.1）；禁止默认 false、禁止登录/每请求清 Cookie |
| `clearSessionCookie()` | 退出时同时清除 Secure / 非 Secure 会话 Cookie |
| `csrfToken()` / `rotateCsrfToken()` | 获取 / 轮换 CSRF |
| `validateCsrf($token)` | 校验 CSRF |
| `requireAuthPost()` | POST 必须带合法 CSRF；失败 JSON 含新 `csrf` |
| `sendSecurityHeaders()` | 认证页 `no-store` + CDN 禁缓存 |
| `sendFrontendSecurityHeaders()` | 前台页 `private, no-store` + `Vary: Cookie` + CDN 禁缓存（E253；防 CSRF/用户态 HTML 被边缘共享） |
| `checkLoginAllowed($username)` | 登录是否被限流 |
| `recordLoginFailure($username)` | 记录登录失败 |
| `checkMailCodeAllowed($email)` | 发验证码是否允许 |
| `issueMailTicket()` / `validateAndConsumeMailTicket($purpose, $ticket)` | 邮件验证码一次性票据（用途见常量） |
| `MAIL_PURPOSE_ADMIN_LOGIN` / `MAIL_PURPOSE_USER_LOGIN` 等 | 发信用途常量（登录 / 重置 / 注册等；须 `normalizeMailPurpose` 放行） |
| `recordOtpFailure($context)` / `clearOtpSession($context)` | OTP 失败计数与会话清理（`admin_login` / `user_login` 等） |

**表单/AJAX 示例：**

```php
AuthSecurity::requireAuthPost();
// 前端：assets/js/auth-csrf.js → VsAuthCsrf.postForm() 凭证失败自动重试一次
```

---

### 4.15b Captcha.php / captcha/*（行为验证）

**作用：** 系统级验证码**门面**；管理员端与用户端可**分别**选择方式。主题 / 认证页只挂载 UI，验票在入口脚本调用 `Captcha::requireValid`。

#### 结构

| 路径 | 说明 |
|------|------|
| `core/Captcha.php` | 门面：mode 归一、场景开关、`publicBoot`、`requireValid`、一次性消费 |
| `core/captcha/local.php` | 本地图形（GD）；session 存场景绑定哈希 |
| `core/captcha/gt3/*` | 极验 3：`GeetestLib.php`、`CheckGeetestStatus.php` 等 |
| `core/captcha/gt4/LoginController.php` | 极验 4 |
| `core/captcha/helper.php` | **`vs_captcha_field` / `vs_captcha_js`**（不在 `helpers.php`） |
| `core/captcha/image.php` | **HTTP**：本地图出图 |
| `core/captcha/register.php` | **HTTP**：极验 register / 校验中转 |

#### Mode 与场景

| 常量 | 值 | 说明 |
|------|-----|------|
| `Captcha::MODE_LOCAL` | `local` | 本站图形 |
| `Captcha::MODE_GT3` | `gt3` | 极验 3 |
| `Captcha::MODE_GT4` | `gt4` | 极验 4 |
| `Captcha::SCENE_ADMIN_LOGIN` | `admin_login` | 管理员登录 |
| `Captcha::SCENE_ADMIN_FORGOT` | `admin_forgot` | 管理员找回 |
| `Captcha::SCENE_USER_LOGIN` | `user_login` | 用户登录 |
| `Captcha::SCENE_USER_REGISTER` | `user_register` | 用户注册 |
| `Captcha::SCENE_USER_FORGOT` | `user_forgot` | 用户找回 |

分端配置：管理员 / 用户侧各自 `captcha_*_mode`（旧版单一 `captcha_mode` 可回退）。

#### 本地图（v13.26.6）

- **hashCode：** 明文先 `strtoupper` 再 `hash_hmac('sha256', …)`（校验**不区分大小写**；图面仍可大小写混排）  
- **pepper：** `serverPepper()` 后缀 `|vs_local_captcha_v3`  
- **一次性消费：** `Captcha::consumeToken` 绑定场景，防本站重放（session `vs_captcha_used`）  
- TTL：本地码约 300s（`CaptchaLocal::TTL`）

#### 前端

三份 `captcha.js` **须保持同步**：

1. `assets/js/captcha.js`（管理员等回落）  
2. `core/theme/default/assets/shell/captcha.js`  
3. `core/theme/slate/assets/shell/captcha.js`  

本地图：**仅首次 focus** 验证码输入框时自动换图（属性 `data-focus-refreshed`）；主题勿另写一套换图逻辑。

#### 主题挂载

```php
vs_captcha_field(Captcha::SCENE_USER_LOGIN);  // 定义于 captcha/helper.php
vs_captcha_js(Captcha::SCENE_USER_LOGIN);
// 入口验票：Captcha::requireValid($scene, $_POST);
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
| `isUserBoundToAdmin($userId)` | 用户是否为某管理员的绑定身份 |
| `activeBindUserCount()` | 全站有效绑定身份去重数量 |
| `userOwnsApi($userId, $apiUserId)` | 接口是否归属该用户（含唯一绑定下的历史 userid=0） |
| `sqlApiOwnedByUser($alias)` | 归属条件 SQL（两个相同 userId 占位符） |

**历史说明：** v3.17.2–v3.30.x 管理员发布曾写 `userid=0`；v3.31.0+ 写绑定用户。贡献者/个人主页须按 `userOwnsApi` / `sqlApiOwnedByUser` 统计，不可只认 `userid = 用户`。

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
**请求方式 `method`：** 存库逗号分隔；`methods` 数组 + `method_label`（如 `GET,POST`）  
**密钥 `needkey`（数字）：** `0` 不需要 / `1` 必须 / `2` 可选  
**密钥传递 `keyways`（v10.17.0）：** 逗号存储；`normalizeKeyways` 归一为 `query` / `header` / `bearer` 有序数组；可多选  
**QPM `qpm`（v10.5.0）：** `0` 不限制 / `>0` 每分钟最大请求次数（无需/可选按 IP，必须按 IP+密钥）  
**计费 `charge` / `price`：** `0` 免费 / `1` 收费；配合 `PointsManager` 扣积分

| 方法 | 说明 |
|------|------|
| `listPublic()` | 前台可见：审核通过且非禁用（含维护中） |
| `listAll` / `listByAudit` / `listByUser` / `listFiltered` | 列表筛选（支持 userid） |
| `create` / `update` / `delete` / `setStatus` / `setAuditStatus` | 写操作（`setAuditStatus` 可带拒绝原因） |
| `formatRow` | 格式化（含 `rejectreason` / `audit_class` / `method_label` / `keyways_label` / `qpm` / `charge`） |
| `normalizeMethods` / `methodsLabel` / `methodsToStorage` | 多 HTTP 方法归一与展示 |
| `normalizeRequireKey` / `requireKeyLabel` 等 | 数字归一与中文标签 |
| `normalizeKeyways` / `keywaysLabel` / `keywaysToStorage` / `hasKeywaysColumn` | keyways 归一、展示、存库、列探测 |
| `normalizeQpm` / `qpmLabel` / `hasQpmColumn` | QPM 归一、展示文案、列探测 |
| `normalizeCharge` / `chargeLabel` / `hasChargeColumn` | 计费归一与标签 |
| `apiTypeBadge` / `requireKeyBadge` | 列表短标签：代理/本地；KEY可选/必填 |
| `countPendingReview()` | 待审核投稿数（侧边栏红点） |

### 4.21.1 ApiNotify.php（邮件通知）

**作用：** 投稿待审通知管理员；审核结果通知投稿用户。依赖 `Mailer` 与系统 SMTP；发信失败不阻断审核主流程。

| 方法 | 说明 |
|------|------|
| `notifyAdminsPending($api)` | 通知全部启用中的管理员邮箱 |
| `notifyUserAuditResult($api, $audit, $reason)` | 通知投稿用户通过/不通过 |

---

### 4.21.2 前台在线测试（默认：浏览器直连）★ 主题开发重点

**默认主题（v4.8.0+）：** 浏览器直连公开 `endpoint`，由本地 `ApiStats::hit` / 代理 `ApiProxy→hitProxy` 记账，`apilog.path` 为真实路径。

```js
VsPlaygroundResponse.directRequest({
  endpoint: api.endpoint, // /api/... 或 /apis/{短码}
  method: 'GET',
  params: { key: '...', q: 'demo' }
}).then(function (res) {
  return VsPlaygroundResponse.renderFetchResponse(res, outputEl);
});
```

**KEY 上下文：** `vs_playground_session_context()` → SSR 仅 `loggedIn` / `apiKeyCount` / urls（**不含**密钥明文，E253）；调试按需 `POST core/front/playground-key.php`。

**可选中继 `PlaygroundRelay`：** 仅兼容旧主题；入口 `core/playground/relay.php`；**禁止**在中继内写 `apilog`（见 E57）。

**媒体：** Content-Type + 文件魔数；**禁止**未知默认 `image`。

**放置原则：** 多主题共用能力放 `core/`；主题 UI 放主题包；根目录不新增内部入口。

---

### 4.21.3 ApiStats.php（调用统计与守卫，**v13.3.0**）

**作用：** 本地脚本 `ApiStats::hit(接口ID)` 与代理 `ApiProxy→hitProxy` 的统一记账、访问守卫与错误输出。

**本地认人（强制）：** 必须传后台接口数字 ID；`0`/省略不记账；**不再**按脚本路径匹配 `endpoint`。站长说明见 `api/统计代码使用说明.md`。

**守卫链 `guardAccess`：** 状态/审核 → QPM（`RateLimitStore`）→ 密钥（`needkey` + `readKey` 按 **keyways**）→ 收费扣积分。

**密钥读取 `readKey($row)`（v10.17.0）：** 按接口 `keyways` 依次尝试：

| keyway | 读取位置 |
|--------|----------|
| `query` | `$_GET['key']` / `$_POST['key']` |
| `header` | 请求头 `X-API-Key` |
| `bearer` | `Authorization: Bearer …` |

`row` 为 null 时三种皆可（兼容旧调用）。

**错误 JSON（v11.0.0）：** 守卫失败经 `jsonExit` → `vs_api_error_exit`，固定：

```json
{"code":0,"msg":"请提供调用密钥","errcode":11001}
```

传输层 HTTP 固定 **200**；业务看 `errcode`（`ApiError` **11001～11018 全套**，见 `businessLabelMap()`）。旧版 `http:401/403` 已废弃。AI 详细文档须用 `aiDetailDocErrcodeClause()` 写全，禁止只列子集。

**日志：** 成功/失败写 `api.calls`、`StatDayManager::recordHit`；详细日志开时写 `apilog`（`ok` / `apikey` / `httpcode`；含异步 `IpLocator` 回填 `iploc`）。大屏飞线按 `ok`+`apikey` 拆绿/黄/红。

**用户近 7 日窗（13.26.7）：** 记账成功后对**有效密钥归属用户**静默调用 `UserStat7Manager::recordHit`；**禁止**用登录 Cookie 回退污染 `user.stat7`（与「我的调用」密钥口径一致）。

---

### 4.21.4 IpLocator.php（IP 归属地）

**作用：** 解析调用方 IP 归属地文案，异步写入 `apilog.iploc`，供数据大屏飞线使用。

| 要点 | 说明 |
|------|------|
| 开关 | `ip_loc_enabled`；仅详细日志开启时触发 |
| 模式 | `ip_loc_mode`=`builtin`（系统内置，**仅 IPv4**，无需填 URL）或 `custom`（仅走自定义接口）；界面勿写明上游厂商 |
| 请求方式 | 自定义：`ip_loc_method`=`get`（默认）或 `post`；GET 拼查询串，POST 表单正文 |
| 认证 | 自定义模式：无 / Bearer / Header / Query |
| 安全 | 自定义 URL 公网校验；禁止跟随跳转；失败负缓存 300s；源码端点按片段拼接，禁明文厂商标识 |
| 性能 | **shutdown 异步回填**，不阻塞接口响应；自定义热路径超时约 5s，探测约 10s |
| 缓存 | Redis `cache:iploc:{md5(ip)}`，TTL 86400s；**探测不写缓存** |
| 设置 | 切内置保存时保留已填自定义参数；测试可带表单草稿，不必先保存 |

---

### 4.21.4b 出站安全三件套（JsonpGuard / ProxyJsonRewrite / ApiOutboundSanitize）

代理网关与 Playground 中继在返回客户端前共用下列规则（**v13.25.0 / v13.25.2**）：

| 类 | 要点 |
|----|------|
| **JsonpGuard** | 回调名白名单 `^[A-Za-z_$][A-Za-z0-9_$]{0,63}$`；识别参数 `callback` / `jsonp` / `jsonpcallback` / `_callback` / `cb`；`stripCallbackParams` 在代理侧剥离，防 JSONP 注入 |
| **ProxyJsonRewrite** | 仅对成功 JSON 做 set/del；若 `ApiError::looksLikeBusinessErrorPayload`（errcode **11001～11018**）则**整段不改写**；禁止 SET 写入含 `/admin` 等后台路径 |
| **ApiOutboundSanitize** | 出站擦除敏感路径字段；业务失败体经 `narrowBusinessErrorBody` **只保留 `code` / `msg` / `errcode`**，防止 `api_info` 等管理字段随错误响应泄露 |

成功响应的 JSON 字段改写能力不变。专题规范见本地 `开发规范/JSONP与出站响应安全规范.md`、`代理JSON字段改写规范.md`。

---

### 4.21.5 AiApiDoc.php / ApiQuickstart.php（AI 文档与快速上手）

**AiApiDoc：** 管理员/用户接口编辑「AI 生成详细文档 / 代码示例」；用户端**仅使用平台 AI**（无自建模型配置）。上下文剔除 `targeturl`/`upkey`/`jsonrewrite`；输出经 `ApiQuickstart::scrubHighlightLeak`；代码片 `wrapQsBlock` 二次 scrub（E232）。

**详细文档 7 章 id（须按此顺序，`AiApiDoc::detailDocSections()`）：**

| 顺序 | section id | 内容要点 |
|------|------------|----------|
| 1 | `intro` | 文首 `# 接口名` + 短概述（v13.26.3：`sanitizeApiTitleName` + `ensureIntroDocTitle`） |
| 2 | `call` | 调用方式 / 鉴权说明 |
| 3 | `params` | 请求参数 |
| 4 | `success` | 成功响应 |
| 5 | `errors` | 业务错误码 |
| 6 | `examples` | **curl + 非空 PHP**（仅这两种） |
| 7 | `notes` | 文末注意事项 |

流式：`generateDetailDocSectionStream`；前端章节失败**自动重试 1 次**，第二次失败才需「继续生成」。

**会话：** `AiChatSession::TTL = 600`（10 分钟）；接口 **create/update 保存成功** 后 `clearAllForActor` 清空该操作者全部短时效会话（自动草稿不清）。

**代码示例（aidoc，最多 27 片 = 3 鉴权 × 9 语言）：** 前端按片 `ai_gen_code_piece_stream`（SSE）；主按钮一键全量；可按鉴权单独生成 9 片（「生成 Query / Header / Bearer」**同行**）。提示词要求**纯代码**（禁思考链）；服务端 `finalizeCodePieceBody` → `stripReasoningArtifacts` + `wrapQsBlock`（E233）。`AiConfig::codeMode`：`sequential` / `parallel`（并发 1～6，CDN 建议 ≤2）。

**默认主题快速上手图标（v12.0.1）：** `assets/img/lang/*.svg`；`detailQsBundle.byAuth[*]` 宜带 `icon_gray`/`icon_color`；另须注入 `window.detailQsLangIcons`（`ApiQuickstart::langIconMap`）。

**代码示例格式（aidoc）：**

```
:::qs lang=curl auth=query
curl …
:::

:::qs lang=python auth=header
…
:::
```

| 属性 | 说明 |
|------|------|
| `lang` | curl / typescript / browser / python / go / java / php / cpp / rust |
| `auth` | **必填**：`query` / `header` / `bearer`；缺省按 query |
| 多鉴权 | 接口 `keyways` 多种时，每种 auth 各一套语言块 |

**ApiQuickstart：** `samplesFromAidoc` / `qsBundleFromAidoc` 解析短码。  
**后台编辑：** 详细文档 / 代码示例 textarea 使用 `data-vs-md="off"`，无右侧实时预览。  
**错误示例：** 须含 `"errcode":11001` 等业务码；传输层 HTTP 固定 200；鉴权错误 `11012`。

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
| `listForTheme()` | 接口数组；Redis 缓存 `call_path`，取出后按当前访问重绑 `endpoint` / `detail_url` / `icon`（同站走 `vs_site_path`） |
| `listForCatalog()` | **目录端点专用**（v13.26.16）：`listForTheme` 后逐条 `slimForCatalog`；供 `core/front/catalog.php` |
| `slimForCatalog($item)` | 去掉 `doc` / `aidoc` / `response` 大字段（首页调试仍可保留 `params`） |
| `findForThemeById($id)` | 单条详情（审核通过；详情允许已禁用）；同样按当前请求重绑 |
| `bindRequestHost` / `bindRequestHostToList` | 将 `call_path` 拼到当前同站路径（或保留外链绝对地址） |
| `countForTheme()` | 公开接口数量 |

**首页 / apis（v13.26.16 强制）：** 主题**禁止**首屏 `json_encode(FrontendApi::listForTheme())`；须 `POST core/front/catalog.php`（见 §1.1 `front/`）+ `VS.fetchFrontCatalog` 后本地筛选。详情页单条 / 推荐卡少量 SSR 仍可调 `listForTheme` / `findForThemeById`（勿灌全站目录）。

**返回字段（每条；catalog 瘦身后无 doc/aidoc/response）：**

| 字段 | 说明 |
|------|------|
| `id` | 接口 ID |
| `name` | 名称 |
| `desc` | 描述 |
| `category` / `category_name` | 分类 id / 原始分类名 |
| `method` / `methods` / `method_label` | 请求方式 |
| `endpoint` | 调用地址（**同站根路径**或外链绝对地址） |
| `call_path` | 路径或外链绝对地址（Redis 缓存依赖此字段，勿把域名烤进缓存语义） |
| `params` / `response` / `doc` / `aidoc` | 参数原文、返回、详细文档、代码示例（**catalog 响应不含**后三者） |
| `params_list` | 解析后的参数表（name/type/required/description/example） |
| `maintenance` | 1=维护中 |
| `needkey` / `needkey_label` | 密钥要求（文案：`无需 KEY` / `KEY 必填` / `KEY 可选`） |
| `keyways` / `keyways_label` | 密钥传递方式数组与中文标签（v10.17.0） |
| `qpm` / `qpm_label` | 每分钟上限；文案「不限制」或「N/MIN」 |
| `charge` / `charge_label` / `points` / `billing_label` | 计费；`billing_label` 为「免费」或「N积分/次」 |
| `author` | 开发者作者卡（无则 null）；含 `profile_url` |
| `calls` / `icon` / `icon_path` / `detail_url` / `createtime` | 其它 |

---

### 4.24b LinkManager.php / LinkSiteMeta / LinkNotify / FrontendLink.php / FrontendPartner.php / FrontendSponsor.php（友链·合作伙伴·赞助）★ 主题开发重点

**共用表 `link`（v5.0.0+，赞助 v5.5.0）：**

| 字段 | 含义 |
|------|------|
| `kind` | `0` 友情链接 · `1` 合作伙伴 · `2` 赞助 |
| `enabled` | `0` 禁用 · `1` 启用（禁用后前台不展示；友链审核状态不变） |
| `status` | 审核：`0` 待审 · `1` 通过 · `2` 拒绝（合作伙伴与赞助固定为通过） |
| `name` / `siteurl` / `icon` | 名称、跳转链接、图标/头像（赞助 siteurl 可选） |
| `description` | 友链简介 / **赞助说明（金额或其它支持）** |
| `contact` | 仅友情链接使用 |

**后台 `LinkManager`：** `create` / `apply` / `update` / `setStatus` / `setEnabled` / `delete` / `listAll($status,$kind)` / `listApproved` / `listPartnersEnabled` / `listSponsorsEnabled`。

**`LinkSiteMeta::fetch($url)`：** 服务端抓取公网页面解析名称/描述/图标；禁止内网与非 http(s)。

**默认主题接口：** `POST /core/theme/default/api/sitemeta.php`（CSRF + 同源）；前端见 `assets/js/pages/applylink.js`。

**`LinkNotify`：** 申请 → 通知管理员（`mail_notify_link_apply`）；通过 → 若联系方式含邮箱则通知申请人（`mail_notify_link_pass`）。

**前台 `FrontendLink`：**

| 方法 | 说明 |
|------|------|
| `listForTheme()` | 已通过且启用的友链（含 name/siteurl/icon/description/host/initial） |
| `listForThemePage()` | 友链页：每次访问 **shuffle**；硬上限 120 |
| `pickForFooter($limit)` | 页脚：每次 **shuffle**；`$limit=0` 显示**全部**；`1～10` 随机取 N 条，超出可「查看更多」 |
| `siteCard()` | 本站友链信息（申请页展示：name/url/desc/icon） |

**前台 `FrontendPartner`：**

| 方法 | 说明 |
|------|------|
| `listForTheme()` | 已启用合作伙伴（name/siteurl/icon/initial） |

**前台 `FrontendSponsor`：**

| 方法 | 说明 |
|------|------|
| `paymentQrs()` | 已配置收款码（alipay/wechat/qq；空不返回） |
| `listForTheme()` | 已启用赞助（name/siteurl/icon/description/initial） |

**配置键：** `sponsor_qr_alipay` / `sponsor_qr_wechat` / `sponsor_qr_qq`

**主题约定：**

- 列表页 `pages/links.php` → `FrontendLink::listForThemePage()`（或 `listForTheme()`）
- 首页合作伙伴区 → **随 catalog `partners=1` 拉取**（`FrontendPartner::listForTheme`）；勿首屏灌包、勿写死外链
- 赞助页 `pages/sponsor.php` → `FrontendSponsor::paymentQrs()` + `listForTheme()`（默认主题：单码切换 + 桌面左右布局 +「感谢支持」+ 赞助卡片多列网格；**禁止**「其它支持方式」；主题二后续对齐）
- 申请页 `pages/applylink.php` + 根入口 `applylink.php`（短名无横线）
- 页脚在二维码上方渲染已通过且启用的友链，末尾固定「申请友链」链到 `/applylink`
- 禁止主题内 SQL；申请提交走 `applylink.php` POST + CSRF + `AjaxResponse`
- 后台：`admin/content/links.php`、`admin/content/partners.php`、`admin/finance/sponsor.php`；操作须 AJAX 局部更新，禁止整页刷新（E61）

**首页 / apis 目录（v13.26.16，正确写法）：**

```php
// 主题页：分类标签可 SSR；接口列表勿 json_encode 全量
$categoryNames = FrontendCategory::nameMap();
// JS：VS.fetchFrontCatalog({ partners: true, shuffle: false })
//    → POST core/front/catalog.php → apiData / categoryNames / partners
```

**说明：** 用户侧「提交接口」等功能未上线时，catalog 可能返回空 `apiData`，**分类标签仍应正常显示**。详情推荐卡等少量服务端挑卡可用 `listForTheme()`，**禁止**把全站目录烤进首页 HTML。

---

### 4.24c SiteMedia.php（内置图片出站）

**作用：** 站点内置图片（分类图标、语言图标、头像素材、支付 / 备案图标等）统一解析为出站 URL。物理文件仍在根目录 `assets/img/`。

| 方法 | 说明 |
|------|------|
| `imgUrl($relative)` | 相对 `assets/img/` → **同站根路径**（v13.26.16）；外链 http(s) 原样；文件不存在返回空串 |
| `imgWebPath($relative)` | 站内路径 `/assets/img/...`（可不强制存在） |
| `resolve(...)` | 解析入库或前端传来的路径/URL，防穿越 |

**主题约定：** 模板里写 `SiteMedia::imgUrl('QQ.svg')`、`SiteMedia::imgUrl('lang/php.svg')` 等；**禁止**手写 `/assets/img/...` 或写死 `https://本域/assets/img/...`。

---

### 4.24d （已移除）主题资源 HTTP 打包

**v13.22.6：** 删除 `ThemeAssetPack.php` 与 `theme-asset.php`。前台 / 用户中心改回**逐文件** `<link>` / `<script>`（清单见 `ThemeManager`）。在线升级时由 `install/obsolete-files.json` 清理旧站残留。  
**v13.26.5：** 默认主题禁止运行时 Tailwind；`defaultFrontendAssets` 加载：

1. `fonts-local.css`（本地 JetBrains Mono）  
2. **`assets/css/feer-compat.css`**（静态工具类，**替代**运行时 Tailwind）  
3. 页级 / `theme-tokens` 等主题 CSS  

中文走系统字体栈；**禁止**再链境外 Google Fonts / 再挂运行时 Tailwind。

---

### 4.24e UserDashHello.php（用户控制台问候）

**作用：** 用户中心控制台按时段问候。**24 个 1 小时槽**（00 … 23）；每槽多条 hello/hint，每次随机；双主题共用文案池。**4–5 点属「凌晨」槽，不写「早上好」。**

| 方法 | 说明 |
|------|------|
| `pick($displayName)` | 返回 `hello` / `hint` / `slot` / `hour` |

主题控制台页调用后做打字动效即可；文案改动只改本类。

---

### 4.25 ThemeManager.php（主题引擎）

**作用：** 主题发现、切换、模板渲染、资源 URL、主题设置读写。

**主题目录：** `core/theme/{themeId}/`（须含 `theme.json`；推荐含 `assets/shell/`、`assets/css/`、`assets/js/`、`pages/`、`layout/`）

**主题设置存储：** MySQL `config` 键 `themesettings`，值为 JSON 对象，键为主题 ID（如 `default` / `slate`），值为该主题配置。`listThemes()` 扫描主题包后自动为缺失主题补空对象；旧 `data/settings.json` 仅一次性迁入，不再写入。

**常用展示配置：** `stats_num_format` = `full`（完整数字）| `compact`（单位转换）；由各主题 `theme.json` settings 声明，首页「累计调用」读取。

| 方法 | 说明 |
|------|------|
| `activeId()` | 当前主题 ID（读 Config `frontend_theme`） |
| `listThemes()` | 已安装主题列表（并 sync `themesettings`） |
| `setActive($themeId)` | 切换主题 |
| `readThemeData` / `writeThemeData` | 读/写某主题配置段（库） |
| `readAllThemesettings` / `syncThemesettingsEntries` | 总表读写与扫描补齐 |
| `renderBody($pageKey, $title, $data)` | 渲染 layout + pages（`pageKey` 清洗；pages 经 `realpath` 限制在主题目录） |
| `themeSetting($key, $default)` | 读当前主题 settings |
| `assetUrl($themeId, $relative)` | 主题静态资源 URL（禁 `..`；主题须合法；**v13.26.16** 同站根路径） |
| `resolveThemeFile` / `resolveActiveThemeFile` | 主题内文件绝对路径（严格当前主题；禁穿越） |
| `shellUrl($file)` | 当前主题 `assets/shell/` 下单文件 URL |
| `pageScriptUrl($file)` | 当前主题页脚本 URL（如 `user-dashboard.js` / `user-logs.js`） |
| `frontendShellCssHrefs` / `frontendShellJsHrefs` | 前台壳 CSS/JS 逐文件列表 |
| `userShellCssHrefs` / `userShellJsHrefs` | 用户中心壳 CSS/JS 逐文件列表 |
| `userMenuGroups()` | 用户中心侧栏（含「日志查询」；开发者项按角色隐藏） |
| `navItems()` | 前台导航项 |
| `defaultFrontendAssets($pageKey)` | 默认主题前台页 CSS/JS 清单（逐文件 URL） |

**新建主题步骤：**

1. 复制 `core/theme/default/` 或 `slate/` 为 `core/theme/mytheme/`（含完整 `assets/shell` 与 `assets/js`）
2. 编写 `theme.json`（id、name、settings 等）
3. 在 `pages/` 下写 PHP，**分类与接口只调 `FrontendCategory` / `FrontendApi`**；图标用 `SiteMedia`
4. 后台「主题设置」切换主题；打开页面时会自动在 `themesettings` 中新增该主题配置段

**主题隔离（强制）：**

- 各主题 CSS/JS **完全独立**，无跨主题文件回退，**禁止**引用根目录 `assets/css|js` 作前台/用户中心壳层
- 用户中心壳样式用本主题 `assets/shell/user-shell.css` 等（`userShellCssHrefs` 逐文件加载），**不是**根目录 `admin.css`
- 根目录 `admin.css` / 管理员脚本 **只给管理员后台**
- 详见《主题资源隔离规范》；旧「用户中心共用 admin.css」说法已废止

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

**配置键（`vs_config`，可选）：** `redis_host`、`redis_port`、`redis_password`、`redis_database`、`redis_prefix`（默认 `127.0.0.1:6379`、db0、`apinexus:`）。同机多站共用 Redis 时 **必须** 为每站设置互异 `redis_prefix`（安装向导 / 系统设置均可配；保存清空本站键空间）。见《Redis缓存键前缀规范》。

**前缀 API（v13.21.0）：** `normalizePrefix` / `detectPrefixConflict` / `flushKeyspace` / `savePrefixConfig`；禁止对整库 `FLUSHDB`。

**业务缓存项（`RedisCache`，监控列表 v10.6.0 / 扩充 v10.16.0）：**

| 逻辑键 | TTL | 说明 |
|--------|-----|------|
| `cache:api:public_list` | 120s | 公开接口列表 |
| `cache:frontend:*` | 120～300s | 接口/分类/友链/伙伴/赞助/文章/公告/贡献者等前台 remember |
| `cache:frontend:misc:*` | 可变 | 其它前台数据（监控标签「其他前台数据」） |
| `cache:iploc:*` | 86400s | IP 归属地 |
| `cache:apilog:query:*` | 45s | 日志查询结果 |
| `cache:apilog:range_total:*` | 90s | 时间窗无筛选总数 |
| `cache:apilog:today_count` | 30s | 今日调用次数 |
| `cache:dashboard:*` | 8～300s | 控制台/大屏分层统计 |
| `cache:orders:range_total:*` | 90s | 订单/积分搜索总数 |

**热路径禁止：** `incrementCallCount` 不得调用 `invalidateFrontend()`（v10.16.0 复查）；今日计数仅 `forget(KEY_APILOG_TODAY)`。

日志相关调用 `RedisCache::invalidateApiLog()`；内容变更可 `invalidateFrontend()`（勿在每次 API 命中时调用）。

监控页业务列表展示**逻辑键名**，不写中文业务用途说明；搜索框放大镜须对齐。

---

### 4.27 SystemInfo.php

**作用：** 收集 PHP、MySQL、操作系统等环境信息，供关于页展示。

```php
$rows = SystemInfo::collect(); // [['label'=>'PHP 版本','value'=>'8.2'], ...]
```

---

### 4.28 Updater.php

**作用：** 检测新版本、下载 `apinexus{版本}.zip`、安全解压覆盖（保护 `config/`、`data/`），并按清单清理废弃文件。

**更新源顺序（三重兜底）：** Gitee → GitCode → GitHub（拉取兜底顺序，仓库无主次）。`update.json` / `version.php` / 更新包均按此顺序尝试；可信域名单含 gitee / gitcode / github 相关主机。

| 方法 | 说明 |
|------|------|
| `updateMirrors()` | 三源镜像配置（清单 / version / update-log URL） |
| `localVersion()` | 本地版本 |
| `checkForUpdate()` | 检测是否有新版本 |
| `fetchRemoteManifest()` | 按镜像顺序拉取 `update.json`（失败再试 `version.php`） |
| `buildUpdatePackageUrls()` | 构建下载链（Gitee 发行包 → GitCode 归档 → GitHub 发行/归档） |
| `isSafeZipEntryName()` | Zip Slip：拒绝 `..` / 绝对路径等危险条目（v10.8.0） |
| `copyFileSafe()` | 安全写入：chmod / 删旧 / copy / file_put_contents |
| `isOptionalUpdatePath()` | 发行说明等非关键路径，写入失败可跳过 |
| `downloadAndApply($version)` | 下载并应用更新包 |
| `removeObsoleteFiles()` | 覆盖后删除 `install/obsolete-files.json` 声明的残留文件 |
| `protectedRelativePaths()` | 更新时绝不可覆盖的路径 |

**废弃文件：** 发行包内维护 `install/obsolete-files.json`（`files` 数组为相对项目根的路径）。部署步骤在 `copyTree` 之后执行删除；不会触及受保护路径。

---

### 4.29 UpdateLog.php

**作用：** 读取版本历史（优先云端 `update-log.json`：Gitee → GitCode → GitHub，失败读本地）。

| 方法 | 说明 |
|------|------|
| `remoteUrls()` | 各镜像 update-log 地址 |
| `allVersions()` / `payloadForApi()` | 版本列表 |
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

**出站 URL 须分清（v13.26.5）：**

| 类型 | 形态 | 说明 |
|------|------|------|
| **主题 / 前端可见出站** | `/user/oauth/start?provider=qq\|gitee` | **无** `.php` 后缀（与全站去后缀一致） |
| **第三方登记的回调** | `/user/oauth/callback.php?provider=…`（`OAuthConfig::callbackUrl`） | **仍带** `.php`（须与开放平台登记一致，勿擅自去掉） |
| **绑定页** | 入口脚本仍为 `user/oauth/bind.php`；对外链出宜去后缀（若经伪静态入口） | 仅已注册用户可绑定 |

**规则：** 仅**已注册用户**可绑定；首次 OAuth 需走绑定页。

---

## 六、主题开发对接指南（完整 API）

> **铁律：** 主题 **零直连数据库**。所有展示数据、用户态、站点信息、图标 URL，一律通过 `core/` 已注册类获取。  
> 缺能力时：先在 core 补 `Frontend*`（必要时再补 `*Manager`）并注册 `bootstrap.php`，再改主题。  
> 参考实现：`core/theme/default/`、`core/theme/slate/`。

### 6.0 一页速查：主题该调谁？

| 你要做什么 | 调用（唯一推荐） | 禁止 |
|------------|------------------|------|
| 读公开接口**目录**（首页/apis） | `POST core/front/catalog.php` / `VS.fetchFrontCatalog`（服务端 `FrontendApi::listForCatalog`） | 首屏 `json_encode(listForTheme())`；`ApiManager::*` |
| 读公开接口**单条详情** | `FrontendApi::findForThemeById($id)`（入口常已注入 `$api`） | `ApiManager::*` 查库 |
| 读分类标签 | `FrontendCategory::listTags()` / `nameMap()` | `ApiCategoryManager::*` |
| 读首页统计 | `FrontendStats::userCount()` / `todayCallCount()` / `approvedApiCount()` / `totalCallCount()` | 主题内 COUNT SQL / 直接调 Manager 统计 |
| 读友链 / 页脚友链 | `FrontendLink::listForThemePage()` / `pickForFooter($n)` / `siteCard()` | `LinkManager::*` |
| 读合作伙伴 | 首页：`catalog partners=1`；其它页可 `FrontendPartner::listForTheme()` | 写死外链、首屏灌伙伴大包或 SQL |
| 读赞助名单 + 收款码 | `FrontendSponsor::listForTheme()` + `paymentQrs()` | 手写收款码路径 |
| 读文章列表/详情 | `FrontendArticle::listForTheme()` / `listPaged()` / `findById()` | `ContentManager::*` |
| 读公告 / 弹窗公告 | `FrontendAnnouncement::listForTheme()` / `listPopups()` | 同上 |
| 读关于页正文 | `FrontendAbout::getBoundArticle()` | 主题硬编码长文 |
| 读贡献者 / 个人主页 | `FrontendContributor::listForTheme()` / `findProfile($uid)` | 拼用户表 SQL |
| 当前登录用户展示 | `FrontendUser::current()` | 主题直读 Session 字段拼装 |
| 是否已登录 / 强制登录 | `UserAuth::check()` / `requireLogin()` | 自造 session key |
| 站点名 / Logo / 备案 | `SiteContext::*` | 读 `Config::get` 当展示（后台键留给 core） |
| 主题配置项 | `ThemeManager::themeSetting*()` | 读别的主题的 settings |
| 内置图标 URL | `SiteMedia::imgUrl('xxx.svg')` | 手写 `/assets/img/...` |
| 用户头像 | 已在 `FrontendUser`/`FrontendContributor`；兜底 `UserAvatar::*` | 外链拼 QQ 头像逻辑自造 |
| 详情快速上手代码 | `ApiQuickstart::qsBundleFromAidoc($aidoc, $keyways)` | 主题内解析 aidoc |
| Markdown 渲染 | 优先用 Frontend* 已给的 `body_html`；必要时 `Markdown::render()` | 主题自带 MD 引擎 |
| 提交评论 / 反馈 | `FrontendComment::submit()` / `FrontendFeedback::submit()` | 主题 INSERT |
| AJAX 成功/失败 | `AjaxResponse::success` / `error` + `vs_require_secure_post()` | 自造 JSON 协议 |
| 壳层 CSS/JS URL | `ThemeManager::shellUrl` / `frontendShell*Hrefs` / `assetUrl` | 引用根目录 `assets/css\|js` 或其它主题 |

---

### 6.1 请求如何进到主题页？

#### 6.1.1 公开前台（访客可见）

根目录入口脚本（如 `index.php`、`apis.php`、`detail.php`…）在 `bootstrap` 之后调用：

```php
vs_frontend_page($pageKey, $pageTitle, $pageData);
```

| 入口脚本 | `$pageKey` | 主题文件 |
|----------|------------|----------|
| `index.php` | `home` | `pages/home.php` |
| `apis.php` | `apis` | `pages/apis.php` |
| `detail.php` | `detail` | `pages/detail.php`（`$pageData` 含 `api` / `notFound` / `playground`） |
| `articles.php` | `articles` | `pages/articles.php` |
| `about.php` | `about` | `pages/about.php` |
| `links.php` | `links` | `pages/links.php` |
| `applylink.php` | `applylink` | `pages/applylink.php` |
| `sponsor.php` | `sponsor` | `pages/sponsor.php` |
| `contributors.php` | `contributors` | `pages/contributors.php` |
| `profile.php` | `profile` | `pages/profile.php` |

**管道：**

```
入口.php
  → vs_frontend_page()
      → 剥 seo → vs_page_seo_pack → $pageData['pageSeo']
      → ThemeManager::frontendShellCssHrefs / JsHrefs
      → default 主题：defaultFrontendAssets($pageKey)（多文件 CSS/JS）
      → 其它主题：activeStylesheetHref + activeScriptHref（theme.css / theme.js）
      → vs_render_head
      → ThemeManager::renderBody($pageKey, $pageTitle, $pageData)
            → layout/header.php
            → pages/{pageKey}.php
            → layout/footer.php
      → vs_render_foot
```

**`renderBody` 注入到模板的变量（始终有）：**

`$vsBase`（**站内路径前缀**，v13.26.16；不是 `https://域名`）、`$siteName`、`$navName`、`$systemName`、`$copyrightName`、`$copyrightUrl`、`$siteDesc`、`$pageKey`、`$pageTitle`、`$navItems`、`$activeNav`、`$userLoggedIn`、`$authUrl`、`$authLabel`、`$authAvatarUrl`、`$themeId`，以及 `$pageData` 全部键（含 `pageSeo`）。

> **陷阱：** 需要 Host 时用 `parse_url(vs_base_url(), PHP_URL_HOST)`，**不要**对 `$vsBase` 做 `parse_url(..., PHP_URL_HOST)`（路径前缀无 Host）。

模板首行必须：

```php
<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
```

#### 6.1.2 用户中心（需登录）

```
user/init.php → UserAuth::requireLogin()
  → 业务入口（如 user/index.php）
  → vs_user_render_page(...) → ThemeManager::renderUserPage
       → user/pages/{pageKey}.php
       → 布局：user/layout.php（vs_theme_user_layout_start/end）
```

登录/注册/找回/OAuth 绑定：

```
ThemeManager::renderAuthPage($pageKey, ...)
  → user/auth/{pageKey}.php
  → ThemeManager::renderThemeAuthHead / Foot（加载 user/auth/layout.php）
```

#### 6.1.3 主题内 AJAX（可选）

可放 `core/theme/{id}/api/*.php`（如 default 的 `api/sitemeta.php`）。  
写操作协议：

```
POST + csrf_token + 同源
→ vs_require_secure_post()
→ Frontend* / 业务
→ AjaxResponse::success($msg, $extra)   // {code:1, msg, ...}
→ AjaxResponse::error($msg)             // {code:0, msg}
```

前端暴露：`window.VS_CSRF_TOKEN = <?php echo json_encode(AuthSecurity::csrfToken()); ?>;`

---

### 6.2 主题目录结构（必须 / 推荐）

```text
core/theme/{id}/
  theme.json                 ← 必须（发现主题的门禁）
  preview.png                ← 推荐
  layout/
    header.php               ← 前台必须
    footer.php               ← 强烈推荐
  pages/
    home.php, apis.php, detail.php, articles.php, about.php,
    links.php, applylink.php, sponsor.php, contributors.php, profile.php
  assets/
    theme.css / theme.js     ← 非 default 主题主资源
    user.css / user.js
    auth.css / auth.js
    shell/                   ← common/toast/modal/icons/site-footer/user-shell…
    css/  js/                ← default 按页拆分（见 ThemeManager::defaultFrontendAssets）
  user/
    layout.php
    auth/layout.php + login.php + register.php + forgot.php + bind.php
    pages/dashboard.php, apimanage.php, keys.php, recharge.php, points.php, account.php
  partials/                  ← 可选片段
  api/                       ← 可选主题 AJAX
```

**`theme.json` 关键字段：** `id`（须与目录名一致，`/^[a-z0-9][a-z0-9_-]{0,31}$/i`）、`name`、`version`、`author`、`description`、`preview`、`settings[]`。

**settings 单项：** `key`、`label`、`type`（`text|textarea|number|checkbox|select`）、可选 `placeholder` / `default` / `options[{value,label}]`。

读取：

```php
ThemeManager::themeSetting('stats_num_format', 'compact');
ThemeManager::themeSettingStr('hero_title', '');
ThemeManager::themeSettingBool('show_partners', true);
ThemeManager::themeSettingInt('xxx', 0);
```

**新建主题步骤：**

1. 复制 `default` 或 `slate` 为 `core/theme/mytheme/`（含完整 shell）  
2. 改 `theme.json` 的 `id` / `name` / settings  
3. 后台「主题设置」切换；打开页面时会自动在 `themesettings` 补该主题配置段  
4. **禁止**引用其它主题或根目录前台 CSS/JS；图标一律 `SiteMedia`  

---

### 6.3 Frontend* 数据 API（主题只读入口）

下列方法均可在主题 PHP 中直接调用（类已由 `bootstrap.php` 加载）。

#### 6.3.1 `FrontendCategory` — 分类

| 方法 | 返回 |
|------|------|
| `listTags()` | `[{id, name}, …]`；含「全部」 |
| `nameMap()` | `{all:"全部", "12":"工具", …}` |
| `nameToIdMap()` | `{名称: id}` |
| `resolveIdByName($name)` | id 字符串或 `''` |
| `countEnabled()` | int |
| `tagVisibleLimit()` | int，**15**（超出由主题做「更多」） |

常量：`FrontendCategory::ALL_ID` = `'all'`，`ALL_NAME` = `'全部'`。

```php
$tags = FrontendCategory::listTags();
foreach ($tags as $tag) {
    // $tag['id'] === 'all' 或数字字符串分类 id
    echo vs_e($tag['name']);
}
```

#### 6.3.2 `FrontendApi` — 公开接口

| 方法 | 返回 |
|------|------|
| `listForTheme()` | 公开接口数组（已按当前请求重绑 `endpoint` / `detail_url` / `icon`；**勿整表灌进首页 HTML**） |
| `listForCatalog()` | 目录专用瘦身列表（无 doc/aidoc/response；**v13.26.16**） |
| `slimForCatalog($item)` | 单条瘦身 |
| `findForThemeById($id)` | 单条（含 `author`）或 `null` |
| `countForTheme()` | int |
| `billingLabel($charge, $price)` | 文案 |
| `parseParamsList($raw)` / `prettyParamsJson($raw)` | 参数辅助 |

**列表/详情字段（主题可用）：**

`id, name, desc, category, category_name, method, methods, method_label, endpoint, call_path, apitype, params, response, doc, aidoc, maintenance(0|1), needkey, needkey_label, keyways, keyways_label, qpm, qpm_label, calls, icon, icon_path, detail_url, charge, charge_label, points, billing_label, createtime, params_list`  
详情另有：`author => {id, username, avatar, profile_url}|null`  
**catalog 响应：** 同上但**不含** `doc` / `aidoc` / `response`。

```php
// 首页 / apis：空壳 + VS.fetchFrontCatalog({ partners: true })
// 详情 / 少量推荐卡：
$api  = FrontendApi::findForThemeById((int) $apiId);
// maintenance === 1 时主题须按维护态展示，勿引导真实调用
```

筛选约定：卡片上带 `data-category="<?php echo vs_e($api['category']); ?>"`，与 `FrontendCategory` 的 id 对齐。

#### 6.3.3 `FrontendStats` — 统计（首页 KPI）

| 方法 | 含义 |
|------|------|
| `userCount()` | 注册用户数 |
| `todayCallCount()` | 今日调用 |
| `approvedApiCount()` | 审核通过接口数 |
| `totalCallCount()` | 全站累计调用 |

```php
$apiCount   = FrontendStats::approvedApiCount();
$totalCalls = FrontendStats::totalCallCount();
$userCount  = FrontendStats::userCount();
$todayCalls = FrontendStats::todayCallCount();
```

#### 6.3.4 `FrontendLink` / `FrontendPartner` / `FrontendSponsor`

**友链 `FrontendLink`**

| 方法 | 返回 |
|------|------|
| `listForTheme()` | 已通过且启用 |
| `listForThemePage()` | `{items, total, truncated, limit}`（硬上限 120，每次 shuffle） |
| `pickForFooter($limit=0)` | `{items, has_more, total, limit}`（页脚；0=全部，上限 10） |
| `siteCard()` | 本站卡片 `{name,url,desc,icon}`（申请页） |
| `formatForTheme($row)` | `{id,name,siteurl,icon,description,host,initial}` |

**合作伙伴 `FrontendPartner`：** `listForTheme()` → `{id,name,siteurl,icon,initial}`  
**赞助 `FrontendSponsor`：** `paymentQrs()` → `[{id,label,url}]`（alipay/wechat/qq）；`listForTheme()` 名单

#### 6.3.5 `FrontendArticle` / `FrontendAnnouncement` / `FrontendAbout`

**文章**

| 方法 | 返回 |
|------|------|
| `listForTheme($limit=10)` | 列表（无正文；limit 1–50） |
| `listPaged($page, $pageSize, $beforeId)` | 分页包 |
| `findById($id, $incrementViews=true)` | 含 `body` + **`body_html`** |

列表字段：`id, title, summary, cover, coverlayout, coverlayout_label, views, views_label, createtime`

**公告：** `listForTheme()` / `listPopups()` / `findById($id)`  
字段：`id, title, summary, body, body_html, preview, ispinned, ispopup, createtime`

**关于：** `FrontendAbout::getBoundArticle()` → `{id,title,summary,body,body_html,createtime}|null`

#### 6.3.6 `FrontendComment` / `FrontendFeedback`

| 方法 | 说明 |
|------|------|
| `FrontendComment::tableReady()` | 表是否就绪 |
| `listByContentId($contentid)` | 已通过评论 |
| `submit(...)` | 成功返回评论数组；失败返回 **错误字符串** |
| `FrontendFeedback::tableReady()` | |
| `submit($apiid, $content)` | 须登录；成功数组 / 失败字符串 |

#### 6.3.7 `FrontendUser` — 当前用户 / 控制台

| 方法 | 返回 |
|------|------|
| `current()` | 格式化用户或 `null` |
| `format($user)` | 标准资料 |
| `checkinBanner()` | `{enabled, checked_today, min, max, show_banner}` |
| `doCheckin()` | `{ok, msg, amount?, balance?, points?}` |
| `dashboardStats()` | 控制台 KPI |
| `myLogsPaged($opts)` | 本人调用日志分页（白名单字段） |

**用户字段：** `id, username, email, avatar, bio, blog, wallpaper, role, role_label, can_publish_api, points, createtime, lastlogin, profile_url`  
**dashboardStats：** `points, points_spent, email, createtime, lastlogin, role_label, can_publish_api, api_total, api_approved, api_pending, api_rejected, api_calls, key_total, key_calls, stat7, recent, detail_enabled, checkin_enabled, checked_today`（`stat7` 含今日/日均/折线序列/**本人近期调用排行** `top_today`/`top_7d`/`top`；`recent` 近若干条白名单；视图已不展示令牌总数 KPI，字段仍可返回；须登录；主题禁止直查库）

**findForThemeById：** 详情允许**已审核且已禁用**（`disabled=1`，真实 endpoint 清空由主题模糊占位）；列表 `listForTheme` 仍排除禁用。
**myLogsPaged：** 仅当前会话用户；禁止客户端指定 userid；无详情接口

问候文案：`UserDashHello::pick($displayName)` → `{hello, hint, slot, hour}`。

#### 6.3.8 `FrontendContributor` — 贡献者

| 方法 | 返回 |
|------|------|
| `listForTheme()` | 卡片列表 |
| `findProfile($userId)` | 卡片 + `apis[]` |
| `listApisForUser($userId)` | 该用户公开接口 |
| `wallpaperUrl($profile)` / `joinLabel($createtime)` | 辅助 |

卡片字段：`id, username, avatar, letter, bio, bio_custom, blog, wallpaper, apicount, calls, calls_label, join_label, createtime, profile_url, role_label`

---

### 6.4 站点 / 主题引擎 / 媒体 / 认证

#### 6.4.1 `SiteContext`（展示用）

优先用访问器，勿在主题里拆 Config 键：

`siteName()`、`systemName()`、`navName()`、`copyrightName()`、`copyrightUrl()`、  
`siteDescription()`、`siteKeywords()`、`siteFavicon()`、`siteLogo()`、`siteRuntimeStart()`、  
`footerHtmlLeft/Center/Right()`、`footerQr1/2{Enabled,Name,Url}()`、  
`icpLink()`、`gonganLink($number)`、`beianInfo()`。

#### 6.4.2 `ThemeManager`（主题只读侧）

| 方法 | 用途 |
|------|------|
| `activeId()` / `themeDir()` / `isValidTheme()` | 当前主题 |
| `themeSetting*` | 读本主题 settings |
| `navItems()` | 主导航 `[{id,label,url}]` |
| `userMenuGroups()` | 用户中心侧栏（会按角色隐藏开发者项） |
| `assetUrl($themeId, $relative)` | 主题包内资源 URL |
| `shellUrl($file)` / `pageScriptUrl($file)` | shell / js 单文件 |
| `frontendShellCssHrefs()` / `frontendShellJsHrefs()` | 前台壳清单 |
| `userShellCssHrefs` / `userShellJsHrefs` | 用户中心壳 |
| `defaultFrontendAssets($pageKey)` | **仅 default** 多文件清单 |
| `activeStylesheetHref()` / `activeScriptHref()` | 非 default 的 theme.css/js |
| `renderBody` / `renderUserPage` / `renderAuthPage` | 通常由入口 helper 调用，主题页不必再调 |

#### 6.4.3 `SiteMedia` / `UserAvatar`

```php
$icon = SiteMedia::imgUrl('QQ.svg');          // 不存在则 ''
$av   = UserAvatar::resolve($userRow);        // 或 FrontendUser 已带 avatar
$fb   = UserAvatar::defaultAvatar();
```

#### 6.4.4 `UserAuth`（会话）

主题安全用法：`check()`、`id()`、`user()`、`requireLogin()`、`redirectIfLoggedIn()`、`logout()`。  
**展示资料请用 `FrontendUser::current()`。** 登录/注册提交由入口脚本处理，勿在纯视图里散落写库逻辑。

#### 6.4.5 验证码（登录/注册/找回）

- UI：`vs_captcha_field($scene)`、`vs_captcha_js($scene)`（定义于 **`core/captcha/helper.php`**，非 `helpers.php`）  
- 场景常量（写全名）：`Captcha::SCENE_USER_LOGIN` / `Captcha::SCENE_USER_REGISTER` / `Captcha::SCENE_USER_FORGOT`  
- 校验在入口：`Captcha::requireValid`（主题模板不负责验票）  
- 本地图（v13.26.6）：校验不区分大小写；用户**首次聚焦**验证码输入框时自动换图（`data-focus-refreshed`；逻辑在三份同步的 `captcha.js`，主题勿另写一套）

---

### 6.5 详情页专用：快速上手 / Markdown / 在线测试

```php
// 详情数据来自入口注入的 $api（已由 FrontendApi::findForThemeById 准备）
$qsBundle = ApiQuickstart::qsBundleFromAidoc(
    isset($api['aidoc']) ? $api['aidoc'] : '',
    isset($api['keyways']) ? $api['keyways'] : array('query')
);
// $qsBundle: {auths, authLabels, byAuth}
// byAuth[*] 项建议含 icon_gray / icon_color；另注入 window.detailQsLangIcons = ApiQuickstart::langIconMap()

// 长文：优先 $api 已格式化字段；裸 Markdown：
$html = Markdown::render($rawMarkdown);
```

在线测试上下文（入口常注入）：`vs_playground_session_context()` →  
`{loggedIn, apiKeyCount, userCenterUrl, loginUrl, csrf, playUrl, keysUrl}`（SSR **无** `apiKey`；取钥见 `core/front/playground-key.php`）。  
**默认主题浏览器直连公开 endpoint**，由 core 记账；勿在主题里写 apilog。

鉴权展示：用 `$api['needkey_label']`、`$api['keyways']`、`$api['keyways_label']`、`$api['qpm_label']`；  
**不要**再调 `ApiManager::keywaysLabel()`。缺省可写死 `'query'` / `'Query 参数'`。

---

### 6.6 常用 helpers（`core/helpers.php`）

| 函数 | 用途 |
|------|------|
| `vs_e($v)` | HTML 转义（用户内容必用） |
| `vs_base_url()` | 站点根**绝对** URL（SEO / 邮件） |
| `vs_site_base_path()` / `vs_site_path($path)` | 同站路径前缀 / 根相对路径（导航、资源、catalog；**v13.26.16**） |
| `vs_api_detail_url($id)` / `vs_profile_url($id)` | 伪静态友好链接（同站路径） |
| `vs_frontend_page(...)` | 公开页入口（根脚本用） |
| `vs_page_seo_pack` / `vs_render_theme_seo_block` | SEO |
| `vs_render_footer_custom_bar` / `vs_render_footer_qrs` | 页脚 |
| `vs_copyright_html` / `vs_site_runtime_start` | 版权 / 运行时长 |
| `vs_require_secure_post()` | AJAX POST 门禁 |
| `vs_playground_session_context()` | 详情调试条 |
| `vs_is_allowed_http_url` / `vs_safe_embed_url` | URL 安全 |
| `vs_user_render_page` | 用户中心入口（`user/includes/layout.php`） |

**验证码挂载不在本文件：** `vs_captcha_field` / `vs_captcha_js` 定义于 **`core/captcha/helper.php`**（由 `Captcha` 门面加载），见 §4.15b。

---

### 6.7 标准页面写法（对照表）

| 页面 | 主题应取数 |
|------|------------|
| 首页 | **空壳** + `VS.fetchFrontCatalog({ partners })`；分类标签 `FrontendCategory::*`；KPI `FrontendStats::*`；公告 `FrontendAnnouncement::*`；页脚 `FrontendLink::pickForFooter`；`ThemeManager::themeSetting*` + `SiteContext::*` |
| 接口目录（apis） | 同目录异步拉取（可 `shuffle`）+ 分类筛选（前端 data-category）；**禁止**首屏全量 `apiData` |
| 接口详情 | 入口已给 `$api`；补 `ApiQuickstart` / `FrontendFeedback`；推荐接口可少量挑卡，勿灌全站目录 |
| 文章 | `FrontendArticle::*` + `FrontendComment::*` |
| 关于 | `FrontendAbout::getBoundArticle()` |
| 友链 | `FrontendLink::listForThemePage` + `siteCard`；申请 POST 走入口/主题 api + CSRF |
| 赞助 | `FrontendSponsor::paymentQrs` + `listForTheme` |
| 贡献者 | `FrontendContributor::listForTheme` |
| 个人主页 | 入口注入或 `findProfile` |
| 用户控制台 | `FrontendUser::current` + `dashboardStats` + `UserDashHello::pick` + `checkinBanner` |
| 用户日志 | `FrontendUser::myLogsPaged` → `ApiLogManager::listForUser` |

**分类标签标准：**

1. 循环 `listTags()`  
2. 「全部」用 `ALL_ID`  
3. 超过 `tagVisibleLimit()` 做「更多」  
4. 无公开接口时分类栏仍显示  

---

### 6.8 绝对禁止（主题）

1. `Database::connect()` / 任何 SQL / 表名 / 字段名出现在主题  
2. 用 `*Manager` **渲染或取展示数据**（`ApiManager`、`ContentManager`、`LinkManager`…）——统计请走 `FrontendStats`  
3. 手写 `/assets/img/...`、引用根目录 `/assets/css|js` 作前台/用户中心壳  
4. `include` / `assetUrl` 指向**其它主题**目录  
5. 为省事把 shell 多文件合并成单文件大 CSS（维护约定）  
6. 调用后台专用类：`DashboardStats`、`GeoCityCoords`、`PanelMonitor` 等  
7. 「先在主题写 SQL 赶进度」——一律禁止；先补 core  
8. 首页 / apis 首屏 `json_encode` 全量 `FrontendApi::listForTheme()`（须走 `core/front/catalog.php`，v13.26.16）  

**分层示意：**

```
主题 pages/*.php / user/pages/*.php     ← 只展示
        ↓ 只调用
Frontend* / SiteContext / ThemeManager / SiteMedia / UserAuth / UserAvatar
ApiQuickstart / Markdown / AjaxResponse / helpers
        ↓（core 内部）
*Manager / Config / Database
        ↓
MySQL / Redis
```

---

### 6.9 新建主题 Checklist

- [ ] `core/theme/{id}/theme.json`（id 与目录一致）  
- [ ] `layout/header.php` + `footer.php`  
- [ ] 公开 `pages/*.php`（至少 home / apis / detail）  
- [ ] `assets/shell/` 齐备；非 default 提供 `theme.css` / `theme.js`  
- [ ] `user/layout.php` + `user/auth/*` + `user/pages/*`  
- [ ] 数据全部来自 §6.3 / §6.4，**无** Manager / SQL  
- [ ] 图标 `SiteMedia`；头像走 Frontend* / `UserAvatar`  
- [ ] 用户内容 `vs_e()`；表单 CSRF  
- [ ] 不引用其它主题与根目录前台资源  
- [ ] 在后台切换主题后，桌面 + 手机各走查一遍  

---

### 6.10 与后台能力对照

| 能力 | 后台类（主题勿用） | 主题类 |
|------|-------------------|--------|
| 接口分类 | `ApiCategoryManager` | `FrontendCategory` |
| 接口审核/上下线 | `ApiManager` | `FrontendApi` + `FrontendStats` |
| 用户管理 | `UserManager` | `UserAuth` + `FrontendUser` |
| 站点配置 | `Config` / 设置页 | `SiteContext` + `ThemeManager::themeSetting*` |
| 文章/公告/关于 | `ContentManager` | `FrontendArticle` / `Announcement` / `About` |
| 友链/伙伴/赞助 | `LinkManager` | `FrontendLink` / `Partner` / `Sponsor` |
| 评论/反馈 | `CommentManager` / `ApiFeedbackManager` | `FrontendComment` / `FrontendFeedback` |
| 贡献者 | （用户+接口聚合） | `FrontendContributor` |
| Markdown | — | `Markdown`（或 Frontend 已渲染 HTML） |

---

### 6.11 最小示例：首页取数

```php
<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$siteName   = SiteContext::siteName();
// 接口目录：勿 listForTheme() 灌首屏；由 VS.fetchFrontCatalog 拉取
$tags       = FrontendCategory::listTags();
$apiCount   = FrontendStats::approvedApiCount();
$totalCalls = FrontendStats::totalCallCount();
$footerLinks = FrontendLink::pickForFooter(8);
$announces  = FrontendAnnouncement::listForTheme();
$heroTitle  = ThemeManager::themeSettingStr('hero_title', '');
$qqIcon     = SiteMedia::imgUrl('QQ.svg');
$showPartners = ThemeManager::themeSettingBool('show_partners', true);
```

前台 JS：`VS.fetchFrontCatalog({ partners: $showPartners })` → 渲染卡片 / 伙伴区。  
**筛选/排序在前端做；数据源必须是 catalog / 上述 Frontend* 返回值。**


## 七、常见问题

**Q：首页可以把 `FrontendApi::listForTheme()` 整表 `json_encode` 进 HTML 吗？**  
A：**不可以（v13.26.16）。** 须 `POST core/front/catalog.php`（`listForCatalog`）+ `VS.fetchFrontCatalog`；见《前端页面渲染与源码规范》。

**Q：同站链接该用 `vs_base_url()` 还是 `vs_site_path()`？**  
A：导航 / CSS/JS / 本站图 / catalog → **`vs_site_path`**（根相对路径）；`og:*` / canonical / 邮件 → **`vs_base_url` / `vs_seo_abs_url`**（绝对 https）。

**Q：用户中心样式乱了 / 和后台搅在一起？**  
A：用户中心必须只加载**当前主题**的 `assets/shell` / `user.css` 等（`userShellCssHrefs`），**不要**再引根目录 `/assets/css/admin.css`。根目录 `admin.css` 仅管理员后台。见《主题资源隔离规范》。

**Q：主题里可以直接写 `/assets/img/xxx.svg` 吗？**  
A：不可以。须 `SiteMedia::imgUrl(...)`（或头像 / 分类等已有核心类）。

**Q：为什么 Network 里会有很多个 CSS/JS 请求？**  
A：v13.22.6 起已取消 HTTP 打包；有几个主题文件就请求几次，便于对照文件名维护。v13.26.5 起默认主题用本地字体 CSS，勿再挂境外 Google Fonts。

**Q：主题里可以直接 `Database::connect()` 吗？**  
A：**禁止。** 请使用 `Frontend*` / `SiteContext` / `ThemeManager` 等；新能力应在 core 新增类后在 bootstrap 注册。完整对接见 **§六**。

**Q：首页统计可以调 `ApiManager::totalCallCount()` 吗？**  
A：**不可以。** 用 `FrontendStats::totalCallCount()` / `approvedApiCount()` / `userCount()` / `todayCallCount()`。

**Q：为什么分 `ApiCategoryManager` 和 `FrontendCategory`？**  
A：前者负责后台 CRUD 与图标；后者负责前台展示规则（all/id 键、可见数量、无接口仍显示）。职责分离，主题不依赖后台实现细节。

**Q：分类下没有接口，标签会消失吗？**  
A：不会。`FrontendCategory::listTags()` 返回全部**已启用**分类。

**Q：新增 core 类后主题用不了？**  
A：检查是否已在 `bootstrap.php` 中 `require_once`。

**Q：文章/友链等前台页没有数据？**  
A：先确认后台已发布且状态为可见；主题须调用对应 `Frontend*`（如 `FrontendArticle` / `FrontendLink`），勿在主题内写 SQL。新业务仍按 **§2.3** 先补 core 再改主题。

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
| **前端源码简洁 / catalog** | `开发规范/前端页面渲染与源码规范.md`（v13.26.16 起；本地维护） |
| 数据库开发 | `开发规范/数据库开发规范.md`（禁止字段下划线；中文 COMMENT；数字状态） |
| 升级策略 | `开发规范/版本升级不兼容旧版.md`（新版不长期兼容旧字段/旧代码） |
| 请求与表单 | `开发规范/请求与表单规范.md`（本地维护） |
| 发版流程 | `开发规范/Gitee推送与发行流程.md`（本地维护） |
| 弹窗规范 | `开发规范/弹窗开发规范.md`（本地维护） |
| 按钮样式 | `开发规范/按钮样式规范.md`（本地维护） |
| 界面提示 / Toast | `开发规范/界面提示规范.md` §8（本地维护） |
| 界面勿泄露实现细节 | `开发规范/界面勿泄露实现细节.md`（禁止把库枚举写到页面） |
| 查询串转路径样式 | `开发规范/查询串转路径样式规范.md`（**一条通用伪静态** `/{页}/{数字ID}`→`/{页}.php?id=`；代理 `/apis/{短码}` 另置顶） |
| 本地/代理调用统计 | `开发规范/本地与代理接口统计机制.md`（`ApiStats` + `apilog`） |
| 代理 JSON 字段改写 | `开发规范/代理JSON字段改写规范.md`（`ProxyJsonRewrite` + `jsonrewrite`） |
| JSONP / 出站响应安全 | `开发规范/JSONP与出站响应安全规范.md`（`JsonpGuard` + `ApiOutboundSanitize`） |
| 主题资源隔离 | `开发规范/主题资源隔离规范.md` |
| 开发易错点 | `开发规范/开发易错点备忘.md` |
| 版本记录 | `update-log.json`、`发行说明/` |

---

**文档维护：** 新增或重构 core 类、**变更主题包资源结构 / 加载方式**时，须同步更新：

1. 本文档文首 **同步至版本号** = 当前 `VS_VERSION`  
2. **§1.1 bootstrap**（若增删 require）  
3. **§三 文件总览**、对应 **§四 详细说明**  
4. **§2.4 当前能力与进度** / **§六 主题对接 API**（新增 Frontend 方法时必须补方法表与字段）  
5. 根目录 `README.md`（目录结构 + 主要能力，写法见《README编写要点》）  
6. `开发规范/主题规范.md` / `主题资源隔离规范.md` / **`前端页面渲染与源码规范.md`**（若涉及主题边界或首屏源码）  

发版检查清单已将「漏更 CORE模块说明」列为文档不合格项。
