# ApiNexus · core 核心模块说明

> **文档位置：** 项目根目录 `CORE模块说明.md`  
> **适用读者：** 主题开发者、二次开发者、维护者  
> **当前版本：** 以 `core/version.php` 中 `VS_VERSION` 为准（本文档同步至 **13.26.3**）

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

**主题资源隔离（13.22.3；加载方式 13.22.6）：** 前台 / 用户中心只加载**当前主题包**内 `assets/shell`、`assets/css`、`assets/js`；根目录 `assets/css|js` **仅**管理员后台与安装等系统页。内置图标物理文件仍在 `assets/img/`，出站须经 `SiteMedia`（或 `UserAvatar` / 分类图标等核心类）。**浏览器按文件逐个请求**（`ThemeManager::frontendShell*Hrefs` / `defaultFrontendAssets` 等清单），**不再**使用 HTTP 打包入口；磁盘源文件保持分立，禁止为维护方便合并成单个大 CSS。Google Fonts 宜 idle 加载。

**默认主题 UI 改动边界：** 详情免责声明开关、快速上手鉴权 Tab、Hero 文案等**仅改** `core/theme/default/`；其它主题须自行对齐 `theme.json` settings，core 不提供跨主题样式回退。

**一句话：** core 负责「数据从哪来、规则是什么」；主题负责「数据怎么展示」。

---

## 1.1 bootstrap 加载顺序（与 `core/bootstrap.php` 一致）

