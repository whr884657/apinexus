# ApiNexus · 核心 PHP（API）与主题包对接说明

> **文档位置：** 项目根目录 `CORE模块说明.md`  
> **文档性质：** 主题开发对接文档（按文件 / 能力分段，**不按版本号分章**）  
> **当前版本：** **13.26.44**（与 `core/version.php` 中 `VS_VERSION` 一致）  
> **适用读者：** 自研主题、二次开发、维护者  

**铁律（全文最重要的一句）：**  
主题包 **禁止直连数据库**（禁止 `Database::`、禁止 SQL、禁止表名/字段名）。主题只调用 `core/` 已提供的 **Frontend\*** / 入口注入变量 / 约定 HTTP 窗口；缺能力时先在 core 补 API，再改主题。

---

## 目录

1. [怎么理解 core（饭店窗口）](#一怎么理解-core饭店窗口)
2. [快速开始](#二快速开始)
3. [总目录表（core 下全部 PHP，不含 theme）](#三总目录表core-下全部-php不含-theme)
4. [分册 A · 基础设施](#四分册-a--基础设施)
5. [分册 B · 认证与注册](#五分册-b--认证与注册)
6. [分册 C · Frontend\* 主题主 API](#六分册-c--frontend-主题主-api)
7. [分册 D · Manager 等后台业务类](#七分册-d--manager-等后台业务类)
8. [分册 E · 支付 / 邮件 / 验证码](#八分册-e--支付--邮件--验证码)
9. [分册 F · 升级与迁移](#九分册-f--升级与迁移)
10. [分册 G · 子目录 HTTP 入口](#十分册-g--子目录-http-入口)
11. [主题对接 Checklist](#十一主题对接-checklist)
12. [相关文档](#十二相关文档)
13. [附录 H · 逐文件 API 卡片](#附录-h--逐文件-api-卡片core不含-theme)

---

## 一、怎么理解 core（饭店窗口）

把整站想成一家饭店：

| 角色 | 对应什么 | 白话 |
|------|----------|------|
| **后厨** | `core/*.php` 里的类（尤其 `Frontend*`、各种 `*Manager`） | 真正查库、算规则、整理字段的地方 |
| **取餐窗口** | `core/front/catalog.php`、`playground-key.php` 等 **HTTP 入口** | 浏览器能访问的网址；验票后喊后厨，把 JSON 递给前端 |
| **服务员 / 装修** | `core/theme/{主题id}/` | **只负责摆盘与好看**；不自己进后厨翻冰箱（不直连库） |
| **菜单规矩** | `RegisterPolicy`、`Config`、审核状态等 | 开不开注册、能不能展示某条接口，规则只写在 core |

**核心 PHP = API**（数据从哪来、规则是什么）。  
**主题包 = 对接客户端**（HTML/CSS/JS + 调用 API 展示）。

再记三句，后面读文档就不会混：

1. **主题 PHP** 调 **类**（`FrontendApi::findForThemeById` 等）。  
2. **主题 JS**（首页/apis 列表）调 **窗口网址**（`POST catalog.php` / `VS.fetchFrontCatalog`）。  
3. 窗口内部也会调后厨类——所以「好像都能拿到接口」，其实是同一套规则，不是两套库。

---

## 二、快速开始

### 2.1 入口怎么接上 core

任何入口页（根目录 `index.php`、`user/`、`admin/` 等）先：

```php
define('VS_ROOT', __DIR__); // 或 dirname(__DIR__) 视入口位置而定
require_once VS_ROOT . '/core/bootstrap.php';
```

`bootstrap.php` 会按固定顺序加载全部核心类，并启动 Session、CSRF。主题页 **不要** 自己再 `require` 一堆 core 文件（已由引导加载）。

### 2.2 主题「只调什么」一页速查

| 你要做什么 | 调用（唯一推荐） | 禁止 |
|------------|------------------|------|
| 读公开接口**目录**（首页 / apis） | `POST core/front/catalog.php` 或 `VS.fetchFrontCatalog(...)` | 首屏 `json_encode(FrontendApi::listForTheme())`；`ApiManager::*` |
| 读公开接口**单条详情** | 入口注入 `$api`，或 `FrontendApi::findForThemeById($id)` | 详情再 POST 全站 catalog；`ApiManager::*` |
| 分类标签 | `FrontendCategory::listTags()` / `nameMap()` | `ApiCategoryManager::*` |
| 首页 KPI | `FrontendStats::*` | 主题内 COUNT SQL |
| 友链 / 页脚 | `FrontendLink::*` | `LinkManager::*` |
| 合作伙伴 | 首页：`catalog` 带 `partners=1`；其它页：`FrontendPartner::listForTheme()` | 首屏灌伙伴大包 / SQL |
| 赞助 | `FrontendSponsor::paymentQrs()` + `listForTheme()` | 手写收款码路径 |
| 文章 / 公告 / 关于 | `FrontendArticle` / `FrontendAnnouncement` / `FrontendAbout` | `ContentManager::*` |
| 贡献者 | `FrontendContributor::*` | 拼用户表 SQL |
| 当前用户展示 | `FrontendUser::current()` | 主题直读 Session 拼装 |
| 是否登录 | `UserAuth::check()` / `requireLogin()` | 自造 session key |
| 站点名 / Logo / 备案 | `SiteContext::*` | 主题直接拆 `Config::get` 当展示 |
| 主题配置项 | `ThemeManager::themeSetting*()` | 读别的主题 settings |
| 内置图标 URL | `SiteMedia::imgUrl('xxx.svg')` | 手写 `/assets/img/...` |
| 详情快速上手 | `ApiQuickstart::qsBundleFromAidoc(...)` | 主题内自解析 aidoc |
| Markdown | 优先 Frontend* 已给的 `body_html`；必要时 `Markdown::render()` | 主题自带 MD 引擎 |
| 评论 / 反馈提交 | `FrontendComment::submit` / `FrontendFeedback::submit` | 主题 INSERT |
| AJAX JSON | `AjaxResponse::success` / `error` + `vs_require_secure_post()` | 自造协议 |
| 壳层 CSS/JS | `ThemeManager::shellUrl` / `frontendShell*Hrefs` / `assetUrl` | 引用根目录前台 `assets/css\|js` 或其它主题 |

### 2.3 公开页如何进到主题模板

根目录入口在 `bootstrap` 之后调用：

```php
vs_frontend_page($pageKey, $pageTitle, $pageData);
```

| 入口脚本 | `$pageKey` | 主题文件 |
|----------|------------|----------|
| `index.php` | `home` | `pages/home.php` |
| `apis.php` | `apis` | `pages/apis.php` |
| `detail.php` | `detail` | `pages/detail.php`（常含 `api` / `notFound` / `playground`） |
| `articles.php` | `articles` | `pages/articles.php` |
| `about.php` | `about` | `pages/about.php` |
| `links.php` | `links` | `pages/links.php` |
| `applylink.php` | `applylink` | `pages/applylink.php` |
| `sponsor.php` | `sponsor` | `pages/sponsor.php` |
| `contributors.php` | `contributors` | `pages/contributors.php` |
| `profile.php` | `profile` | `pages/profile.php` |

管道简述：

```
入口.php → vs_frontend_page()
  → SEO / 壳 CSS·JS 清单
  → ThemeManager::renderBody($pageKey, ...)
       → layout/header.php
       → pages/{pageKey}.php
       → layout/footer.php
```

模板首行必须：

```php
<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
```

**用户中心：** `user/init.php` → `UserAuth::requireLogin()` → `vs_user_render_page` → `user/pages/{pageKey}.php`。  
**登录/注册/找回：** `ThemeManager::renderAuthPage` → `user/auth/{pageKey}.php`。

### 2.4 主题包目录（必须 / 推荐）

```text
core/theme/{id}/
  theme.json                 ← 必须（id 与目录名一致）
  主题规范.md                ← 本主题自己的界面规范（自定义这个包时先读）
  preview.png                ← 推荐
  layout/header.php          ← 前台必须
  layout/footer.php          ← 强烈推荐
  pages/                     ← home / apis / detail / …
  assets/shell/              ← 样式与界面脚本（modal / vs-pick 等）。不要放 common.js
  assets/theme.css|js        ← 非 default 主资源
  user/layout.php
  user/auth/{login,register,forgot,bind}.php
  user/pages/...
  api/                       ← 可选主题 AJAX
```

### 2.5 绝对禁止（主题）

1. `Database::connect()` / 任何 SQL / 表名 / 字段名出现在主题  
2. 用 `*Manager` **渲染或取展示数据**（统计请走 `FrontendStats`）  
3. 手写 `/assets/img/...`；把根目录 `/assets/css|js` 当主题皮肤来引（**例外**：系统固定加载 `assets/js/common.js`，认证页 `auth-csrf.js`，验证码 `captcha.js`）  
4. `include` / `assetUrl` 指向**其它主题**  
5. 调用后台专用类：`DashboardStats`、`GeoCityCoords`、`PanelMonitor` 等  
6. 首页 / apis 首屏 `json_encode` 全量 `FrontendApi::listForTheme()`（必须走 catalog 窗口）  

分层示意：

```
主题 pages / user/pages     ← 只展示
        ↓ 只调用
Frontend* / SiteContext / ThemeManager / SiteMedia / UserAuth / UserAvatar
ApiQuickstart / Markdown / AjaxResponse / helpers / RegisterPolicy（读开关）
        ↓（core 内部）
*Manager / Config / Database
        ↓
MySQL / Redis
```

---

## 三、总目录表（core 下全部 PHP，不含 theme）

> 一句话索引。详细方法见后文分册。标 **★** 的是主题日常最常用；标 **HTTP** 的是独立网址入口（不在 bootstrap 类清单里当「可 new 的类」用）。

### 3.1 根目录类与引导

| 文件 | 一句话 |
|------|--------|
| `bootstrap.php` | 系统引导：按序加载全部核心类 + Session/CSRF；末尾可注册全局哀悼输出缓冲（v13.26.43） |
| `version.php` | 定义 `VS_VERSION`（当前 **13.26.44**） |
| `helpers.php` | 全局函数：转义、路径、SEO、前台渲染、`vs_require_secure_post`、全局哀悼注入等 |
| `InstallChecker.php` | 是否已安装；未安装跳转安装向导 |
| `Database.php` | PDO 连接、表前缀 |
| `DatabaseInstaller.php` | 安装时执行 `install/database.sql` |
| `DatabaseMigrator.php` | 版本迁移 SQL、清理残留 |
| `SchemaFullAligner.php` | 对照 `database.sql` 全量结构对齐（只补不删） |
| `Config.php` | `vs_config` 键值读写 |
| `SiteContext.php` ★ | 站点名 / Logo / 页脚 / 备案等**展示**信息 |
| `RegisterPolicy.php` ★ | 注册总闸、身份子开关、邮箱验证、后缀白名单 |
| `Mailer.php` | SMTP 发信 |
| `RedisService.php` | Redis 连接与后台监控快照 |
| `RedisCache.php` | 业务缓存（列表、日志、控制台等） |
| `RateLimitStore.php` | 限流计数（MySQL） |
| `AjaxResponse.php` ★ | 统一 JSON：`{code,msg,...}` |
| `Auth.php` | **管理员**登录会话 |
| `UserAuth.php` ★ | **用户**登录/注册/重置；主题用 `check`/`requireLogin` |
| `UserRole.php` | `user` / `developer` 角色常量与归一化 |
| `AuthSecurity.php` | CSRF、限流、Session、邮件票据、安全头 |
| `Captcha.php` | 行为验证门面（local / gt3 / gt4） |
| `FrontendUser.php` ★ | 当前用户资料、签到、控制台 KPI、本人日志 |
| `UserDashHello.php` ★ | 用户控制台按时段问候文案 |
| `UserAvatar.php` ★ | 头像 URL 解析 / 默认头像 |
| `UserManager.php` | 后台用户列表/封禁/角色 |
| `AdminUserBinding.php` | 管理员绑定用户身份（发帖身份） |
| `SiteMedia.php` ★ | 内置 `assets/img` 出站 URL（主题禁止手写路径） |
| `ThemeManager.php` ★ | 主题发现、settings、壳资源、渲染 |
| `Sitemap.php` | `/sitemap.xml` 站点地图 |
| `SystemInfo.php` | 关于页环境信息 |
| `AboutCatalog.php` | 关于页技术栈/链接目录（本地 JSON 优先） |
| `Updater.php` | 云端在线更新检测与安装 |
| `UpdateLog.php` | 读 `update-log.json` |
| `AdminNotify.php` | 管理端顶栏待办铃铛 |
| `ping.php` **HTTP** | 贡献者/卡片延迟检测（IP 频控） |

### 3.2 Frontend\*（主题主 API）

| 文件 | 一句话 |
|------|--------|
| `FrontendCategory.php` ★ | 分类标签 / 名称映射 |
| `FrontendApi.php` ★ | 公开接口列表/详情/目录瘦身（后厨） |
| `OpenApiBuilder.php` | 由 `params` 派生 OpenAPI 3.1.1（仅详情/预览，不入库；直访 403） |
| `FrontendStats.php` ★ | 四个首页 KPI |
| `FrontendLink.php` ★ | 友链列表 / 页脚 / 本站卡片 |
| `FrontendPartner.php` ★ | 合作伙伴 |
| `FrontendSponsor.php` ★ | 赞助名单 + 收款码 |
| `FrontendArticle.php` ★ | 文章列表/详情（含 `body_html`） |
| `FrontendAnnouncement.php` ★ | 公告 / 弹窗公告 |
| `FrontendAbout.php` ★ | 关于页绑定文章 |
| `FrontendComment.php` ★ | 评论列表与提交 |
| `FrontendFeedback.php` ★ | 接口反馈提交 |
| `FrontendContributor.php` ★ | 贡献者卡片与个人主页 |

### 3.3 接口 / 代理 / 统计 / 密钥

| 文件 | 一句话 |
|------|--------|
| `ApiManager.php` | 接口 CRUD、审核（**后台/投稿**；主题勿用取数） |
| `ApiCategoryManager.php` | 分类 CRUD（后台） |
| `ApiError.php` | 业务错误码 11001～11024 文案 |
| `ApiQuickstart.php` ★ | 详情页从 aidoc 解析 `:::qs` 快速上手 |
| `ApiNotify.php` | 投稿/审核邮件 |
| `ApiProxy.php` | 外链网关转发上游 |
| `ApiStats.php` | 调用守卫、记账、出站头、扣费 |
| `ApiKeyManager.php` | 用户密钥 CRUD / 配额 |
| `ApiLogManager.php` | 调用日志查询（热冷合并） |
| `ApiLogArchive.php` | 日志冷归档（SQLite 分片） |
| `ApiFeedbackManager.php` | 反馈后台处理 |
| `FeedbackNotify.php` | 反馈邮件 |
| `PlaygroundRelay.php` | 在线测试同源中继（类） |
| `ProxyClientProfile.php` | 出站 UA/Referer 预设 |
| `ProxyJsonRewrite.php` | 代理 JSON 字段改写 |
| `JsonpGuard.php` | JSONP 回调白名单 |
| `ApiOutboundSanitize.php` | 出站 JSON 消毒 |
| `IpLocator.php` | IP 归属地 |
| `StatDayManager.php` | 日聚合 `statday` |
| `UserStat7Manager.php` | 用户近 7 日聚合 |
| `UserCallStats.php` | 个人调用统计只读查询 |
| `UserIpAllow.php` | 用户 IP 白名单 |
| `UserIpProxy.php` | 用户自备出口代理 |
| `DashboardStats.php` | 管理端控制台/大屏 KPI（主题禁调） |
| `PanelMonitor.php` | 宝塔/1Panel 监控（主题禁调） |
| `GeoCityCoords.php` | 大屏飞线城市坐标（主题禁调） |

### 3.4 内容 / 友链 / 评论

| 文件 | 一句话 |
|------|--------|
| `ContentManager.php` | 文章/公告 CRUD（kind 区分） |
| `CommentManager.php` | 评论后台 |
| `CommentNotify.php` | 评论邮件 |
| `LinkManager.php` | 友链/伙伴/赞助共用 CRUD（kind 0/1/2） |
| `LinkSiteMeta.php` | 抓外站 TDK/favicon（防 SSRF） |
| `LinkNotify.php` | 友链申请/通过邮件 |

### 3.5 积分 / 支付 / 签到

| 文件 | 一句话 |
|------|--------|
| `PayConfig.php` | 码支付与充值套餐配置 |
| `OrderManager.php` | 积分/充值订单 |
| `PointsManager.php` | 余额、扣费、充值履约、注册赠送、签到 |
| `PayPendingWatch.php` | 待支付超时自动取消 |
| `PointsNotify.php` | 积分相关邮件 |
| `CheckinManager.php` | 每日签到表 |
| `CardKeyManager.php` | 积分卡密生成 / 列表 / 兑换（防并发）/ 按码查询与作废 |
| `SystemApiKey.php` | 系统密钥（归档、卡密对接 API 等） |

### 3.6 AI 文档

| 文件 | 一句话 |
|------|--------|
| `AiConfig.php` | 站点 AI 配置（仅后台） |
| `AiClient.php` | OpenAI 兼容客户端 |
| `AiChatSession.php` | 短时效多轮（Redis TTL） |
| `AiSse.php` | SSE 流式输出 |
| `AiApiDoc.php` | 按章生成详细文档与代码示例 |

### 3.7 子目录

| 路径 | 一句话 |
|------|--------|
| `front/catalog.php` **HTTP** ★ | 公开接口目录「取餐窗口」 |
| `front/playground-key.php` **HTTP** ★ | 登录按需取 Playground KEY（禁 SSR） |
| `captcha/*` | 本地图 / 极验实现 + `image.php` / `register.php` HTTP |
| `oauth/*` | QQ / Gitee OAuth 类（入口在 `user/oauth/`） |
| `markdown/Markdown.php` ★ | Markdown 渲染门面 |
| `markdown/Parsedown.php` | Parsedown 引擎 |
| `play/codeplay/*` | 码支付客户端 + `notify.php` / `return.php` |
| `playground/relay.php` **HTTP** | 在线测试中继入口 |
| `playground/media.php` **HTTP** | 测试媒体短时预览 |
| `api/apilogarchive.php` **HTTP** | 日志清理（归档/删除；系统密钥 + 开关） |
| `api/cardkey.php` **HTTP** | 卡密对接 API（系统密钥；generate/stock/take/void/query） |

---

## 四、分册 A · 基础设施

每节固定三块：**干什么** / **主题怎么用** / **禁止什么**。

### 4.1 `bootstrap.php`

| | |
|--|--|
| **干什么** | 按固定顺序 `require` 全部核心类；设时区 `Asia/Shanghai`；启动 Session 与 CSRF；已安装时做迁移清理。 |
| **主题怎么用** | 入口已 `require` 即可；主题模板内直接调用类名，无需再加载。 |
| **禁止什么** | 主题内再复制一套引导；打乱加载顺序自行拼装。 |

加载顺序（与源码一致，便于排查「类不存在」）：

```
version → helpers → 时区
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
→ ApiNotify → ProxyClientProfile → ProxyJsonRewrite → JsonpGuard
→ ApiOutboundSanitize → ApiProxy → ApiStats → IpLocator
→ StatDayManager → UserStat7Manager → UserCallStats
→ ApiLogManager → ApiLogArchive → ApiKeyManager
→ ApiFeedbackManager → FrontendFeedback → FeedbackNotify
→ ApiCategoryManager
→ PayConfig → OrderManager → PointsManager → PayPendingWatch
→ PointsNotify → UserIpAllow → UserIpProxy
→ CodePayClient
→ FrontendCategory → FrontendApi → FrontendStats
→ GeoCityCoords → DashboardStats → PanelMonitor
→ LinkManager → LinkSiteMeta → LinkNotify
→ FrontendLink → FrontendPartner → FrontendSponsor → FrontendContributor
→ ContentManager → CommentManager → AdminNotify → CommentNotify → FrontendComment
→ CheckinManager → CardKeyManager → Markdown
→ FrontendAnnouncement → FrontendArticle → FrontendAbout
→ PlaygroundRelay → ThemeManager → Sitemap
→ oauth/*
→ Session + CSRF
→（已安装）DatabaseMigrator::pruneAppliedAboveCodeVersion
```

### 4.2 `version.php`

| | |
|--|--|
| **干什么** | 定义常量 `VS_VERSION`（云端更新比对、关于页展示）。 |
| **主题怎么用** | 一般只读展示；勿在主题里「伪造版本」。 |
| **禁止什么** | 主题内 `define` 覆盖版本号。 |

### 4.3 `helpers.php`（主题常用函数）

| | |
|--|--|
| **干什么** | 转义、同站路径、前台渲染管道、SEO、页脚、安全 POST、Playground 上下文等。 |
| **主题怎么用** | 见下表；用户内容一律 `vs_e()`。 |
| **禁止什么** | 自造转义；用 `$vsBase` 当绝对域名做 `parse_url(..., PHP_URL_HOST)`（它是**路径前缀**）。 |

| 函数 | 用途 |
|------|------|
| `vs_e($v)` | HTML 转义 |
| `vs_curl_close($ch)` | 关闭 curl：PHP 7.4 调用 `curl_close`；PHP 8.0+ 不调用（避免 8.5 弃用警告） |
| `vs_base_url()` | 站点根**绝对** URL（SEO / 邮件） |
| `vs_site_base_path()` / `vs_site_path($path)` | 同站路径前缀 / 根相对路径（导航、资源、catalog） |
| `vs_api_detail_url($id)` / `vs_profile_url($id)` | 详情 / 个人主页链接 |
| `vs_frontend_page(...)` | 公开页入口（根脚本用） |
| `vs_user_render_page` | 用户中心入口（在 `user/includes`） |
| `vs_require_secure_post()` | AJAX：同源 + CSRF |
| `vs_playground_session_context()` | 详情调试条上下文（**无**明文 KEY） |
| `vs_page_seo_pack` / `vs_render_theme_seo_block` | SEO |
| `vs_render_footer_custom_bar` / `vs_render_footer_qrs` | 页脚 |
| `vs_copyright_html` / `vs_site_runtime_start` | 版权 / 运行时长 |
| `vs_is_allowed_http_url` / `vs_safe_embed_url` | URL 安全 |
| `vs_console_brand_script()` | 挂载全站控制台品牌外链 JS |
| `vs_site_mourning_on()` / `vs_mourning_boot()` | 全局哀悼是否开启；在 bootstrap 注册输出缓冲（主题无关，E335） |

> **全局哀悼（v13.26.43）：** 主题**不要**自己写灰白 CSS。后台「展示与自定义」勾选后，核心自动给任意主题 HTML 注入 `html.vs-mourning`。

页脚 / `vs_render_foot` 会注入浏览器全局变量，主题壳脚本依赖：

- `VS_CSRF_TOKEN`
- `VS_FRONT_CATALOG`（指向 `/core/front/catalog.php`，含子目录前缀）

**验证码挂载不在本文件：** `vs_captcha_field` / `vs_captcha_js` 在 `core/captcha/helper.php`。

### 4.4 `Config.php` / `SiteContext.php`

| | |
|--|--|
| **干什么** | `Config` 读写系统键；`SiteContext` 把展示相关键整理成访问器。 |
| **主题怎么用** | **展示一律 `SiteContext::*`**（见下）。不要在主题里记一堆 config 键名。 |
| **禁止什么** | 主题 `Config::set`；把后台内部键当 UI 文案源。 |

`SiteContext` 常用方法：

`siteName()`、`systemName()`、`navName()`、`copyrightName()`、`copyrightUrl()`、  
`siteDescription()`、`siteKeywords()`、`siteFavicon()`、`siteLogo()`、`siteRuntimeStart()`、  
`footerHtmlLeft/Center/Right()`、`footerQr1/2{Enabled,Name,Url}()`、  
`icpLink()`、`gonganLink($number)`、`beianInfo()`。

### 4.5 `Database*` / `InstallChecker` / `SchemaFullAligner`

| | |
|--|--|
| **干什么** | 安装检测、PDO、装库、迁移、全量结构对齐。 |
| **主题怎么用** | **不用。** |
| **禁止什么** | 主题任何路径触碰 Database。 |

### 4.6 `RedisService` / `RedisCache` / `RateLimitStore`

| | |
|--|--|
| **干什么** | Redis 连接/监控；业务缓存；限流存储。 |
| **主题怎么用** | **不用**（缓存失效由 Manager 内部处理）。 |
| **禁止什么** | 主题直读写 Redis 键。 |

### 4.7 `AjaxResponse.php`

| | |
|--|--|
| **干什么** | 统一 JSON：`success($msg, $extra)` → `{code:1,...}`；`error($msg)` → `{code:0,msg}`。 |
| **主题怎么用** | 主题包内 `api/*.php` 写操作时使用；先 `vs_require_secure_post()`。 |
| **禁止什么** | 自造另一套 `{ok:true}` 协议与入口不一致。 |

### 4.8 `ThemeManager.php`

| | |
|--|--|
| **干什么** | 发现主题、读 `theme.json` settings、拼壳 CSS/JS、渲染公开页/用户页/认证页。 |
| **主题怎么用** | 读本主题配置；资源 URL；一般不必自己调 `renderBody`（入口 helper 已调）。 |
| **禁止什么** | 加载其它主题资源；合并 shell 成单文件大 CSS 破坏约定。 |

| 方法 | 用途 |
|------|------|
| `activeId()` / `themeDir()` / `isValidTheme()` | 当前主题 |
| `themeSetting` / `themeSettingStr` / `themeSettingBool` / `themeSettingInt` | 读 settings |
| `navItems()` | 主导航 `[{id,label,url}]` |
| `userMenuGroups()` | 用户中心侧栏（按角色隐藏） |
| `assetUrl($themeId, $relative)` | 主题包内资源 |
| `shellUrl` / `pageScriptUrl` | shell / 页脚本 |
| `frontendShellCssHrefs` / `frontendShellJsHrefs` | 前台壳清单 |
| `userShellCssHrefs` / `userShellJsHrefs` | 用户中心壳 |
| `defaultFrontendAssets($pageKey)` | **仅 default** 多文件清单 |
| `activeStylesheetHref` / `activeScriptHref` | 非 default 的 theme.css/js |
| `renderBody` / `renderUserPage` / `renderAuthPage` | 渲染（入口侧） |

`renderBody` 注入变量（始终有）：  
`$vsBase`（站内路径前缀）、`$siteName`、`$navName`、`$systemName`、`$copyrightName`、`$copyrightUrl`、`$siteDesc`、`$pageKey`、`$pageTitle`、`$navItems`、`$activeNav`、`$userLoggedIn`、`$authUrl`、`$authLabel`、`$authAvatarUrl`、`$themeId`，以及 `$pageData` 全部键。

### 4.9 `SiteMedia.php` / `UserAvatar.php`

| | |
|--|--|
| **干什么** | 内置图标出站；用户头像解析。 |
| **主题怎么用** | `SiteMedia::imgUrl('QQ.svg')`；头像优先用 Frontend* 已带字段，兜底 `UserAvatar::resolve` / `defaultAvatar()`。 |
| **禁止什么** | 手写 `/assets/img/...`；自造 QQ 头像拼接逻辑。 |

### 4.10 `Sitemap.php` / `SystemInfo.php` / `AboutCatalog.php`

| | |
|--|--|
| **干什么** | SEO 地图；环境信息；关于页目录数据。 |
| **主题怎么用** | 关于页可用 `AboutCatalog::load()` / `SystemInfo`（若页面需要）；地图由根 `sitemap.php` 输出，主题勿重做。 |
| **禁止什么** | 主题伪造 sitemap 规则绕过公开接口过滤。 |

---

## 五、分册 B · 认证与注册

### 5.1 `UserAuth.php` / `UserRole.php` / `Auth.php`

| | |
|--|--|
| **干什么** | 用户会话、登录注册重置；角色常量；管理员会话（后台）。 |
| **主题怎么用** | 安全侧：`UserAuth::check()` / `id()` / `user()` / `requireLogin()` / `redirectIfLoggedIn()` / `logout()`。**展示资料用 `FrontendUser::current()`**。登录/注册提交由 `user/*.php` 入口处理，主题模板主要渲染。 |
| **禁止什么** | 主题自造 session 键；在纯视图里散落写库；管理员 `Auth` 混进前台主题。 |

`UserRole`：`ROLE_USER` = `user`，`ROLE_DEVELOPER` = `developer`；`normalize($role)`。

### 5.2 `AuthSecurity.php`

| | |
|--|--|
| **干什么** | CSRF 令牌、登录/邮件限流、安全响应头、邮件一次性票据等。 |
| **主题怎么用** | 页面输出 `AuthSecurity::csrfToken()` 到 `VS_CSRF_TOKEN`；表单带 `csrf_token`；AJAX 走 `vs_require_secure_post()`。 |
| **禁止什么** | 关闭 CSRF「图省事」；主题绕过安全头缓存策略。 |

前台页会 `sendFrontendSecurityHeaders`：`private, no-store` + `Vary: Cookie`，防止 CDN 把带登录态的 HTML 缓存放大。

### 5.3 `RegisterPolicy.php`（注册策略 · 必须写全）

| | |
|--|--|
| **干什么** | 控制「能不能注册、能注册成什么身份、要不要邮箱验证、邮箱后缀白名单」。用**单键** `register_enabled` 数字模式表达四种状态；后台勾选只是 UI，落库一个值。 |
| **主题怎么用** | **不要自己算开关。** 使用入口 `user/register.php` 注入的变量渲染；读方法仅作兜底。提交时服务端会再 `assertRoleAllowed`。 |
| **禁止什么** | 主题硬编码「永远可注册」；忽略 `$showRoleSegment` 强行显示双身份；客户端伪造 `role` 绕过（服务端会固定/校验）；再拆 `register_allow_*` 多键。 |

#### 5.3.1 配置键

| 常量 / 键 | 含义 |
|-----------|------|
| `RegisterPolicy::CONFIG_KEY` = `register_policy` | JSON：邮箱后缀列表等 |
| `KEY_ENABLED` = `register_enabled` | **单键模式**：`1` 全部开启 · `2` 全部关闭 · `3` 仅普通用户 · `4` 仅开发者（种子见 `install/database.sql`） |
| `KEY_EMAIL_VERIFY` = `register_email_verify` | 是否必须邮箱验证码（默认必须） |
| `MODE_ALL_OPEN` / `MODE_ALL_CLOSED` / `MODE_USER_ONLY` / `MODE_DEVELOPER_ONLY` | 模式常量 `'1'`/`'2'`/`'3'`/`'4'` |

#### 5.3.2 公开 API 一览

| 方法 | 返回 | 白话 |
|------|------|------|
| `getMode()` | `string` | 当前模式 `1`～`4` |
| `isOpen()` | `bool` | **是否至少开放一种身份**（登录页「立即注册」、强访注册页用此判断） |
| `isFullyOpen()` | `bool` | **两种身份都开放**（模式 `1`；后台总闸勾选态） |
| `allowsUserRole()` | `bool` | 是否允许注册为普通用户（模式 `1` 或 `3`） |
| `allowsDeveloperRole()` | `bool` | 是否允许注册为开发者（模式 `1` 或 `4`） |
| `allowsRole($role)` | `bool` | 指定 `user`/`developer` 是否允许 |
| `shouldShowRoleSegment()` | `bool` | 注册页是否显示身份分段滑块（**仅模式 `1` 为 true**） |
| `fixedRegisterRole()` | `string\|null` | 仅开放一种时返回固定 `user`/`developer`；两种都开或都关返回 `null` |
| `requiresEmailVerify()` | `bool` | 是否必须邮箱验证码 |
| `closedMessage()` | `string` | 完全关闭时的对外文案 |
| `roleClosedMessage()` | `string` | 某类身份未开放时的对外文案 |
| `assertOpen()` | `string\|null` | 开放返回 `null`；关闭返回错误文案 |
| `assertRoleAllowed($role)` | `string\|null` | 允许返回 `null`；否则返回错误（含总关闭） |
| `saveRoleAllows($allowUser, $allowDeveloper)` | `void` | 由勾选推导模式并**只写** `register_enabled`；后台设置页用 |
| `modeFromAllows($allowUser, $allowDeveloper)` | `string` | 勾选 → `1`/`2`/`3`/`4` |
| `getPolicy()` | `array{email_suffixes:string[]}` | 读后缀策略 |
| `saveEmailSuffixes(array $suffixes)` | `void` | 写后缀策略 |
| `hasEmailSuffixRestriction()` | `bool` | 是否启用了后缀限制 |
| `validateEmailSuffix($email)` | `string\|null` | 校验邮箱；不允许时返回错误文案 |
| `parseSuffixInput($input)` | `string[]` | 后台表单解析 |
| `formatSuffixInput(array $suffixes)` | `string` | 后台表单回显 |

#### 5.3.3 入口注入变量（主题注册页必读）

`user/register.php` 调用 `ThemeManager::renderAuthPage('register', …)` 时注入：

| 变量 | 类型 | 含义 |
|------|------|------|
| `$registerOpen` | `bool` | `RegisterPolicy::isOpen()` |
| `$emailVerify` | `bool` | 是否要邮箱验证码 |
| `$formEnabled` | `bool` | 开放且（免验证 **或** 邮箱已配置）时才可提交 |
| `$showRoleSegment` | `bool` | `shouldShowRoleSegment()`：是否画「普通用户 / 开发者」分段 |
| `$registerRole` | `string` | 当前默认/固定角色：`user` 或 `developer` |
| `$fixedRole` | `string\|null` | `fixedRegisterRole()` 原值 |
| `$registerClosedMsg` 等 | `string` | 关闭时的标题/说明文案 |
| `$mailEnabled` / `$mailDisabledMsg` | | 发信是否可用 |

主题模板约定（三主题已对齐）：

```php
$showRoleSegment = !isset($showRoleSegment) || !empty($showRoleSegment);
$registerRole = isset($registerRole) && (string)$registerRole === 'developer'
    ? 'developer' : 'user';
```

- `$showRoleSegment === true`：显示分段控件，用户可选身份，hidden/input 的 `role` 随切换变。  
- `$showRoleSegment === false`：不显示分段；用 `$registerRole` 作为唯一身份（服务端还会用 `fixedRegisterRole()` 强制）。  

POST `register` 时入口逻辑：若 `fixedRegisterRole()` 非空则**覆盖**客户端提交的 `role`，再 `assertRoleAllowed`。

### 5.4 `Captcha` 与 `captcha/*`

见 [分册 E](#八分册-e--支付--邮件--验证码)。

### 5.5 `oauth/*`

| | |
|--|--|
| **干什么** | QQ / Gitee 绑定与登录编排（仅已注册用户可绑定）。 |
| **主题怎么用** | UI 出站链到 `/user/oauth/start`；绑定页展示 `OAuthService::enabledProviders()` / `bindingsForUser`（通常由入口注入）。 |
| **禁止什么** | 主题里自己拼 OAuth token 交换；跳过 state 校验。 |

主要类：`OAuthConfig`、`OAuthState`、`OAuthService`、`HttpClient`、`qq/QQOAuth`、`gitee/GiteeOAuth`。

---

## 六、分册 C · Frontend\* 主题主 API

> 本节是主题开发的**主菜**。后台 `*Manager` 只服务运营后台；主题取数走这里。

### 6.0 公开接口目录全链路白话（catalog ↔ FrontendApi）★ 必读

#### （1）三句话结论

1. **`FrontendApi`（`core/FrontendApi.php`）= 后厨**  
   PHP **类**，服务器内部调用。负责：前台能展示哪些接口、字段怎么整理、详情取一条、目录瘦身。  
   **不是**一个网址。

2. **`catalog.php`（`core/front/catalog.php`）= 取餐窗口**  
   浏览器拉「全站接口清单」时访问的 **HTTP 入口**。  
   自己不查库；验票（POST + CSRF + IP 频控）后喊 `FrontendApi::listForCatalog()`，JSON 递给浏览器。

3. **两者不重复。**  
   - 主题 **JS** → 窗口（网址）  
   - 主题 **PHP**（详情等）→ 后厨（类）  
   - 窗口内部 → 后厨  

#### （2）为什么要拆成两个？

| 只留谁 | 会怎样 |
|--------|--------|
| 只留 `FrontendApi` | 浏览器 JS **调不了 PHP 类**；首页又禁止把全表写进 HTML → 列表没数据源 |
| 只留 `catalog.php` 并把查库写进窗口 | 详情/推荐卡也要同一规则 → 逻辑复制、难维护 |
| **窗口 + 后厨** | 列表走窗口；详情走后厨；规则只在 `FrontendApi` 写一次 |

历史白话：以前首页 PHP `json_encode(FrontendApi::listForTheme())` 把整包（含大文档）塞进 HTML。现改为 HTML 空壳 → JS POST 窗口拉清单。

#### （3）饭店对照图

```
访客浏览器
    │  POST 点菜单
    ▼
┌──────────────────────────────────────┐
│  取餐窗口 = core/front/catalog.php   │
│  · 验：正规 POST + CSRF              │
│  · 防：同一 IP 别狂刷                │
│  · 收：action / shuffle / partners   │
│  · 出：JSON（apiData 等）            │
└──────────────────┬───────────────────┘
                   │ 内部调用
                   ▼
┌──────────────────────────────────────┐
│  后厨 = core/FrontendApi.php         │
│  · listForCatalog() 清单+瘦身        │
│  · findForThemeById() 详情一条       │
│  · listForTheme() 完整列表（慎用）   │
└──────────────────┬───────────────────┘
                   ▼
                 数据库
```

#### （4）分场景：主题该对接谁

| 页面 | 对接谁 | 怎么接 | 不要做什么 |
|------|--------|--------|------------|
| **首页**接口卡片 | **窗口** catalog | 空壳 + `VS.fetchFrontCatalog({partners})` | PHP `json_encode` 全站列表 |
| **/apis** | **窗口** | 常带 `shuffle: true` | 同上 |
| **详情** | **后厨** | 入口 `$api` 或 `findForThemeById` | 为详情 POST 全站 catalog |
| 分类按钮文案 | `FrontendCategory` | PHP SSR `listTags`/`nameMap` | — |
| 首页合作伙伴 | 窗口顺带 `partners=1` | `fetchFrontCatalog({partners:true})` | 首屏灌伙伴大包 |

目录瘦身后的列表 **没有** 完整 `doc` / `aidoc` / `response`。完整文档在详情单条拿。

#### （5）窗口 `catalog.php` 参数与响应

| 项 | 内容 |
|----|------|
| 路径 | `{站点根}/core/front/catalog.php` |
| 方法 | **只能 POST** |
| 安全 | `vs_require_secure_post()`；IP 频控约 60 次/60 秒 |
| 内部 | `FrontendApi::listForCatalog()`（按 `apiorder` 展示）、`FrontendCategory::nameMap()`；可选 `FrontendPartner::listForTheme()` |

**POST 参数：**

| 参数 | 必填 | 白话 |
|------|------|------|
| `action` | 是 | 必须 `list` |
| `partners` | 否 | `1` = 响应多带伙伴数组 |

> 展示顺序由系统设置 `apiorder` 决定（`0` 随机临时打乱 / `1` 按分类权重）。**不再接受**客户端 `shuffle` 参数。

**成功 JSON 字段：**

| 字段 | 白话 |
|------|------|
| `code` | `1` 成功 |
| `msg` | 提示 |
| `apiData` | 瘦身接口数组（已按 `apiorder` 排好） |
| `categoryNames` | 分类 id → 中文名 |
| `apiCount` | 条数 |
| `apiorder` | `0` 随机 / `1` 按分类权重 |
| `partners` | 仅请求带了 `partners=1` |
| `csrf` | 可能刷新令牌 |

#### （6）浏览器三件套（主题必须具备）

1. 页脚有 `window.VS_FRONT_CATALOG = ".../core/front/catalog.php"`  
2. 根目录 `assets/js/common.js` 提供 `VS.fetchFrontCatalog({ partners? })`（系统级，主题包不必、也不许再带一份）  
3. 页面 JS 拉完后渲染卡片（搜索/分类/分页在浏览器内存做）；有序模式勿再客户端 `shuffle` / 按字母重排分类组

自研主题最短接法：对照 `default` / `slate` / `three` 抄；**不要**另造 `/theme/api/xxx` 目录接口。

#### （7）常见误解

| 误解 | 正解 |
|------|------|
| catalog 已带全部文档 | 故意去掉文档大字段 |
| FrontendApi 与 catalog 重复 | 一个类、一个网址 |
| 只调 FrontendApi、不用 catalog | **列表页不行** |
| 详情也走 catalog | 详情走单条 |
| 浏览器 GET 打开 catalog 试试 | 必须 POST + CSRF |

---

### 6.1 `FrontendCategory.php`

| | |
|--|--|
| **干什么** | 前台分类标签与名称映射。 |
| **主题怎么用** | 首页/apis 分类按钮；与卡片 `data-category` 对齐。 |
| **禁止什么** | `ApiCategoryManager::*`；自造「全部」id。 |

| 方法 | 返回 |
|------|------|
| `orderMode()` / `isRandomOrder()` | `apiorder`：`0` 随机 / `1` 按分类权重 |
| `listTags()` | 启用分类 `[{id,name},…]`（随机模式每次临时打乱副本） |
| `listTagsCanonical()` | 有序底稿（Redis 缓存，不打乱） |
| `nameMap()` | `{all:"全部","12":"工具",…}` |
| `nameToIdMap()` | `{名称:id}` |
| `nameToSortMap()` | `{名称:sort权重}` |
| `resolveIdByName($name)` | id 或 `''` |
| `countEnabled()` | int |
| `tagVisibleLimit()` | **15**（超出主题做「更多」） |

常量：`ALL_ID = 'all'`，`ALL_NAME = '全部'`，`CONFIG_KEY_ORDER = 'apiorder'`。

### 6.2 `FrontendApi.php`

| | |
|--|--|
| **干什么** | 公开接口列表、详情、目录瘦身（后厨）。 |
| **主题怎么用** | 详情用 `findForThemeById` / 入口 `$api`；列表用 catalog；KPI 用 `countForTheme` 或 `FrontendStats`。 |
| **禁止什么** | 首页灌 `listForTheme()`；调用 `ApiManager` 取展示数据。 |

| 方法 | 白话 | 谁调用 |
|------|------|--------|
| `listForTheme()` | 完整公开列表（Redis 存**按分类权重**有序底稿） | core 内部；主题**禁止**整表灌 HTML |
| `listForCatalog()` | 瘦身 + 按 `apiorder` 展示（随机则临时 shuffle） | **主要是** `catalog.php` |
| `sortByCategoryWeight(&$list)` | 按分类 `sort`、同分类 `id` | 缓存写入 / 目录 |
| `applyCatalogDisplayOrder(&$list)` | 有序底稿上再按模式临时打乱 | `listForCatalog` |
| `slimForCatalog($item)` | 删 `doc`/`aidoc`/`response` | 几乎只被 listForCatalog 用 |
| `findForThemeById($id)` | 详情一条（可含文档；可含 `author`） | 详情入口 / 主题 |
| `countForTheme()` | 公开接口个数 | 主题可调 |
| `pickRandomRecommend($excludeId)` | 详情推荐：纯随机一条（含维护、不含禁用、不含当前） | 三主题 `pages/detail.php` |
| `billingLabel($charge,$price)` | 计费文案 | 辅助 |
| `parseParamsList` / `prettyParamsJson` | 参数辅助 | 辅助 |

**列表/详情常用字段：**  
`id, name, desc, category, category_name, method, methods, method_label, endpoint, call_path, apitype, params, response, doc, aidoc, maintenance, needkey, needkey_label, keyways, keyways_label, qpm, qpm_label, calls, icon, icon_path, detail_url, charge, charge_label, points, billing_label, createtime, params_list`  
详情另有：`author => {id,username,avatar,profile_url}|null`；**仅详情**另有 `openapi_json`（由 `OpenApiBuilder` 从 `params` 派生，**不入库**）。  
**catalog：** 同上但**不含** `doc`/`aidoc`/`response`/`openapi_json`。

说明：`findForThemeById` 允许展示「已审核且已禁用」接口（`disabled=1`，真实 endpoint 清空由主题模糊占位）；`listForTheme` 仍排除禁用。`maintenance === 1` 时主题按维护态展示，勿引导真实调用。

### 6.2b `OpenApiBuilder.php`（v13.26.41）

| | |
|--|--|
| **干什么** | 由 `api.params`（自定义参数行数组）+ 接口元数据生成 **OpenAPI 3.1.1** 文档字符串/数组。 |
| **主题怎么用** | **禁止**主题手写拼装。读详情字段 `openapi_json`；模式切换与复制分流在各主题 `detail.js`。 |
| **禁止什么** | 新增库列存 OpenAPI；把大文档灌进 catalog；前端重复实现生成算法；**直接 HTTP 访问本文件**（未定义 `VS_ROOT` 时 403）。 |
| **servers** | 站内相对路径：仅动态填当前访问的 `vs_base_url()`（协议+Host+子目录，**不**追加绑定域名多条）；外链绝对地址仅拆其自身 host。 |
| **安全** | 预览入口须登录 + CSRF + `assertPreviewRateLimit`；白名单字段，不注入 `upkey`/`targeturl`；禁用接口 path 用 `/_unavailable` 占位。 |

| 方法 | 白话 |
|------|------|
| `documentForApiRow($row)` / `jsonForApiRow($row, $pretty)` | 库行 → OpenAPI 文档（详情嵌入建议 `$pretty=false`） |
| `documentFromPreviewRequest($post, $baseRow)` / `jsonFromPreviewRequest(...)` | 管理端/投稿预览 AJAX |
| `assertPreviewRateLimit()` | 预览频控 |

### 6.3 `FrontendStats.php`

| | |
|--|--|
| **干什么** | 四个首页 KPI。 |
| **主题怎么用** | 直接调四个静态方法。 |
| **禁止什么** | 主题 COUNT SQL；直调 `ApiManager::count*`。 |

| 方法 | 含义 |
|------|------|
| `userCount()` | 注册用户数 |
| `todayCallCount()` | 今日调用 |
| `approvedApiCount()` | 审核通过接口数 |
| `totalCallCount()` | 全站累计调用 |

### 6.4 `FrontendLink` / `FrontendPartner` / `FrontendSponsor`

| | |
|--|--|
| **干什么** | 友链 / 合作伙伴 / 赞助展示数据（表 `link`，kind 0/1/2，规则在 core）。 |
| **主题怎么用** | 见方法表；首页伙伴优先走 catalog。 |
| **禁止什么** | `LinkManager::*`；手写收款码磁盘路径。 |

**FrontendLink**

| 方法 | 返回 |
|------|------|
| `listForTheme()` | 已通过且启用 |
| `listForThemePage()` | `{items,total,truncated,limit}`（上限 120，shuffle） |
| `pickForFooter($limit=0)` | 页脚；0=全部，上限 10 |
| `siteCard()` | 本站卡片（申请页） |
| `formatForTheme($row)` | `{id,name,siteurl,icon,description,host,initial}` |

**FrontendPartner：** `listForTheme()` → `{id,name,siteurl,icon,initial}`  

**FrontendSponsor：** `paymentQrs()` → `[{id,label,url}]`；`listForTheme()` 名单  

### 6.5 `FrontendArticle` / `FrontendAnnouncement` / `FrontendAbout`

| | |
|--|--|
| **干什么** | 文章、公告、关于页绑定正文。 |
| **主题怎么用** | 列表用 list；详情用 find；HTML 优先用已渲染的 `body_html`。 |
| **禁止什么** | `ContentManager::*`；主题硬编码长文当关于页。 |

**文章**

| 方法 | 返回 |
|------|------|
| `listForTheme($limit=10)` | 列表（无正文；limit 1–50） |
| `listPaged($page,$pageSize,$beforeId)` | 分页包 |
| `findById($id,$incrementViews=true)` | 含 `body` + `body_html` |

列表字段：`id,title,summary,cover,coverlayout,coverlayout_label,views,views_label,createtime`

**公告：** `listForTheme()` / `listPopups()` / `findById($id)`  
字段：`id,title,summary,body,body_html,preview,ispinned,ispopup,createtime`

**关于：** `FrontendAbout::getBoundArticle()` → `{id,title,summary,body,body_html,createtime}|null`

### 6.6 `FrontendComment` / `FrontendFeedback`

| | |
|--|--|
| **干什么** | 文章评论列表/提交；接口反馈提交。 |
| **主题怎么用** | 提交前 CSRF；`submit` 成功返回数组、失败返回**错误字符串**（注意判断类型）。 |
| **禁止什么** | 主题 INSERT；绕过登录要求（反馈须登录）。 |

| 方法 | 说明 |
|------|------|
| `FrontendComment::tableReady()` | 表是否就绪 |
| `listByContentId($contentid)` | 已通过评论 |
| `submit(...)` | 成功数组 / 失败字符串 |
| `FrontendFeedback::tableReady()` | |
| `submit($apiid,$content)` | 须登录；成功数组 / 失败字符串 |

### 6.7 `FrontendUser.php` / `UserDashHello.php`

| | |
|--|--|
| **干什么** | 当前用户展示、签到、控制台 KPI、本人调用日志。 |
| **主题怎么用** | 控制台页调 `current` + `dashboardStats` + `UserDashHello::pick`；日志页 `myLogsPaged` / `myLogDetail`。 |
| **禁止什么** | 直查库；客户端指定 userid 偷看他人日志。 |

| 方法 | 返回 |
|------|------|
| `current()` | 格式化用户或 `null` |
| `format($user)` | 标准资料 |
| `checkinBanner()` | `{enabled,checked_today,min,max,show_banner}` |
| `doCheckin()` | `{ok,msg,amount?,balance?,points?}` |
| `dashboardStats()` | 控制台 KPI（须登录） |
| `myLogsPaged($opts)` | 本人日志分页（白名单字段） |
| `myLogDetail($id)` | 本人单条详情 |

**用户字段：** `id,username,email,avatar,bio,blog,wallpaper,role,role_label,can_publish_api,points,createtime,lastlogin,profile_url`  

**dashboardStats 要点：** `points,points_spent,email,createtime,lastlogin,role_label,can_publish_api,api_*,key_*,stat7,recent,detail_enabled,checkin_*`（`stat7` 含折线与本人排行；`recent` 近若干条白名单）。

问候：`UserDashHello::pick($displayName)` → `{hello,hint,slot,hour}`。

### 6.8 `FrontendContributor.php`

| | |
|--|--|
| **干什么** | 贡献者卡片与公开个人主页。 |
| **主题怎么用** | 列表 `listForTheme()`；主页 `findProfile($uid)`（或入口注入）。 |
| **禁止什么** | 拼用户表 + 接口表 SQL。 |

| 方法 | 返回 |
|------|------|
| `listForTheme()` | 卡片列表 |
| `findProfile($userId)` | 卡片 + `apis[]` |
| `listApisForUser($userId)` | 该用户公开接口 |
| `wallpaperUrl` / `joinLabel` | 辅助 |

卡片字段：`id,username,avatar,letter,bio,bio_custom,blog,wallpaper,apicount,calls,calls_label,join_label,createtime,profile_url,role_label`

### 6.9 详情页专用：`ApiQuickstart` / `Markdown` / Playground

| | |
|--|--|
| **干什么** | 快速上手代码块；Markdown 渲染；在线测试上下文与按需取钥。 |
| **主题怎么用** | 见示例；默认主题浏览器直连公开 endpoint，由 core 记账。 |
| **禁止什么** | 主题写 apilog；SSR 把 API KEY 明文写进 HTML；自造 MD 引擎。 |

```php
$qsBundle = ApiQuickstart::qsBundleFromAidoc(
    isset($api['aidoc']) ? $api['aidoc'] : '',
    isset($api['keyways']) ? $api['keyways'] : array('query')
);
// {auths, authLabels, byAuth}；图标可用 ApiQuickstart::langIconMap()

$html = Markdown::render($rawMarkdown); // 优先用已有 body_html
```

`vs_playground_session_context()` →  
`{loggedIn, apiKeyCount, userCenterUrl, loginUrl, csrf, playUrl, keysUrl}`（**无** `apiKey`）。  
取钥：登录后 `POST core/front/playground-key.php`（CSRF + UID/IP 频控）。

鉴权展示用 `$api['needkey_label']`、`$api['keyways']`、`$api['keyways_label']`、`$api['qpm_label']`；**不要**再调 `ApiManager::keywaysLabel()`。

### 6.10 标准页面写法对照

| 页面 | 主题应取数 |
|------|------------|
| 首页 | 空壳 + `VS.fetchFrontCatalog`；`FrontendCategory`；`FrontendStats`；`FrontendAnnouncement`；`FrontendLink::pickForFooter`；`ThemeManager::themeSetting*`；`SiteContext` |
| apis | 同目录异步（可 shuffle）+ 前端筛选 |
| 详情 | 入口 `$api`；`ApiQuickstart`；`FrontendFeedback` |
| 文章 | `FrontendArticle` + `FrontendComment` |
| 关于 | `FrontendAbout::getBoundArticle()` |
| 友链 | `FrontendLink::listForThemePage` + `siteCard` |
| 赞助 | `FrontendSponsor::*` |
| 贡献者 / 主页 | `FrontendContributor::*` |
| 用户控制台 | `FrontendUser` + `UserDashHello` |
| 用户日志 | `FrontendUser::myLogsPaged` / `myLogDetail` |
| 注册 | 入口注入的 `$registerOpen` / `$showRoleSegment` / `$registerRole` 等 |

最小首页 PHP 示例：

```php
<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$siteName    = SiteContext::siteName();
$tags        = FrontendCategory::listTags();
$apiCount    = FrontendStats::approvedApiCount();
$totalCalls  = FrontendStats::totalCallCount();
$footerLinks = FrontendLink::pickForFooter(8);
$announces   = FrontendAnnouncement::listForTheme();
$heroTitle   = ThemeManager::themeSettingStr('hero_title', '');
$qqIcon      = SiteMedia::imgUrl('QQ.svg');
$showPartners = ThemeManager::themeSettingBool('show_partners', true);
// 接口目录交给 JS：VS.fetchFrontCatalog({ partners: $showPartners })
```

---

## 七、分册 D · Manager 等后台业务类

> 主题开发者：**知道有这些类即可，取数不要调用它们。**  
> 维护者：后台 `admin/`、用户投稿、网关记账走这里。

### 7.1 与主题对照表

| 能力 | 后台类（主题勿用取数） | 主题类 |
|------|------------------------|--------|
| 接口分类 | `ApiCategoryManager` | `FrontendCategory` |
| 接口 CRUD/审核 | `ApiManager` | `FrontendApi` + `FrontendStats` |
| 用户管理 | `UserManager` | `UserAuth` + `FrontendUser` |
| 站点配置 | `Config` | `SiteContext` + `ThemeManager::themeSetting*` |
| 文章/公告 | `ContentManager` | `FrontendArticle` / `Announcement` / `About` |
| 友链/伙伴/赞助 | `LinkManager` | `FrontendLink` / `Partner` / `Sponsor` |
| 评论/反馈 | `CommentManager` / `ApiFeedbackManager` | `FrontendComment` / `FrontendFeedback` |
| 密钥 | `ApiKeyManager` | 用户中心入口页面（非公开主题页直调） |
| 日志 | `ApiLogManager` / `ApiLogArchive` | `FrontendUser::myLogs*` |

### 7.2 `ApiManager`（摘要）

| | |
|--|--|
| **干什么** | 接口表 CRUD、审核三态、方法/鉴权/计费字段规范化、公开列表（供 core 内部）。 |
| **主题怎么用** | **不用。** 展示走 FrontendApi。 |
| **禁止什么** | 主题 `listPublic` / `findById` 绕过前台可见性规则。 |

常用方法族：`create`/`update`/`setStatus`/`setAuditStatus`/`listFiltered`/`listByUser`/`formatRow`、以及 `normalizeMethods`/`normalizeKeyways`/`chargeLabel`/`qpmLabel` 等。

### 7.3 `ApiCategoryManager`

分类 CRUD、图标库、启禁、删除并迁移接口。主题用 `FrontendCategory`。

### 7.4 `ApiKeyManager`

每用户密钥上限 `apikey_max`（默认 3、最大 20）；密钥形如 `sk-`+32；配额字段 `quota`/`quotaused`/`quotafallback`/`expiretime`；全盘消耗 `pointsspent`。过期业务码 **11023**，配额不足 **11024**。主题勿用「tokens」命名误导用户。

### 7.5 `ApiStats` / `ApiProxy` / 出站三件套

| 类 | 干什么 |
|----|--------|
| `ApiStats` | 调用守卫、记账、出站头、扣费、写日志 |
| `ApiProxy` | 外链网关转发上游 |
| `JsonpGuard` | JSONP 回调白名单 |
| `ProxyJsonRewrite` | 响应 JSON set/del |
| `ApiOutboundSanitize` | 擦除敏感路径/凭据串 |
| `ProxyClientProfile` | UA/Referer 预设 |

主题：**禁止**调用这些类「自己转发接口」。在线测试默认浏览器直连公开 endpoint。

### 7.6 `ApiLogManager` / `ApiLogArchive`

热数据 MySQL + 冷数据 SQLite 分片；管理端/用户侧格式化与分页。用户侧安全字段经 `formatUserSafeRow`；主题经 `FrontendUser`。

### 7.7 `ContentManager` / `CommentManager` / `LinkManager`

| 类 | kind / 说明 |
|----|-------------|
| `ContentManager` | kind=0 公告；kind=1 文章；可绑定关于页 |
| `CommentManager` | 评论审核/删除 |
| `LinkManager` | kind=0 友链（有审核）；kind=1 伙伴；kind=2 赞助 |

配套：`LinkSiteMeta::fetch`（一键 TDK）、`LinkNotify`、`CommentNotify`、`ApiNotify`、`FeedbackNotify`。

### 7.8 `UserManager` / `AdminUserBinding` / `AdminNotify`

后台用户治理；管理员绑定发布身份；顶栏待办铃铛（审核/反馈/友链/评论/升级）。

### 7.9 `DashboardStats` / `PanelMonitor` / `GeoCityCoords` / `IpLocator` / `UserCallStats` / `UserIp*`

均为后台或调用链路内部能力。**主题禁止调用** `DashboardStats` / `PanelMonitor` / `GeoCityCoords`。

### 7.10 AI 文档链

`AiConfig` → `AiClient` → `AiChatSession` → `AiSse` → `AiApiDoc`：仅管理端/开发者编辑页生成文档与代码。主题详情只读已生成的 `aidoc`/`doc`，用 `ApiQuickstart` 解析展示。

### 7.11 `ApiError.php`

公开 API 业务错误码与 HTTP 状态分离；`label($errcode)`；AI 文档可写入全量码表说明。

---

## 八、分册 E · 支付 / 邮件 / 验证码

### 8.1 `Mailer.php`

| | |
|--|--|
| **干什么** | SMTP 发信；OTP 邮件 HTML 模板。 |
| **主题怎么用** | 一般不直接调；注册/找回由入口发信。 |
| **禁止什么** | 主题批量发信；绕过限流票据。 |

### 8.2 积分与支付：`PayConfig` / `OrderManager` / `PointsManager` / `PayPendingWatch` / `PointsNotify` / `CheckinManager` / `CardKeyManager` / `CodePayClient`

| | |
|--|--|
| **干什么** | 充值套餐、下单、码支付回调履约、余额扣费、注册赠送、每日签到、积分卡密兑换、待支付超时取消、积分邮件。 |
| **主题怎么用** | 用户中心充值/积分页走入口与 `FrontendUser`（余额、签到横幅）；充值页可展示卡密兑换框（入口注入 `cardkeyReady`）；收款码展示用 `FrontendSponsor::paymentQrs` 或 PayConfig 图标辅助（用户中心）。 |
| **禁止什么** | 主题伪造履约；自己验签回调；绕过 `PayPendingWatch`；主题直读 `cardkey` 表。 |

码支付 HTTP：`core/play/codeplay/notify.php`（异步）、`return.php`（浏览器回跳，履约以 notify 为准）。

签到展示：`FrontendUser::checkinBanner()` / `doCheckin()`（内部 `PointsManager` + `CheckinManager`）。

卡密：管理端 `/admin/finance/cardkey`；用户兑换走 `user/recharge.php` `action=redeem` → `CardKeyManager::redeem`（事务 + `FOR UPDATE`）。商城对接：`/core/api/cardkey.php`（系统密钥；generate / stock / take / void / query）。

### 8.3 `Captcha.php` 与 `captcha/*`

| | |
|--|--|
| **干什么** | 分端 mode（管理员/用户可不同）：`local` / `gt3` / `gt4`；场景校验。 |
| **主题怎么用** | 认证页挂载：`vs_captcha_field($scene)` + `vs_captcha_js($scene)`（`captcha/helper.php`）。场景用全名常量。 |
| **禁止什么** | 主题模板自己验票（入口 `Captcha::requireValid`）；另写一套换图逻辑。 |

场景常量（写全名）：

- `Captcha::SCENE_USER_LOGIN`
- `Captcha::SCENE_USER_REGISTER`
- `Captcha::SCENE_USER_FORGOT`
- （另有管理员场景，主题一般不用）

本地图：校验大小写不敏感；用户**首次聚焦**验证码框自动换图（根目录 `assets/js/captcha.js`）。

HTTP：`captcha/image.php`（出图）、`captcha/register.php`（极验 register/校验中转）。

---

## 九、分册 F · 升级与迁移

### 9.1 `Updater.php` / `UpdateLog.php`

| | |
|--|--|
| **干什么** | 云端检测更新、下载 ZIP、安全解压、覆盖后清理废弃文件；读本地/远端更新日志。 |
| **主题怎么用** | **不用。** |
| **禁止什么** | 主题触发升级；伪造版本绕过更新。 |

### 9.2 `DatabaseMigrator.php` / `SchemaFullAligner.php`

| | |
|--|--|
| **干什么** | 按版本执行迁移 SQL；对照 `install/database.sql` 全量对齐（只补不删）。 |
| **主题怎么用** | **不用。** 新业务表/字段由 core 迁移落地。 |
| **禁止什么** | 主题内执行 DDL/DML。 |

### 9.3 新增业务能力时的正确顺序（维护者）

```
① 数据库 / 迁移 SQL
② core/XxxManager.php（后台）
③ admin/ 管理页 + AJAX
④ core/FrontendXxx.php（前台只读）
⑤ bootstrap.php 注册
⑥ 各主题 pages 调用 FrontendXxx
⑦ 更新本文档 + README
```

**禁止：** 只在主题里写 SQL 赶进度。

---

## 十、分册 G · 子目录 HTTP 入口

下列文件是**独立网址**，各自 `require bootstrap` 或按需加载，**不会**当作主题里「随便 new」的普通类。

### 10.1 `core/front/`

| 文件 | 干什么 | 主题怎么用 | 禁止什么 |
|------|--------|------------|----------|
| `catalog.php` | 公开目录取餐窗口 | `VS.fetchFrontCatalog` | GET 打开；首屏灌包 |
| `playground-key.php` | 登录按需取 KEY | 详情调试条按需 POST | SSR 明文 KEY |

详见 [§6.0](#60-公开接口目录全链路白话catalog--frontendapi-必读)。

### 10.2 `core/captcha/`

| 文件 | 干什么 |
|------|--------|
| `helper.php` | `vs_captcha_field` / `vs_captcha_js` |
| `local.php` | 本地图实现 |
| `image.php` | HTTP 出图 |
| `register.php` | 极验中转 HTTP |
| `gt3/*` / `gt4/LoginController.php` | 极验 SDK 封装 |

### 10.3 `core/oauth/`

类由 bootstrap 加载；用户可见入口在 `user/oauth/`（start/callback）。主题只出链，不实现协议。

### 10.4 `core/markdown/`

| 文件 | 干什么 | 主题怎么用 |
|------|--------|------------|
| `Markdown.php` | `render($text)` 门面 | 优先 Frontend* 的 `body_html` |
| `Parsedown.php` | 引擎 | 勿直接依赖其内部 API |

### 10.5 `core/play/codeplay/`

| 文件 | 干什么 |
|------|--------|
| `CodePayClient.php` | 签名、下单、验签 |
| `notify.php` | 异步回调履约 |
| `return.php` | 浏览器回跳 |

### 10.6 `core/playground/`

| 文件 | 干什么 | 主题怎么用 |
|------|--------|------------|
| `relay.php` | 同源中继 HTTP（CSRF+频控） | 默认主题优先浏览器直连；兼容旧主题 |
| `media.php` | 测试媒体短时预览 | 调试条按需 |

类 `PlaygroundRelay::execute(...)` 供中继内部使用；**勿在主题写 apilog**。

### 10.7 `core/api/`

| 文件 | 干什么 |
|------|--------|
| `apilogarchive.php` | 日志清理（系统密钥 + 后台开关；归档或过期删除） |
| `cardkey.php` | 卡密对接（generate / stock / take / void / query；系统密钥 + 开关） |

主题无关；见《系统级外部API规范》。旧 `core/cron/` 已废弃删除，勿再新增。

### 10.8 `core/ping.php`

贡献者/卡片延迟检测 HTTP；含 IP 频控。主题 JS 可按现成主题方式调用，勿放开无校验代理。

---

## 十一、主题对接 Checklist

新建或验收自研主题时逐项打勾：

### 11.1 包结构

- [ ] `core/theme/{id}/theme.json`（`id` 与目录名一致，符合命名规则）  
- [ ] `layout/header.php` + `footer.php`  
- [ ] 公开 `pages/`：至少 `home` / `apis` / `detail`（及其它站点已启用的页面）  
- [ ] `assets/shell/` 放本主题样式和界面脚本即可；**不必**自带 `common.js`（系统加载根目录 `assets/js/common.js`，内含 `VS.fetchFrontCatalog`）  
- [ ] 非 default：提供 `theme.css` / `theme.js`  
- [ ] `user/layout.php` + `user/auth/*`（含 **register** 支持 `$showRoleSegment` / `$registerRole`）+ `user/pages/*`  

### 11.2 数据与安全

- [ ] 展示数据全部来自 Frontend\* / SiteContext / ThemeManager / SiteMedia / UserAuth / 入口注入  
- [ ] **无** `Database` / SQL / 表名 / `*Manager` 取数  
- [ ] 首页 / apis：**空壳 + catalog**；View Source **无**巨量接口 JSON  
- [ ] 详情：入口 `$api`；壳含 `VS_FRONT_CATALOG` + CSRF  
- [ ] 注册页：尊重 `$registerOpen` / `$showRoleSegment` / `$registerRole`；关闭时展示注入文案  
- [ ] 用户内容 `vs_e()`；写操作 CSRF + `vs_require_secure_post`  
- [ ] 图标 `SiteMedia`；头像走 Frontend\* / `UserAvatar`  
- [ ] 不引用其它主题与根目录前台 CSS/JS（系统级 `common.js` / `captcha.js` / `auth-csrf.js` 除外）  
- [ ] Playground：**无** KEY SSR；按需 `playground-key.php`  

### 11.3 走查

- [ ] 后台切换到该主题后，桌面 + 手机各走一遍公开页与用户中心  
- [ ] 注册：仅开用户 / 仅开开发者 / 全开 / 全关 四种策略 UI 均正确  
- [ ] 登录页「立即注册」仅在 `RegisterPolicy::isOpen()` 时出现（由入口控制）  

### 11.4 最小正确分层（再贴一次）

```
主题（客户端）
  → Frontend* / 窗口 HTTP / SiteContext / ThemeManager / helpers
    → *Manager / Config / Database（仅 core 内部）
```

---

## 十二、相关文档

| 文档 | 用途 |
|------|------|
| `README.md` | 安装与总览 |
| `CORE模块说明.md`（本文） | 主题对接 / core API |
| 《前端页面渲染与源码规范》 | 前台 HTML/JS 规范、catalog 相关易错点 |
| 《Git提交规范》 | 提交说明中文、禁止 AI 署名 |
| `update-log.json` | 版本变更记录（维护查阅；**本文不按版本分章**） |

---

**文档版本标注：** 13.26.42
**维护约定：** 新增 `Frontend*` 或主题可见 HTTP 窗口时，同步更新本文件对应分册与总目录表；说明以「干什么 / 主题怎么用 / 禁止什么」三块书写，避免按版本号堆章节。

---

## 附录 H · 逐文件 API 卡片（core，不含 theme）

> 本附录按**文件路径**罗列：一句话作用、公开方法名、主题可否直调。与正文分册互补；查某个文件时先搜本附录。

### `core/AboutCatalog.php` · `AboutCatalog`

**干什么：** 管理员关于页「开发与维护 / 相关链接 / 技术栈」目录加载

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `load`

---

### `core/AdminNotify.php` · `AdminNotify`

**干什么：** 管理后台顶栏待办通知汇总（审核/反馈/友链/评论/升级）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `inbox`

---

### `core/AdminUserBinding.php` · `AdminUserBinding`

**干什么：** 管理员账号与用户账号绑定（后台发布内容身份）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `isUserBoundToAdmin` · `getBoundUser` · `publishUserId` · `bind` · `unbind` · `activeBindUserCount` · `userOwnsApi` · `sqlApiOwnedByUser`

---

### `core/AiApiDoc.php` · `AiApiDoc`

**干什么：** 根据接口资料生成详细文档（Markdown）与快速上手代码示例（:::qs 短码）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `detailDocSections` · `detailDocSectionsForClient` · `generateDetailDoc` · `generateDetailDocSectionStream` · `generateDetailDocStream` · `generateCodeSamplePiece` · `generateCodeSamplePieceStream` · `generateCodeSamples` · `safeContext`

---

### `core/AiChatSession.php` · `AiChatSession`

**干什么：** AI 短时效多轮对话（Redis；无 Redis 则本进程无跨请求历史）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `key` · `topicFromApi` · `load` · `save` · `clear` · `clearAllForActor` · `buildMessages` · `appendTurn` · `savePartial` · `historyAvailable`

---

### `core/AiClient.php` · `AiClient`

**干什么：** OpenAI 兼容客户端（Chat Completions + Responses API；模型列表；连通测试）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `chat` · `testConnection` · `listModels` · `chatWithConfig` · `chatStreamWithConfig` · `extractAssistantText` · `normalizeBaseUrl` · `assertSafeBaseUrl` · `normalizeApiMode` · `chatCompletionsUrl` · `responsesUrl` · `modelsUrl`

---

### `core/AiConfig.php` · `AiConfig`

**干什么：** 站点 AI 对接配置（仅管理员后台使用）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `providerPresets` · `get` · `codeMode` · `codeConcurrency` · `codeClientOptions` · `apiMode` · `isReady` · `forAdminForm`

---

### `core/AiSse.php` · `AiSse`

**干什么：** AI 流式 SSE 输出（对抗 CDN/Nginx 缓冲：关缓冲头 + 心跳）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `isActive` · `begin` · `emit` · `comment` · `maybePing` · `flush` · `end`

---

### `core/AjaxResponse.php` · `AjaxResponse`

**干什么：** 后台/安装 AJAX JSON 响应

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `json` · `success` · `error`

---

### `core/ApiCategoryManager.php` · `ApiCategoryManager`

**干什么：** API 接口分类管理

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `iconLibraryDir` · `defaultIconPaths` · `defaultIcons` · `resolveIconUrl` · `formatRow` · `listAll` · `listEnabled` · `findById` · `findByName` · `create` · `update` · `setStatus` · `listOthers` · `deleteAndMove` · `delete` · `countApisByName` · `normalizeIconInput`

---

### `core/ApiError.php` · `ApiError`

**干什么：** 公开 API 业务错误码（与 HTTP 网络状态码分离，避免 401/403/503 等重合）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `businessLabelMap` · `label` · `aiDetailDocErrcodeClause` · `isKnown` · `isBusinessFailure` · `looksLikeBusinessErrorPayload`

---

### `core/ApiFeedbackManager.php` · `ApiFeedbackManager`

**干什么：** 接口反馈 CRUD（管理员处理 / 列表）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `tableReady` · `statusLabel` · `countPending` · `listPendingBrief` · `listAll` · `findById` · `formatRow` · `setStatus` · `setReply` · `delete` · `create`

---

### `core/ApiKeyManager.php` · `ApiKeyManager`

**干什么：** 用户 API 调用密钥 CRUD（每用户上限由系统设置 apikey_max 配置，默认 3、最大 20）；

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `maxPerUser` · `normalizeMaxPerUser` · `canCreateMore` · `tableReady` · `statusLabel` · `generateSecret` · `countByUser` · `listByUser` · `listAll` · `findById` · `findBySecret` · `formatRow` · `expireLabel` · `isExpired` · `create` · `saveSettings` · `updateRemark` · `resetSecret` · `setStatus` · `delete` · `incrementCalls` · `userHasKeycallsColumn` · `userKeyCallsTotal` · `hasPointsspentColumn` · `resetPointsspentColumnCache` · `hasQuotaColumns` · `resetQuotaColumnCache` · `prepareCharge` · `adjustQuotaused` · `clearQuotaNoticeFlag` · `adjustPointsspent`

---

### `core/ApiLogArchive.php` · `ApiLogArchive`

**干什么：** 调用日志冷热分层——热数据留 MySQL；冷数据三层索引 + SQLite 分片。写冷库成功后**必须**从 MySQL 删除对应行（不可逆，禁止双留）。

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `rootDir` · `catalogPath` · `dayIndexPath` · `shardDir` · `isEnabled` · `sqliteAvailable` · `shardRows` · `clampShardRows` · `hotDays` · `cronKey` · `generateCronKey` · `validateCronKey` · `cronUrl` · `ensureStorage` · `run` · `runOnce` · `countInQueryWindow` · `listInQueryWindow` · `findById` · `readCatalog`

---

### `core/ApiLogManager.php` · `ApiLogManager`

**干什么：** API 调用日志查询（每页条数 + keyset / 热冷合并 / 短 TTL；冷数据见 ApiLogArchive）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `detailEnabled` · `hasEgressColumn` · `queryDaysDefault` · `keepDays` · `clampQueryDays` · `methodClass` · `maskApikey` · `httpClass` · `httpcodeLabel` · `formatRow` · `findById` · `countToday` · `listPaged` · `formatUserSafeRow` · `formatUserDetailRow` · `findByIdForUser` · `recentForUser` · `listForUser`

---

### `core/ApiManager.php` · `ApiManager`

**干什么：** API 接口数据管理（后台接口列表 CRUD、用户投稿）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `hasAuditColumn` · `hasRejectReasonColumn` · `hasProxyColumns` · `hasChargeColumns` · `hasQpmColumn` · `hasKeywaysColumn` · `hasUpstreamAuthColumns` · `hasProxyClientColumns` · `hasUpmethodColumn` · `normalizeUpmethod` · `upmethodLabel` · `upmethodHttp` · `normalizeUpauth` · `normalizeUpkeyvia` · `normalizeUpkeyname` · `normalizeUpkey` · `upauthLabel` · `normalizeQpm` · `qpmLabel` · `normalizeKeyways` · `keywaysToStorage` · `keywaysLabel` · `normalizeCharge` · `normalizePrice` · `chargeLabel` · `listPublic` · `countPublic` · `countApproved` · `totalCallCount` · `categoriesFromList` · `listAll` · `listByAudit` · `listForReview` · `countPendingReview` · `listPendingReviewBrief` · `listByUser` · `attachUserIdIfOrphan` · `listFiltered` · `findById` · `create` · `update` · `updateDocsContent` · `setStatus` · `setAuditStatus` · `normalizeRejectReason` · `delete` · `incrementCallCount` · `normalizeStatus` · `statusLabel` · `isValidStatus` · `normalizeAuditStatus` · `isValidAuditStatus` · `auditStatusLabel` · `auditStatusClass` · `formatRow` · `formatRowSummary` · `normalizeApiType` · `apiTypeLabel` · `apiTypeBadge` · `requireKeyBadge` · `resolveCallPath` · `resolveCallUrl` · `normalizeMethods` · `methodsToStorage` · `methodsLabel` · `normalizeRequireKey` · `requireKeyLabel`

---

### `core/ApiNotify.php` · `ApiNotify`

**干什么：** 接口投稿 / 审核结果的邮件通知（依赖 Mailer，发信失败不阻断主流程）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `notifyAdminsPending` · `notifyUserAuditResult`

---

### `core/ApiOutboundSanitize.php` · `ApiOutboundSanitize`

**干什么：** 公开 API / 代理出站 JSON 消毒 —— 去掉后台路径与配置敏感串

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `stringLooksSensitive` · `isAllowedRewriteValue` · `keyLooksCredential` · `scrubNode` · `narrowBusinessErrorBody` · `scrubJsonBody`

---

### `core/ApiProxy.php` · `ApiProxy`

**干什么：** 代理外链网关 —— 公开地址转发上游

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `findBySlug` · `findCallableBySlug` · `requestPathInfo` · `resolveSlugFromRequest` · `isGatewayRequest` · `handleRequest` · `buildUpstreamRequest` · `publicPath` · `publicUrl` · `normalizeSlug` · `generateUniqueSlug` · `slugExists` · `isPlatformKeyFieldName` · `stripPlatformKeyFieldsFromArray` · `stripPlatformKeyFieldsFromBody` · `mergeQuery`

---

### `core/ApiQuickstart.php` · `ApiQuickstart`

**干什么：** 默认主题 API 详情「快速上手」——从 aidoc 的 :::qs 短码解析多语言示例

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `langMeta` · `langMap` · `authLabels` · `authLabel` · `iconUrl` · `langIconMap` · `samplesFromAidoc` · `qsBundleFromAidoc` · `normalizeAidocBlocks` · `parseQsBlocks` · `parseQsAttrs` · `parseFenceBlocksAsQs` · `normalizeLangId` · `normalizeAuthId` · `syntaxLang` · `scrubHighlightLeak` · `stripEmoji`

---

### `core/ApiStats.php` · `ApiStats`

**干什么：** 本地/代理接口调用统计（次数 + 调用日志）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `keyContext` · `hit` · `outboundHeaders` · `outboundUa` · `outboundReferer` · `captureEgressProxyFlags` · `applyOutboundProxy` · `applyOutboundProxyForProxy` · `hitProxy` · `chargeProxyUpfront` · `guardAccess` · `tableReady`

---

### `core/Auth.php` · `Auth`

**干什么：** 管理员认证、登录态管理、会话超时

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `login` · `loginById` · `logout` · `touchActivity` · `isSessionExpired` · `check` · `id` · `requireLogin` · `redirectIfLoggedIn` · `user` · `updateAccount` · `resetPasswordById`

---

### `core/AuthSecurity.php` · `AuthSecurity`

**干什么：** 认证页安全防护（CSRF、频率限制、登录防暴力）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `sessionCookieSecure` · `detectSessionRealm` · `currentSessionRealm` · `configureSessionCookies` · `expireNamedSessionCookie` · `clearSessionCookie` · `isHttps` · `sendSecurityHeaders` · `sendFrontendSecurityHeaders` · `ensureCsrfToken` · `rotateCsrfToken` · `csrfToken` · `validateCsrf` · `normalizeRequestHost` · `validateSameOrigin` · `clientIp` · `trustForwardedHeaders` · `rateLimitAllow` · `secondsSinceLastHit` · `issueMailTicket` · `validateAndConsumeMailTicket` · `withMailTicket` · `checkLoginAllowed` · `recordLoginFailure` · `checkMailCodeAllowed` · `recordMailCodeAttempt` · `recordMailCodeSent` · `checkResetSubmitAllowed` · `recordResetSubmit` · `recordOtpFailure` · `clearOtpSession` · `resetOtpFailCount` · `checkOAuthStartAllowed` · `recordOAuthStart` · `checkOAuthCallbackAllowed` · `recordOAuthCallback` · `requireAuthPost`

---

### `core/Captcha.php` · `Captcha`

**干什么：** 系统级验证码门面（本地图 / 极验3 / 极验4；管理员与用户可分别选方式）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `normalizeMode` · `modeAdmin` · `modeUser` · `isAdminScene` · `mode` · `sideUsesMode` · `credentialsReadyForMode` · `credentialsReady` · `sceneConfiguredOn` · `sceneEnabled` · `gt3Id` · `gt3Key` · `gt4Id` · `gt4Key` · `gt4Api` · `publicBoot` · `registerGt3` · `requireValid` · `scenes` · `forAdminForm`

---

### `core/CheckinManager.php` · `CheckinManager`

**干什么：** 每日签到记录（同用户同日唯一；主题经 FrontendUser / PointsManager 调用）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `today` · `hasCheckedInToday` · `record` · `deleteToday` · `bannerState`

---

### `core/CardKeyManager.php` · `CardKeyManager`

**干什么：** 积分卡密生成、库存查询、出库（已发放）、列表（keyset）、统计、作废、兑换（事务锁行防并发双兑）

**主题：** 一般**不要**在主题里直接调用；充值页只渲染入口注入的兑换 UI，POST 由 `user/recharge.php` 处理

**公开方法：** `table` · `tableReady` · `statusLabel` · `isRedeemableStatus` · `isValidCodeFormat` · `formatRow` · `generate` · `stats` · `stock` · `takeFromStock` · `listPaged` · `redeem` · `voidUnused` · `voidUnusedByCodes` · `findByCode`

---

### `core/SystemApiKey.php` · `SystemApiKey`

**干什么：** 系统级密钥（`config.system_api_key`）；归档计划任务与卡密对接 API 等机器调用共用；支持 Bearer / Header / Query 提取与 `hash_equals` 校验

**主题：** **禁止**调用

**公开方法：** `migrateFromLegacy` · `get` · `generate` · `set` · `validate` · `requestBody` · `extractFromRequest` · `requireAuthorized` · `requireRateLimit` · `jsonExit`

---

### `core/api/cardkey.php`

**干什么：** 卡密对接 HTTP API（`action=generate|stock|take|void|query`）；须系统密钥；库存查询与出库；按码作废/查询；限流

**主题：** **禁止**调用；由商城/外部系统 HTTPS 调用

**公开方法：** （入口脚本）

---

### `core/CommentManager.php` · `CommentManager`

**干什么：** 文章评论 CRUD（管理员处理；邮箱必填；支持引用回复与个人网址）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `hasParentColumn` · `hasWebsiteColumn` · `normalizeStatus` · `statusLabel` · `normalizeFlag` · `normalizeWebsite` · `excerptBody` · `formatRow` · `countPending` · `listPendingBrief` · `listAll` · `findById` · `create` · `setReply` · `setPinned` · `setStatus` · `delete` · `listApprovedByContent`

---

### `core/CommentNotify.php` · `CommentNotify`

**干什么：** 文章评论邮件通知（新评论/引用通知管理员；被引用与管理员回复通知评论者；失败不阻断主流程）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `notifyAdminsNew` · `notifyParentQuoted` · `notifyUserAdminReply`

---

### `core/Config.php` · `Config`

**干什么：** 系统配置读写（vs_config 表，初始数据见 database.sql）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `all` · `get` · `set` · `setMany` · `isMailEnabled` · `sessionTimeout` · `clearCache`

---

### `core/ContentManager.php` · `ContentManager`

**干什么：** 公告与文章共用管理（表 content；kind 区分）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `hasBindPageColumn` · `normalizeKind` · `normalizeStatus` · `normalizeFlag` · `kindLabel` · `statusLabel` · `normalizeBindPage` · `bindPageLabel` · `normalizeCoverLayout` · `coverLayoutLabel` · `plainTextPreview` · `formatRow` · `findById` · `listAll` · `listPaged` · `create` · `update` · `delete` · `findBoundAboutId` · `isAboutBound` · `findBoundAboutRow` · `setStatus` · `setPinned` · `setPopup` · `incrementViews`

---

### `core/DashboardStats.php` · `DashboardStats`

**干什么：** 管理员控制台 / 数据大屏统计聚合（分层 TTL 缓存，避免大表反复扫）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `assertAjaxRateLimit` · `bootAttrJson` · `consoleBootShell` · `consoleSnapshot` · `liveIntervalChoices` · `liveIntervalSeconds` · `consoleLiveTick` · `screenSnapshot` · `screenLiveTick`

---

### `core/Database.php` · `Database`

**干什么：** PDO 数据库连接与操作封装

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `loadConfig` · `connect` · `connectWithConfig` · `testConnection` · `prefix` · `table` · `reset`

---

### `core/DatabaseInstaller.php` · `DatabaseInstaller`

**干什么：** 读取 install/database.sql 并执行建表

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `sqlFile` · `sqlFileExists` · `install` · `dropExistingTables` · `getExistingTables` · `parseSqlStatements`

---

### `core/DatabaseMigrator.php` · `DatabaseMigrator`

**干什么：** 版本更新时执行 install/migrations 下的增量 SQL（数据库结构更新）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `migrationsDir` · `normalizeVersionToken` · `runPending` · `reconcileSchemaState` · `ensureIpProxyCodeFive` · `tableIndexExists` · `purgeLegacyArtifacts` · `getPendingFiles` · `hasPendingMigrations` · `isMigrationPending` · `getAppliedVersions` · `markApplied` · `unmarkApplied` · `pruneAppliedAboveCodeVersion` · `forceMigrateRange` · `hasSchemaProbe` · `isMigrationObsolete` · `seedAppliedUpTo` · `tableColumnExists` · `tableExists` · `executeFile` · `assertPrefixedTables` · `applyAdminAvatarUrlColumn` · `applyMailCodeRateLogMigration` · `execStatement` · `backfillOrphanAdminApis` · `applyContentTable` · `applyContentCoverLayoutColumn` · `versionSchemaReady` · `ensureVersionSchema` · `isIgnorableSqlError`

---

### `core/FeedbackNotify.php` · `FeedbackNotify`

**干什么：** 接口反馈邮件通知（新反馈通知管理员 / 处理结果通知用户；失败不阻断主流程）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `notifyAdminsPending` · `notifyUserHandled`

---

### `core/FrontendAbout.php` · `FrontendAbout`

**干什么：** 前台主题 · 关于页内容（由绑定文章驱动；主题禁止直读库）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `getBoundArticle`

---

### `core/FrontendAnnouncement.php` · `FrontendAnnouncement`

**干什么：** 前台主题 · 已发布公告与弹窗（主题只调用本类）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `formatForTheme` · `listForTheme` · `listPopups` · `findById`

---

### `core/FrontendApi.php` · `FrontendApi`

**干什么：** 前台主题 · 公开接口列表与详情（统一调度，主题只调用本类）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `formatForTheme` · `bindRequestHost` · `bindRequestHostToList` · `billingLabel` · `parseParamsList` · `prettyParamsJson` · `listForTheme` · `sortByCategoryWeight` · `applyCatalogDisplayOrder` · `slimForCatalog` · `listForCatalog` · `findForThemeById` · `countForTheme` · `pickRandomRecommend`

---

### `core/OpenApiBuilder.php` · `OpenApiBuilder`

**干什么：** 由 `api.params` + 接口元数据派生 OpenAPI 3.1.1（不入库）；直访 403

**主题：** 只读详情字段 `openapi_json`；禁止主题手写拼装

**公开方法：** `documentForApiRow` · `jsonForApiRow` · `documentFromPreviewRequest` · `jsonFromPreviewRequest` · `assertPreviewRateLimit`

---

### `core/FrontendArticle.php` · `FrontendArticle`

**干什么：** 前台主题 · 已发布文章列表与详情（主题只调用本类）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `formatForTheme` · `listForTheme` · `listPaged` · `findById`

---

### `core/FrontendCategory.php` · `FrontendCategory`

**干什么：** 前台主题 · 接口分类数据（统一调度，主题只调用本类，不直接访问数据库表/字段）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `tagVisibleLimit` · `orderMode` · `isRandomOrder` · `countEnabled` · `listTags` · `listTagsCanonical` · `nameToSortMap` · `nameMap` · `nameToIdMap` · `resolveIdByName`

---

### `core/FrontendComment.php` · `FrontendComment`

**干什么：** 前台主题 · 文章评论（主题只调用本类，禁止直读库）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `tableReady` · `listByContentId` · `submit`

---

### `core/FrontendContributor.php` · `FrontendContributor`

**干什么：** 前台贡献者列表与公开个人主页（主题只调本类，禁止直读库）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `listForTheme` · `findProfile` · `listApisForUser` · `wallpaperUrl` · `joinLabel` · `hostFromEndpoint`

---

### `core/FrontendFeedback.php` · `FrontendFeedback`

**干什么：** 前台主题 · 接口反馈提交（主题只调用本类，禁止直读库）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `tableReady` · `submit`

---

### `core/FrontendLink.php` · `FrontendLink`

**干什么：** 前台主题 · 已通过友情链接列表（主题只调用本类）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `formatForTheme` · `pickForFooter` · `listForThemePage` · `listForTheme` · `siteCard`

---

### `core/FrontendPartner.php` · `FrontendPartner`

**干什么：** 前台主题 · 已启用合作伙伴列表（主题只调用本类）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `formatForTheme` · `listForTheme`

---

### `core/FrontendSponsor.php` · `FrontendSponsor`

**干什么：** 前台主题 · 赞助收款码与赞助名单（主题只调用本类）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `paymentQrs` · `formatForTheme` · `listForTheme`

---

### `core/FrontendStats.php` · `FrontendStats`

**干什么：** 前台主题可展示的统计数据（无 SQL 进主题）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `userCount` · `todayCallCount` · `approvedApiCount` · `totalCallCount`

---

### `core/FrontendUser.php` · `FrontendUser`

**干什么：** 前台/用户中心统一用户信息调度（主题与布局通过本类获取用户资料，禁止直读数据库）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `current` · `format` · `checkinBanner` · `doCheckin` · `dashboardStats` · `myLogsPaged` · `myLogDetail`

---

### `core/GeoCityCoords.php` · `GeoCityCoords`

**干什么：** 数据大屏飞线城市经纬度全量库（国内地级市加强 + 全球主要城市 + 中英别名）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `china` · `world` · `aliases` · `matchNamesSorted` · `resolveCityName`

---

### `core/InstallChecker.php` · `InstallChecker`

**干什么：** 检测系统是否已完成安装（文件锁 + 库标记双保险）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `lockFile` · `configFile` · `isInstalled` · `probeDbInstallFlag` · `dbInstallFlagSet` · `markInstalledInConfig` · `requireInstalled` · `requireNotInstalled`

---

### `core/IpLocator.php` · `IpLocator`

**干什么：** IP 归属地解析（系统内置或自定义接口），结果写入 apilog.iploc 供数据大屏飞线使用

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `enabled` · `provider` · `requestMethod` · `lookup` · `probe` · `assertPublicHttpUrl` · `parseExtras`

---

### `core/JsonpGuard.php` · `JsonpGuard`

**干什么：** JSONP callback 白名单校验；剥离危险回调参数（防反射型 XSS）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `paramNames` · `isJsonpParamName` · `isSafeCallbackName` · `sanitizeCallbackName` · `stripCallbackParams` · `wrapJsonIfSafe`

---

### `core/LinkManager.php` · `LinkManager`

**干什么：** 友情链接 / 合作伙伴 / 赞助共用管理（表 link；kind 区分）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `normalizeStatus` · `normalizeKind` · `kindLabel` · `normalizeEnabled` · `statusLabel` · `enabledLabel` · `normalizeUrl` · `normalizeIcon` · `upgradeInsecureUrl` · `formatRow` · `findById` · `countPendingFriend` · `listPendingFriendBrief` · `listAll` · `listApproved` · `listPartnersEnabled` · `listSponsorsEnabled` · `urlExists` · `create` · `apply` · `update` · `setStatus` · `setEnabled` · `delete` · `invalidateCache`

---

### `core/LinkNotify.php` · `LinkNotify`

**干什么：** 友情链接申请 / 审核通过的邮件通知（失败不阻断主流程）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `notifyAdminsPending` · `notifyApplicantApproved` · `extractEmail`

---

### `core/LinkSiteMeta.php` · `LinkSiteMeta`

**干什么：** 抓取外站 HTML，解析 title / description / favicon（友链一键填充）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `fetch` · `isAllowedFetchUrl` · `pinPublicFetchTarget` · `curlPreparePinnedUrl` · `isPublicRoutableIp`

---

### `core/Mailer.php` · `Mailer`

**干什么：** 系统邮件发送（SMTP）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `send` · `otpMailBody`

---

### `core/OrderManager.php` · `OrderManager`

**干什么：** 积分变动与支付订单（表 orders）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `genOrderNo` · `kindLabel` · `kindClass` · `statusLabel` · `formatRow` · `statusClass` · `findByOrderNo` · `insert` · `sumUserSpent` · `listPaged`

---

### `core/PanelMonitor.php` · `PanelMonitor`

**干什么：** 对接宝塔 / 1Panel 面板接口，汇总控制台「服务器」卡片数据

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `emptySnapshot` · `isEnabled` · `isConfigured` · `isDisplayReady` · `clearCache` · `persistConfig` · `snapshot` · `testConnection` · `publishSuccessSnapshot` · `configOnlySnapshot` · `normalizeProvider` · `providerLabel` · `assertSafePanelUrl`

---

### `core/PayConfig.php` · `PayConfig`

**干什么：** 码支付与积分充值相关系统配置读写

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `all` · `isReady` · `rate` · `channels` · `methods` · `packages` · `save` · `fmtPoints` · `methodLabel` · `iconPath` · `iconUrl` · `iconHtml`

---

### `core/PayPendingWatch.php` · `PayPendingWatch`

**干什么：** 充值待支付单超时自动取消（惰性过期 + Redis ZSET 顺带弹出；无计划任务、无全表扫）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `ttlSeconds` · `track` · `untrack` · `expireIfDue` · `expireUserPending` · `drainExpired` · `onRequest` · `applyLazyToRow` · `isRechargePending` · `rowIsOverdue`

---

### `core/PlaygroundRelay.php` · `PlaygroundRelay`

**干什么：** 可选同源中继（兼容旧主题）。默认主题 v4.8.0+ 用浏览器直连公开 endpoint，勿在此写 apilog。

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `execute`

---

### `core/PointsManager.php` · `PointsManager`

**干什么：** 用户积分余额增减与充值履约

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `balance` · `hasPointsColumn` · `hasSpentColumn` · `spentTotal` · `deductApiCall` · `refundApiCall` · `adminAdjust` · `creditCardKey` · `createRecharge` · `completeRecharge` · `cancelPending` · `giftOnRegister` · `checkin`

---

### `core/PointsNotify.php` · `PointsNotify`

**干什么：** 积分相关邮件通知（余额归零 / 不足调用 / 充值成功；失败不阻断主流程）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `notifyBalanceZero` · `notifyPointsInsufficient` · `clearInsufficientNoticeFlag` · `notifyRechargeSuccess` · `notifyKeyQuotaExhausted`

---

### `core/ProxyClientProfile.php` · `ProxyClientProfile`

**干什么：** 出站身份（User-Agent / Referer）内置预设与解析

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `presets` · `presetOptions` · `normalizeUaMode` · `normalizeRefererMode` · `normalizePresetKey` · `normalizeUa` · `normalizeReferer` · `resolveUa` · `resolveReferer` · `buildClientHeaders`

---

### `core/ProxyJsonRewrite.php` · `ProxyJsonRewrite`

**干什么：** 代理接口返回 JSON 的字段级改写（仅 JSON；设置 / 删除 / 覆盖）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `hasColumn` · `normalizeConfig` · `parseConfig` · `parsePath` · `looksLikeJson` · `apply` · `applyToData`

---

### `core/RateLimitStore.php` · `RateLimitStore`

**干什么：** 发信/操作频率限制（优先 Redis，降低 MySQL 高频写入；不可用时回退 MySQL）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `countHits` · `secondsSinceLastHit` · `allow` · `recordHit`

---

### `core/RedisCache.php` · `RedisCache`

**干什么：** ApiNexus 业务数据 Redis 缓存（读写分离 MySQL，降低高频查询与限流写入压力）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `enabled` · `remember` · `get` · `put` · `set` · `forget` · `invalidateFrontend` · `apilogPageKey` · `apilogRangeTotalKey` · `ordersRangeTotalKey` · `apilogFilterTotalKey` · `invalidateOrders` · `invalidateApiLog` · `maintainKeyspace` · `appStats` · `inspectEntries`

---

### `core/RedisService.php` · `RedisService`

**干什么：** Redis 连接、业务缓存监控与 ApiNexus 专用键空间

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `extensionLoaded` · `normalizeDatabase` · `normalizeHost` · `normalizePrefix` · `testDraftConnection` · `saveConnectionSettings` · `countKeysUnderPrefix` · `detectPrefixConflict` · `flushKeyspace` · `savePrefixConfig` · `ping` · `withClient` · `siteKeySalt` · `keyspacePrefix` · `buildKey` · `formatBytes` · `connectionConfig` · `versionLabel` · `collectMonitorSnapshot` · `pruneRateLimitKeys` · `formatUptime`

---

### `core/RegisterPolicy.php` · `RegisterPolicy`

**干什么：** 用户注册策略（总闸全选、按身份开放、邮箱验证、邮箱后缀限制）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `getMode` · `isOpen` · `isFullyOpen` · `allowsUserRole` · `allowsDeveloperRole` · `allowsRole` · `shouldShowRoleSegment` · `fixedRegisterRole` · `requiresEmailVerify` · `closedMessage` · `roleClosedMessage` · `assertOpen` · `assertRoleAllowed` · `saveRoleAllows` · `modeFromAllows` · `getPolicy` · `saveEmailSuffixes` · `hasEmailSuffixRestriction` · `validateEmailSuffix` · `parseSuffixInput` · `formatSuffixInput`

---

### `core/SchemaFullAligner.php` · `SchemaFullAligner`

**干什么：** 对照 install/database.sql 终态结构，对线上库做「只补不删」的全量结构对齐

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `align`

---

### `core/SiteContext.php` · `SiteContext`

**干什么：** 站点展示信息（读取系统配置；备案号按访问 Host 匹配，最多两槽）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `clearCache` · `normalizeHost` · `normalizeDomainInput` · `currentHost` · `resolve` · `siteName` · `systemName` · `navName` · `copyrightName` · `copyrightUrl` · `siteDescription` · `siteKeywords` · `siteFavicon` · `siteLogo` · `siteRuntimeStart` · `footerHtmlLeft` · `footerHtmlCenter` · `footerHtmlRight` · `footerQr1Enabled` · `footerQr1Name` · `footerQr1Url` · `footerQr2Enabled` · `footerQr2Name` · `footerQr2Url` · `icpLink` · `gonganLink` · `beianInfo`

---

### `core/SiteMedia.php` · `SiteMedia`

**干什么：** 站点内置图片（分类图标、语言图标、头像、支付/备案图标等）统一经此类解析出站 URL

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `imgUrl` · `imgWebPath` · `resolve`

---

### `core/Sitemap.php` · `Sitemap`

**干什么：** 生成前台 SEO 用 sitemap.xml（静态页 + 公开接口详情 + 已发布文章）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `emit` · `buildXml` · `collectUrls`

---

### `core/StatDayManager.php` · `StatDayManager`

**干什么：** 控制台按日调用聚合（statday，滚动固定 30 天）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `table` · `tableReady` · `resetReadyCache` · `recordHit` · `ensureDay` · `pruneOld` · `backfillLastDays` · `getDay` · `todayRow` · `todayCalls` · `todayOkFail` · `mapLastDays` · `sumCallsBetween` · `topListFromJson`

---

### `core/SystemInfo.php` · `SystemInfo`

**干什么：** 服务器与运行环境信息（关于页面）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `collect`

---

### `core/ThemeManager.php` · `ThemeManager`

**干什么：** 前台主题发现、切换与模板渲染

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `themesRoot` · `activeId` · `themeDir` · `isValidTheme` · `listThemes` · `readMeta` · `previewUrl` · `setActive` · `isThemeEnabled` · `themeDataFile` · `readAllThemesettings` · `writeAllThemesettings` · `syncThemesettingsEntries` · `readThemeData` · `writeThemeData` · `clearThemeSettingCache` · `themeSetting` · `themeSettingStr` · `themeSettingBool` · `themeSettingInt` · `getSettingsSchema` · `sanitizeThemeSettingsInput` · `navItems` · `userMenuGroups` · `resolveActiveThemeFile` · `resolveThemeFile` · `userStylesheetHrefs` · `authStylesheetHrefs` · `authScriptHref` · `userScriptHref` · `ensureAuthLayoutLoaded` · `renderThemeAuthHead` · `renderThemeAuthFoot` · `renderUserLayoutStart` · `renderUserLayoutEnd` · `renderAuthPage` · `renderUserPage` · `assetUrl` · `shellUrl` · `pageScriptUrl` · `frontendShellCssHrefs` · `frontendShellJsHrefs` · `userShellCssHrefs` · `userShellJsHrefs` · `defaultFrontendAssets` · `activeStylesheetHref` · `activeScriptHref` · `frontendPageJsHrefs` · `renderBody`

---

### `core/UpdateLog.php` · `UpdateLog`

**干什么：** 读取版本更新记录（升级页「更新记录」等）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `clearResolvedCache` · `localPath` · `loadLocal` · `isValidLogPayload` · `remoteUrl` · `remoteUrls` · `resolveRepoBranch` · `fetchRemote` · `loadData` · `getSource` · `allVersions` · `getVersion` · `nextVersionAfter` · `countVersionsAfter` · `versionHasDbChanges` · `payloadForApi` · `rangeHasDbChanges`

---

### `core/Updater.php` · `Updater`

**干什么：** ApiNexus 在线更新（云端版本检测与更新包应用）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `localVersion` · `updateDir` · `writeDenyHtaccess` · `checkForUpdate` · `clearCheckCache` · `applyUpdateStep` · `schemaMaintainInfo` · `runSchemaFullAlignNow` · `runSchemaMigrateNow` · `applyUpdate` · `databaseConfigPath` · `databaseConfigFingerprint` · `assertDatabaseConfigUnchanged` · `updateMirrors` · `fetchRemoteManifest` · `buildUpdatePackageUrls` · `buildReleasePackageUrl` · `isTrustedUpdateUrl` · `isValidZipFile` · `getLastError` · `configureCurlSsl` · `httpGet` · `downloadFile` · `protectedRelativePaths` · `loadObsoleteRelativePaths` · `isSafeZipEntryName` · `sanitizeObsoletePaths` · `removeObsoleteFiles` · `isImmutablePath` · `detectExtractRoot` · `looksLikeProjectRoot` · `isOptionalUpdatePath` · `copyFileSafe` · `copyTree` · `isProtectedPath` · `cleanupUpdateWorkspace` · `cleanupPaths` · `removeDir`

---

### `core/UserAuth.php` · `UserAuth`

**干什么：** 用户认证、登录态管理、注册与密码重置

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `login` · `logout` · `touchActivity` · `isSessionExpired` · `check` · `id` · `requireLogin` · `redirectIfLoggedIn` · `user` · `verifyCredentials` · `isBannedAccount` · `loginById` · `register` · `resetPasswordById` · `findByEmail` · `checkRegisterDuplicate` · `updateAccount`

---

### `core/UserAvatar.php` · `UserAvatar`

**干什么：** 用户头像解析（QQ 邮箱 / 自定义链接 / 本地随机）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `resolve` · `resolveByEmail` · `extractQqFromEmail` · `localRandomAvatar` · `defaultAvatar` · `localAvatarFiles`

---

### `core/UserCallStats.php` · `UserCallStats`

**干什么：** 公开「个人调用/积分」只读查询（供 api/index.php 等本地接口）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `allFieldKeys` · `codeMap` · `parseFromRequest` · `parseFields` · `resolveUserFromRequest` · `query`

---

### `core/UserDashHello.php` · `UserDashHello`

**干什么：** 用户控制台按时段问候（双主题共用；按小时 24 槽；每次随机一条）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** `pick`

---

### `core/UserIpAllow.php` · `UserIpAllow`

**干什么：** 用户调用 IP 白名单（空=不限制；仅对「密钥必须」接口硬拦）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `columnReady` · `rawForUser` · `parseList` · `normalizeIp` · `serializeList` · `adminOverview` · `adminFlatAllowList` · `checkUser` · `ipInList` · `saveList` · `addIp` · `removeIp`

---

### `core/UserIpProxy.php` · `UserIpProxy`

**干什么：** 用户自备出口 IP 代理（隧道 / 提取）；每用户最多 5 条

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `publicTestUrls` · `parseVsproxyValue` · `noteRequestFlags` · `normalizeProxyCode` · `generateProxyCode` · `backfillMissingProxyCodes` · `regenerateLegacyProxyCodesToFive` · `tableReady` · `strategyColumnReady` · `requestWantsEgress` · `requestProxyCode` · `requestStrategy` · `requestProxyId` · `truthyFlag` · `strategyForUser` · `saveStrategy` · `listForUser` · `adminFlatList` · `findForUser` · `formatPublicRow` · `protoLabel` · `modeLabel` · `strategyLabel` · `save` · `delete` · `sanitizeJsonPath` · `resolveJsonPath` · `isValidProxyHost` · `pinProxyEndpoint` · `isAllowedProxyEndpoint` · `resolveEndpoint` · `materializeEndpoint` · `pullFromExtract` · `detectExtractVendorFailure` · `parseExtractBody` · `applyEndpointToCurl` · `applyToCurl` · `armRequestEgress` · `disarmRequestEgress` · `isRequestEgressArmed` · `requestEgressHostPort` · `setStatus` · `testConnectivity`

---

### `core/UserManager.php` · `UserManager`

**干什么：** 管理员用户列表查询

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `all` · `attachListStats` · `findByAccount` · `findById` · `count` · `setStatus` · `setRole` · `delete` · `exists`

---

### `core/UserRole.php` · `UserRole`

**干什么：** 用户角色常量、校验与权限判断

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `normalize` · `label` · `canPublishApi` · `currentCanPublishApi` · `allLabels`

---

### `core/UserStat7Manager.php` · `UserStat7Manager`

**干什么：** 用户近 7 日调用聚合（user.stat7 JSON，按日分桶）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `hasColumn` · `resetColumnCache` · `recordHit` · `dashboardSlice`

---

### `core/bootstrap.php`

**干什么：** 见源码文件头

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/captcha/gt3/CheckGeetestStatus.php` · `CheckGeetestStatus`

**干什么：** 极验 3 代云状态检测（官方 bypass；无 Redis 时用 session 缓存）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `getGeetestStatus`

---

### `core/captcha/gt3/GeetestLib.php` · `GeetestLib`

**干什么：** 见源码文件头

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `gtlog` · `localInit` · `register` · `successValidate` · `failValidate` · `sha256_encode`

---

### `core/captcha/gt3/GeetestLibResult.php` · `GeetestLibResult`

**干什么：** 见源码文件头

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `getStatus` · `setStatus` · `getData` · `setData` · `getMsg` · `setMsg` · `setAll` · `__toString`

---

### `core/captcha/gt4/LoginController.php` · `Geetest4Login`

**干什么：** 极验 4 代二次校验（官方流程 + 本站安全加固：fail-closed / HTTPS / 域名白名单）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `validate` · `normalizeApiServer`

---

### `core/captcha/helper.php`

**干什么：** 认证页验证码挂载与脚本输出

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/captcha/image.php`

**干什么：** 本地图形验证码 PNG（含频率限制；按场景方式判定）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/captcha/local.php` · `CaptchaLocal`

**干什么：** 本地图形验证码（GD；session 存场景绑定哈希；含基础强度）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `makeCode` · `outputPng` · `verify`

---

### `core/captcha/register.php`

**干什么：** 极验 3 代初始化（官方 first_register + 频率限制）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/api/apilogarchive.php`

**干什么：** 调用日志清理 HTTP API（冷热归档或过期直接删除；须系统密钥 + 后台开关）；内部 `ApiLogArchive::runScheduled()`

**主题：** **禁止**调用；由 crontab / 运维 HTTPS 调用

**正式 URL：** `/core/api/apilogarchive.php`（旧 `/core/cron/apilogarchive.php` 已删除；升级后由 `obsolete-files` 清理）

**公开方法：** （入口脚本）

---

### `core/front/catalog.php`

**干什么：** 前台公开接口目录（POST + CSRF）；首页/apis 首屏不灌大包，一次拉取后本地筛选

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** （入口脚本或无 public API）

---

### `core/front/playground-key.php`

**干什么：** 前台在线测试按需拉取当前用户启用 KEY（POST + CSRF；禁止 SSR 明文进 HTML）

**主题：** 可按正文约定调用（或经入口注入 / HTTP 窗口）

**公开方法：** （入口脚本或无 public API）

---

### `core/helpers.php`

**干什么：** ApiNexus 通用辅助函数

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/markdown/Markdown.php` · `Markdown`

**干什么：** Markdown + 扩展短码渲染（公告/文章/API 文档共用）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `render` · `assetPaths` · `renderAssetsHtml`

---

### `core/markdown/Parsedown.php` · `Parsedown`

**干什么：** 见源码文件头

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `line`

---

### `core/oauth/HttpClient.php` · `OAuthHttpClient`

**干什么：** OAuth 相关 HTTP 请求（PHP 7.4+）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `get` · `postForm`

---

### `core/oauth/OAuthConfig.php` · `OAuthConfig`

**干什么：** OAuth 配置读写（存于 config.oauth_config JSON）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `getAll` · `getProvider` · `isEnabled` · `defaults` · `save` · `callbackUrl`

---

### `core/oauth/OAuthService.php` · `OAuthService`

**干什么：** OAuth 聚合登录编排（仅已注册用户可绑定/登录）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `authorizeUrl` · `enabledProviders` · `handleCallback` · `bindPendingToAccount` · `bindUser` · `findUserByIdentity` · `bindingsForUser` · `unbindUser` · `validateBindStart` · `getBindPending` · `clearBindPending`

---

### `core/oauth/OAuthState.php` · `OAuthState`

**干什么：** OAuth state 防 CSRF（HMAC 签名，不依赖 Session 存取）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `create` · `peek` · `consume`

---

### `core/oauth/gitee/GiteeOAuth.php` · `GiteeOAuth`

**干什么：** Gitee OAuth2.0

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `authorizeUrl` · `fetchIdentity`

---

### `core/oauth/qq/QQOAuth.php` · `QQOAuth`

**干什么：** QQ 互联 OAuth2.0（网站应用）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `authorizeUrl` · `fetchIdentity`

---

### `core/ping.php`

**干什么：** 检测指定主机 TCP 连通耗时（供前台接口卡片延迟展示）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/play/codeplay/CodePayClient.php` · `CodePayClient`

**干什么：** 码支付（易支付协议）签名、下单、验签

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** `sign` · `verify` · `create`

---

### `core/play/codeplay/notify.php`

**干什么：** 码支付异步回调（无需登录；先验签再履约）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/play/codeplay/return.php`

**干什么：** 码支付浏览器回跳（履约以 notify 为准）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/playground/media.php`

**干什么：** 在线测试媒体预览（短时落盘文件，同源播放 video/img/audio）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/playground/relay.php`

**干什么：** 前台在线测试同源中继入口（POST + CSRF + IP 频控）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---

### `core/version.php`

**干什么：** 定义当前系统版本号（云端更新比对用）

**主题：** 一般**不要**在主题里直接调用；由入口/后台使用

**公开方法：** （入口脚本或无 public API）

---