```
version.php
→ helpers.php
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
→ StatDayManager → ApiLogManager → ApiLogArchive → ApiKeyManager
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
→ PlaygroundRelay → ThemeManager
→ oauth/*（HttpClient → OAuthConfig → OAuthState → OAuthService → QQ/Gitee）
→ Session 启动 + CSRF
→（已安装时）DatabaseMigrator::pruneAppliedAboveCodeVersion
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
| 公开 API 接口 | `ApiManager` / `ApiNotify` / `ApiProxy` / `PlaygroundRelay` / `ApiStats` | `FrontendApi` / `FrontendStats` | `admin/api/list.php`、`review.php`、`user/api-manage.php`、`apis.php`、`detail.php` | ✅ 是 | **已完成**（本地/外链、详情 `/detail/{id}`、多选 method、**keyways**、needkey/qpm/charge、审核三态、统计、在线测试浏览器直连、双端 UI） |
| 用户调用密钥 | `ApiKeyManager` | —（统计内校验） | `user/keys.php`、`admin/api/keys.php` | 用户中心/后台 | **已完成**（表 `apikey`；每账号最多 3 个；`sk-`+32；本地/代理校验与计数；页面勿用 `tokens` 命名） |
| 积分与支付 | `PointsManager` / `OrderManager` / `CheckinManager` / `PayConfig` / `CodePayClient` | `FrontendUser`（余额 / 签到） | `admin/finance/*`、`admin/settings`、`user/recharge`、`user/points`、`user/index`、`core/play/codeplay/notify.php` / `return.php` | 用户中心/后台 | **已完成**（充值扣费；v10.4.0 注册赠送 / 每日签到；表 `orders` + `checkin`） |
| 站点信息 | `Config` / `SiteContext` | `SiteContext` | `admin/settings.php` | ✅ 是 | **已完成** |
| 用户认证 | `UserAuth` / `UserManager` | `UserAuth` + `FrontendUser` | `user/`、`admin/users.php` | ✅ 是 | **已完成**（含角色 user/developer） |
| 管理员认证 | `Auth` | — | `admin/` | 后台专用 | **已完成** |
| 第三方登录 | `oauth/*` | `OAuthService` | 系统设置 | ✅ 是 | **已完成** |
| 文章 | `ContentManager`（kind=1） | `FrontendArticle` / `FrontendAbout` | `admin/content/articles.php`、`articles.php`、`about.php` | ✅ 是 | **已完成**（封面；可绑定关于页；隐藏态） |
| 友情链接 | `LinkManager` / `LinkSiteMeta` / `LinkNotify` | `FrontendLink` | `admin/content/links.php`、`links.php`、`applylink.php`、`core/theme/default/api/sitemeta.php` | ✅ 是 | **已完成**（表 `link`；`kind=0`；审核 + 启禁；一键 TDK；邮件通知） |
| 合作伙伴 | `LinkManager`（共用） | `FrontendPartner` | `admin/content/partners.php`、默认主题首页 | ✅ 是 | **已完成**（表 `link`；`kind=1`；无审核；仅编辑/启禁） |
| 赞助 | `LinkManager`（共用） | `FrontendSponsor` | `admin/finance/sponsor.php`、`sponsor.php`、默认主题赞助页、系统设置收款码 | ✅ 是 | **已完成**（表 `link`；`kind=2`；简介=赞助说明；收款码配置） |
| 公告 | `ContentManager`（kind=0） | `FrontendAnnouncement` | `admin/content/announcements.php`、首页弹窗/跑马灯 | ✅ 是 | **已完成**（置顶/弹窗；Markdown；与文章共用表） |
| Markdown | `Markdown`（`core/markdown/`） | 编辑器 + 渲染 | 公告/文章/API 文档编辑 | ✅ 是 | **已完成**（本地 marked/purify/Parsedown；短码扩展） |
| Redis 缓存 | — | `RedisService` / `RedisCache` / `DashboardStats` / `StatDayManager` | `admin/system/redis.php`、`admin/index.php`、`admin/screen.php` | 后台专用 | **业务缓存已接入**（公开接口 / 前台展示 / 分类 / 日志分页 / 今日调用←statday / 控制台 `cache:dashboard:*` + `statday` 日聚合） |
| 贡献者 | `FrontendContributor` | `FrontendContributor` | `contributors.php`、`profile.php`、`core/ping.php` | ✅ 是 | **已完成**（开发者卡片、公开主页、加入时间、壁纸、延迟检测） |
| 主题资源 / 媒体 | `ThemeManager` / `SiteMedia` | （主题 layout 调用） | 各主题 `assets/shell|js|css`（逐文件 link/script） | ✅ 是 | **已完成**（双主题完全隔离；逐文件加载；图标经 SiteMedia） |
| 用户控制台问候 | `UserDashHello` | — | `user/index`（双主题） | 用户中心 | **已完成**（12×2h 槽 + 打字动效） |

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

### 2.8 主题开发者只需记住

1. **读分类** → `FrontendCategory`  
2. **读公开接口** → `FrontendApi`  
3. **读站点名/描述** → `SiteContext::siteName()`（前台）；后台/用户中心壳层用 `SiteContext::systemName()`（或模板 `$systemName`）  
4. **当前登录用户** → `FrontendUser::current()`（推荐）或 `UserAuth::user()`  
5. **是否开发者** → `UserRole::currentCanPublishApi()`
5. **读文章** → `FrontendArticle::listForTheme()` / `findById()`  
6. **读公告** → `FrontendAnnouncement::listForTheme()` / `listPopups()`  
7. **永远不要**在主题里写 SQL 或直接调 `*Manager` 做前台展示  

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
| `SiteContext.php` | 站点名称（前台）、系统名称（后台壳层）、描述、Logo 等展示信息 |
| `RegisterPolicy.php` | 注册邮箱后缀策略 |
| `Mailer.php` | SMTP 发信 |
| `Auth.php` | **管理员**登录与会话 |
| `UserAuth.php` | **用户**登录、注册、重置密码 |
| `UserRole.php` | 用户角色常量与权限判断（普通用户/开发者） |
| `FrontendUser.php` | 前台用户资料调度（用户名、头像、简介、博客、壁纸、角色）；`dashboardStats()` 控制台汇总 |
| `UserDashHello.php` | 用户控制台按时段问候（12 个 2 小时槽；文案池随机；双主题共用） |
| `SiteMedia.php` | 内置图片出站 URL（`assets/img/` 物理文件；主题禁止手写路径） |
| `FrontendContributor.php` | 贡献者列表与公开个人主页（接口数 / 调用量 / 加入时间；`bio_custom` 标记是否自填简介；归属含绑定身份下历史 userid=0） |
| `AuthSecurity.php` | CSRF、限流、Session 安全、邮件票据 |
| `Captcha.php` | 行为验证统一入口（本站图形 / 第三方；分端配置） |
| `RateLimitStore.php` | 限流计数存储（MySQL） |
| `AjaxResponse.php` | 后台 AJAX 统一 JSON 响应 |
| `AdminUserBinding.php` | 管理员绑定用户身份（发布内容用） |
| `UserManager.php` | 后台用户列表/封禁/删除/身份转换 |
| `UserAvatar.php` | 用户头像 URL 解析 |
| `ApiManager.php` | API 接口数据与审核状态（后台 / 用户投稿） |
| `ApiError.php` | 公开 API 业务错误码（11001～11018）；`businessLabelMap` / `aiDetailDocErrcodeClause` 供 AI 详细文档全量写入 |
| `ApiQuickstart.php` | 从 `aidoc` 解析 `:::qs lang=… auth=…` 多语言快速上手（v10.15.0；auth v10.17.0） |
| `AiConfig.php` | 站点 AI 配置（启用/服务商/根地址/密钥/模型/单片超时/代码调度模式与并发） |
| `AiClient.php` | OpenAI 兼容 Chat Completions / Responses；流式 `chatStreamWithConfig`；连通测试须先 `session_write_close`（v13.26.0） |
| `AiChatSession.php` | AI 短时效多轮（Redis TTL 约 10 分钟）；接口保存时 `clearAllForActor` 整批清空 |
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
| `ApiStats.php` | 本地/代理调用统计与守卫；本地须 `hit(接口ID)`；本地出站头 `outboundHeaders` / `outboundUa` / `outboundReferer` |
| `StatDayManager.php` | 控制台日聚合表 `statday` |
| `DashboardStats.php` | 控制台/大屏 KPI·趋势·TOP·live（含 TOP live / 服务器监控快照，**v13.4.0 / v13.16.0**）；geo 飞线三色 |
| `PanelMonitor.php` | 宝塔 / 1Panel 面板监控客户端；控制台「服务器」卡片快照与测试连接（**v13.16.0**） |
| `GeoCityCoords.php` | 大屏飞线全量城市坐标库；`resolveCityName` 地级优先 + 剥离运营商尾缀（v13.0.0 / **v13.2.0**） |
| `ApiKeyManager.php` | 用户 API 调用密钥 CRUD |
| `ApiLogManager.php` | 调用日志分页、今日计数、脱敏 |
| `ApiLogArchive.php` | 调用日志冷热归档 |
| `ContentManager.php` | 文章/公告内容 CRUD（kind 区分） |
| `CommentManager.php` / `FrontendComment.php` | 文章评论后台与前台 |
| `ApiCategoryManager.php` | API 分类 CRUD（**后台向**） |
| `LinkManager.php` | 友情链接 / 合作伙伴 / 赞助共用 CRUD（`kind` 0/1/2；友链审核；前台申请）（**后台向**） |
| `LinkSiteMeta.php` | 抓取外站 HTML 解析 title/description/favicon（友链一键填充；防 SSRF） |
| `LinkNotify.php` | 友链申请通知管理员；通过后通知申请人邮箱 |
| `FrontendCategory.php` | 前台分类标签（**主题向**） |
| `FrontendApi.php` | 前台公开接口列表与详情（**主题向**）；入口 SEO 见 `vs_page_seo_pack`（v10.8.2） |
| `FrontendLink.php` | 前台已通过且启用的友链列表与本站友链卡片（**主题向**） |
| `FrontendPartner.php` | 前台已启用合作伙伴列表（**主题向**） |
| `FrontendSponsor.php` | 前台赞助收款码 + 赞助名单（**主题向**） |
| `FrontendStats.php` | 前台统计：注册用户数、今日调用次数（**主题向**） |
| `RedisCache.php` | 业务数据缓存（前台/公开列表 + apilog + orders + **控制台 dashboard 前缀** + statday topmap）；监控页展示逻辑键名（v10.6.0 / v10.12.0） |
| `ApiLogManager.php` | API 调用日志：默认时间窗、COUNT 无 JOIN、keyset 翻页、热冷合并查询；`countToday` 优先读 `statday`；`detailEnabled()` 控制是否写详细日志；`httpcodeLabel`（v10.6.1）；`maskApikey` 展示/落库脱敏（v10.8.0） |
| `OrderManager.php` | 积分/充值订单：按每页条数 + keyset 翻页（无时间窗、无全表 COUNT）；写入后 `invalidateOrders`；kind 含注册赠送/每日签到；搜索先解析用户/类型再精确过滤 + `kind_class`（v10.6.0）；业务时区东八区（v10.6.1） |
| `PointsManager.php` | 余额读写、扣费、充值完成/取消（回调不比对金额，见支付规范 §2.6）、`giftOnRegister` / `checkin`；列表走 OrderManager |
| `CheckinManager.php` | 每日签到表：同用户同日唯一、横幅状态、失败回滚占位 |
| `ApiLogArchive.php` | 调用日志冷热归档：开关、三层索引、SQLite 分片（条数可配）、计划任务密钥 |
| `RedisService.php` | Redis 连接、监控快照、运行时长格式化（天/时/分/秒）与限流键清理（**后台向**） |
| `ThemeManager.php` | 主题发现、切换、模板渲染、主题内资源 URL；前台/用户中心壳与页 CSS·JS 清单 |
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
| `vs_path_resource_url($script, $id)` | 路径式资源 URL：`/{脚本}/{id}`（通用伪静态） |
| `vs_api_detail_url($apiId)` | 接口详情 URL（→ `/detail/{id}`） |
| `vs_resolve_path_id()` | 入站解析资源数字 ID（GET 优先，兼容 PATH_INFO） |
| `vs_redirect($url)` | HTTP 重定向 |
| `vs_render_seo_meta()` / `vs_seo_defaults()` / `vs_seo_abs_url()` | SEO / OG / 分享 meta 统一输出 |
| `vs_render_head()` / `vs_render_foot()` | 输出 HTML 头尾（head 支持 `$seoOpts` 页面级覆盖） |
| `vs_frontend_page($pageKey, $title)` | **前台页面统一入口**（自动选主题、加载 CSS/JS） |
| `vs_render_404_page()` | **全站 404 页**（根目录 `404.php` / Apache ErrorDocument；含安全法律提示；乱路径由 Nginx 默认页处理，不强制伪静态指到本页） |
| `vs_render_notice()` | 后台提示块 |
| `vs_render_site_logo()` | 站点 Logo |
| `vs_require_secure_post()` | 校验 POST + CSRF |
| `vs_decode_transport_field()` / `vs_decode_transport_fields()` | 解码 `VS64B:`/`VS64:` Base64 表单字段（防 WAF 误拦，v10.15.3） |
| `vs_api_error_exit($errcode, $msg)` | 守卫/代理统一错误 JSON：`{ code:0, msg, errcode }`，传输 HTTP 固定 200（v11.0.0；见 `ApiError`） |
| `vs_safe_embed_url()` / `vs_safe_css_color()` | Markdown 短码外链/色值白名单（防 XSS，v10.15.3 复查） |
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
| `configureSessionCookies()` / `sessionCookieSecure()` | Session Cookie；`Secure` 跟随 `isHttps()`（对齐 v5.1.1）；禁止默认 false、禁止登录/每请求清 Cookie |
| `clearSessionCookie()` | 退出时同时清除 Secure / 非 Secure 会话 Cookie |
| `csrfToken()` / `rotateCsrfToken()` | 获取 / 轮换 CSRF |
| `validateCsrf($token)` | 校验 CSRF |
| `requireAuthPost()` | POST 必须带合法 CSRF；失败 JSON 含新 `csrf` |
| `sendSecurityHeaders()` | 认证页 `no-store` + CDN 禁缓存 |
| `checkLoginAllowed($username)` | 登录是否被限流 |
| `recordLoginFailure($username)` | 记录登录失败 |
| `checkMailCodeAllowed($email)` | 发验证码是否允许 |
| `issueMailTicket()` / `validateAndConsumeMailTicket()` | 邮件验证码票据 |

**表单/AJAX 示例：**

```php
AuthSecurity::requireAuthPost();
// 前端：assets/js/auth-csrf.js → VsAuthCsrf.postForm() 凭证失败自动重试一次
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

**KEY 上下文：** `vs_playground_session_context()` → `apiKey` / `loggedIn` / `apiKeyCount`（勿再依赖中继 CSRF）。

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

### 4.21.5 AiApiDoc.php / ApiQuickstart.php（AI 文档与快速上手）

**AiApiDoc：** 管理员/用户接口编辑「AI 生成详细文档 / 代码示例」；**v13.26.2+** 详细文档默认按 7 章 `generateDetailDocSectionStream` 顺序回填；**v13.26.3** intro 须 `# 接口名` + 短概述（`sanitizeApiTitleName` + `ensureIntroDocTitle` 后再消毒），examples 须 curl+非空 PHP；前端章节失败自动重试 1 次。用户端**仅使用平台 AI**（无自建模型配置）。上下文剔除 `targeturl`/`upkey`/`jsonrewrite`；输出经 `ApiQuickstart::scrubHighlightLeak`；代码片 `wrapQsBlock` 二次 scrub（E232）。
**v13.26.1 文档约束：** 详细文档调用示例仅 **curl + PHP**；密钥传递只描述**首选一种**鉴权；章节顺序强制（参数→响应→错误码→调用示例→注意事项）；文末必有「注意事项」。
**展示：** `VsSyntax` bash 纯文本分词；复制用 `data-vs-plain` / `plainText`。

**代码示例生成（v12.0.0 / v13.26.2 / v13.26.3）：** 前端按片调用 `ai_gen_code_piece_stream`（SSE，`delta` 可预览）；**AI 生成最多 3 鉴权 × 9 语言 = 27 片**。主按钮一键全量；可按鉴权单独生成 9 片（按钮文案「生成 Query / Header / Bearer」，**同行横排**，合并保留其它鉴权块）。用户开发者入口标签/hint/校验切 Tab/总用时与管理端对齐。`AiConfig::codeMode` 为 `sequential`/`parallel`（并发 1～6，CDN 建议 ≤2）。提示词要求**纯代码**；服务端 `finalizeCodePieceBody` 包裹 `:::qs`（兼容旧 :::qs / fence / JSON），禁 emoji、要求中文注释，失败可重试 1 次（重试仍流式）。旧整包接口仅兼容保留。**纠正：** v13.26.0 误把「文档内仅 curl+PHP」套用到 aidoc 并降为 2 片，已在 13.26.1 恢复。

**默认主题快速上手图标（v12.0.1）：** `assets/img/lang/*.svg`；`detailQsBundle.byAuth[*]` 宜带 `icon_gray`/`icon_color`；另须注入 `window.detailQsLangIcons`（`ApiQuickstart::langIconMap`）。`detail-quickstart.js` 重绘 Tab 时按语言 id 兜底补图标；首屏已有 PHP 图标则勿无谓重绘。切换 Query/Header/Bearer **不得**丢九种语言图标。

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
| `auth` | **必填**（v10.17.0）：`query` / `header` / `bearer`；缺省按 query |
| 多鉴权 | 接口 `keyways` 多种时，每种 auth 各一套语言块 |

**ApiQuickstart：** `samplesFromAidoc($aidoc, $keyways)` / `qsBundleFromAidoc` 解析短码；默认主题详情页横滑语言 Tab + 鉴权 Tab（`auth=…` 维度）。

**后台编辑（v11.1.0）：** 接口列表详细文档 / 代码示例 textarea 使用 `data-vs-md="off"`，**无右侧实时预览**（仍保留工具栏）。

**详细文档要求（v11.0.0+）：** 错误响应示例须含 `"errcode":11001` 等业务码；传输层 HTTP 固定 200。鉴权方式错误为 `11012`。禁止再写 `"http":401`。

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
| `listForTheme()` | 接口数组；Redis 缓存 `call_path`，取出后按当前访问域名重绑 `endpoint` / `detail_url`（多域名备用入口不串域） |
| `findForThemeById($id)` | 单条详情（审核通过且非禁用）；同样按当前域名重绑 |
| `bindRequestHost` / `bindRequestHostToList` | 将 `call_path` 拼到当前 `vs_base_url()` |
| `countForTheme()` | 公开接口数量 |

**返回字段（每条）：**

| 字段 | 说明 |
|------|------|
| `id` | 接口 ID |
| `name` | 名称 |
| `desc` | 描述 |
| `category` / `category_name` | 分类 id / 原始分类名 |
| `method` / `methods` / `method_label` | 请求方式 |
| `endpoint` | 调用地址（**当前访问域名**动态拼装；外链绝对地址不改） |
| `call_path` | 路径或外链绝对地址（Redis 缓存依赖此字段，勿把域名烤进缓存语义） |
| `params` / `response` / `doc` / `aidoc` | 参数原文、返回、详细文档、代码示例 |
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

- 列表页 `pages/links.php` → `FrontendLink::listForTheme()`
- 首页合作伙伴区 → `FrontendPartner::listForTheme()`（勿写死外链）
- 赞助页 `pages/sponsor.php` → `FrontendSponsor::paymentQrs()` + `listForTheme()`（默认主题：单码切换 + 桌面左右布局 +「感谢支持」+ 赞助卡片多列网格；**禁止**「其它支持方式」；主题二后续对齐）
- 申请页 `pages/applylink.php` + 根入口 `applylink.php`（短名无横线）
- 页脚在二维码上方渲染已通过且启用的友链，末尾固定「申请友链」链到 `/applylink`
- 禁止主题内 SQL；申请提交走 `applylink.php` POST + CSRF + `AjaxResponse`
- 后台：`admin/content/links.php`、`admin/content/partners.php`、`admin/finance/sponsor.php`；操作须 AJAX 局部更新，禁止整页刷新（E61）
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

### 4.24c SiteMedia.php（内置图片出站）

**作用：** 站点内置图片（分类图标、语言图标、头像素材、支付 / 备案图标等）统一解析为出站 URL。物理文件仍在根目录 `assets/img/`。

| 方法 | 说明 |
|------|------|
| `imgUrl($relative)` | 相对 `assets/img/` → 完整 URL；文件不存在返回空串 |
| `imgWebPath($relative)` | 站内路径 `/assets/img/...`（可不强制存在） |
| `resolve(...)` | 解析入库或前端传来的路径/URL，防穿越 |

**主题约定：** 模板里写 `SiteMedia::imgUrl('QQ.svg')`、`SiteMedia::imgUrl('lang/php.svg')` 等；**禁止**手写 `/assets/img/...`。

---

### 4.24d （已移除）主题资源 HTTP 打包

**v13.22.6：** 删除 `ThemeAssetPack.php` 与 `theme-asset.php`。前台 / 用户中心改回**逐文件** `<link>` / `<script>`（清单见 `ThemeManager`）。在线升级时由 `install/obsolete-files.json` 清理旧站残留。Google Fonts 仍建议 idle，勿阻塞首屏。

---

### 4.24e UserDashHello.php（用户控制台问候）

**作用：** 用户中心控制台按时段问候。12 个 2 小时槽（0–1 … 22–23）；每槽多条 hello/hint，每次随机；双主题共用文案池。**4–5 点属「凌晨」槽，不写「早上好」。**

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
| `renderBody($pageKey, $title, $data)` | 渲染 layout + pages |
| `themeSetting($key, $default)` | 读当前主题 settings |
| `assetUrl($themeId, $relative)` | 主题静态资源 URL |
| `shellUrl($file)` | 当前主题 `assets/shell/` 下单文件 URL |
| `pageScriptUrl($file)` | 当前主题页脚本 URL（如 `vs-syntax.js`） |
| `frontendShellCssHrefs` / `frontendShellJsHrefs` | 前台壳 CSS/JS 逐文件列表 |
| `userShellCssHrefs` / `userShellJsHrefs` | 用户中心壳 CSS/JS 逐文件列表 |
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

### 4.28 UpdateLog.php

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
| 站点配置 | 后台设置页 → `Config` | `SiteContext::siteName()` / `systemName()` / `ThemeManager::themeSetting()` | ✅ 可调用 |
| 文章 | `ContentManager`（kind=1） | `FrontendArticle` | ✅ 已完成 |
| 友链 | `LinkManager` | `FrontendLink` | ✅ 可调用（已通过且启用） |
| 合作伙伴 | `LinkManager` | `FrontendPartner` | ✅ 可调用（已启用） |
| 赞助 | `LinkManager` | `FrontendSponsor` | ✅ 可调用（收款码 + 已启用名单） |
| 公告 | `ContentManager`（kind=0） | `FrontendAnnouncement` | ✅ 已完成 |
| Markdown | `Markdown` | 编辑器资源 `core/markdown/assets` | ✅ |

---

## 七、常见问题

**Q：用户中心样式乱了 / 和后台搅在一起？**  
A：用户中心必须只加载**当前主题**的 `assets/shell` / `user.css` 等（`userShellCssHrefs`），**不要**再引根目录 `/assets/css/admin.css`。根目录 `admin.css` 仅管理员后台。见《主题资源隔离规范》。

**Q：主题里可以直接写 `/assets/img/xxx.svg` 吗？**  
A：不可以。须 `SiteMedia::imgUrl(...)`（或头像 / 分类等已有核心类）。

**Q：为什么 Network 里会有很多个 CSS/JS 请求？**  
A：v13.22.6 起已取消 HTTP 打包；有几个主题文件就请求几次，便于对照文件名维护。Google Fonts 仍 idle，不挡首屏。

**Q：主题里可以直接 `Database::connect()` 吗？**  
A：不推荐。请使用 `FrontendCategory`、`FrontendApi` 等已封装类；新能力应在 core 新增类后在 bootstrap 注册。

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
4. **§2.4 当前能力与进度** / **§6.5**（若影响能力表）  
5. 根目录 `README.md`（目录结构 + 主要能力，写法见《README编写要点》）  
6. `开发规范/主题规范.md` / `主题资源隔离规范.md`（若涉及主题边界）  

发版检查清单已将「漏更 CORE模块说明」列为文档不合格项。
