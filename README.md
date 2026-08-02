<div align="center">
  <h1>APINEXUS</h1>
</div>

<p align="center">
  <strong>开放接口平台 · 自部署管理 · 云端在线更新</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-13.22.6-blue?logo=semver&logoColor=white" alt="version">
  <img src="https://img.shields.io/badge/License-MIT-green?logo=opensourceinitiative&logoColor=white" alt="License: MIT">
  <a href="https://gitee.com/xunjinlu/apinexus"><img src="https://img.shields.io/badge/Gitee-xunjinlu%2Fapinexus-red?logo=gitee&logoColor=white" alt="Gitee"></a>
  <a href="https://gitcode.com/xunjinlu/apinexus"><img src="https://img.shields.io/badge/GitCode-xunjinlu%2Fapinexus-orange?logo=git&logoColor=white" alt="GitCode"></a>
  <a href="https://github.com/whr884657/apinexus"><img src="https://img.shields.io/badge/GitHub-whr884657%2Fapinexus-blue?logo=github&logoColor=white" alt="GitHub"></a>
  <img src="https://img.shields.io/badge/PHP-7.4%20%7C%208.0%20%7C%208.2-purple?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-5.7%2B-blue?logo=mysql&logoColor=white" alt="MySQL">
</p>

---

## 项目简介

**ApiNexus** 是一套可自部署的**开放 API 接口平台**管理系统。基于 PHP + MySQL，无重型框架依赖，提供前台接口目录与在线调试、后台接口审核与分类管理，以及用户体系与云端在线更新。

**主要能力：**

- Web **六步**安装向导（伪静态 → 环境 → 数据库 → 建表 → 管理员 → 完成），自动创建数据表与初始配置；同机多站可配缓存键前缀
- **双端认证**：管理员后台（安装时创建）+ 用户中心（邮箱验证码注册 + QQ / Gitee 第三方登录）
- **API 管理**：后台接口列表 / 审核 / 分类 / 令牌 / 文档 / 反馈；用户中心开发者投稿与邮件通知
- **调用统计**：本地接口脚本可接入统计；代理短码访问自动记账（说明见 `api/统计代码使用说明.md`）
- **代理网关**：服务端中继上游；支持多种上游认证方式；可配置出站 UA / Referer；可选改写 JSON 响应字段；密钥不暴露给调用方
- **调用方密钥传递**：接口可多选 Query / Header / Bearer；站点名与系统名拆分；详情免责声明可主题开关
- **调色盘固定色**：登录 / 注册 / 忘记密码与后台使用系统色板（无可自定义取色、无昼夜自动切换）
- **行为验证**：系统设置可选本站图形或第三方验证；管理员与用户可分端选择
- **AI 代码示例**：按鉴权 × 语言分片生成；详细文档支持流式输出；代码示例支持单线程流式或多线程；可在系统设置中切换
- **快速上手**：多语言与多鉴权示例；切换鉴权方式后语言图标保持可用
- **实时数据监控中心**：地图飞线、四角实时面板（今日 / 累计 / 成功率等）、深浅色、双端自适应
- **控制台服务器监控**：对接常见面板；站点三名称与自定义页脚版权；文章编辑与 Markdown
- **用户令牌**：用户中心与管理员后台均可管理；每账号有数量上限；调用时校验并累计次数
- **积分计费与充值**：接口收费扣积分；用户扫码充值；订单与积分变动分栏管理
- **前台双主题**：默认主题 + 主题二；各主题样式与脚本完全独立（根目录静态资源仅服务管理员后台）；前台/用户中心按文件逐个加载本主题 CSS/JS；内置图标经核心统一出站；首页「累计调用」可在主题设置中选完整数字或单位转换
- 前台页面：首页、全部接口、文章、贡献者、友情链接、赞助、关于（导航支持伪静态）
- **友情链接 / 合作伙伴**：友链可审核与禁用；合作伙伴由管理员维护；默认主题首页可展示合作伙伴
- 分组侧边栏管理后台（控制台、数据大屏、API 管理、内容运营、交易财务、系统管理）
- 用户中心：控制台、API 管理（仅开发者）、令牌管理、积分变动、接口列表、账号设置
- **用户角色**：普通用户（调用接口、管理令牌）/ 开发者（可投稿接口待审）；注册可选身份，管理员可转换
- 用户管理：列表、搜索、封禁 / 解封 / 删除、身份转换
- 用户头像：邮箱匹配 / 自定义链接 / 默认头像
- 管理员：登录、忘记密码（邮箱验证码）；站点信息、注册邮箱后缀、SMTP 发信
- **站点扩展**：自定义底栏 HTML、站点运行时间、页脚二维码；主题可开关相关区块
- **SEO**：分享元数据与各页独立描述
- **云端在线更新**：后台检测新版本、分步下载安装、可选数据库结构迁移
- 认证页角色动画背景；后台大弹窗与 Toast；简洁白色后台，适配电脑与手机

### UI 规范（弹窗 / 布局）

- **手机端（≤900px）**：侧边栏默认隐藏，点击顶栏菜单从右侧滑出；点击遮罩关闭
- **电脑端（≥768px）**：侧边栏默认展开，可收缩
- **后台弹窗**：大尺寸弹窗——电脑端约 92% 视口居中，手机端全宽底部抽屉，内容区可滚动
- **参数类型选择**：嵌套选择器——电脑端弹窗中的弹窗、手机端抽屉中的抽屉；预设类型 + 自定义输入
- **认证页**：可交互角色动画背景，随前台主题联动

---

## 代码仓库与下载

| 平台 | 链接 | 说明 |
|------|------|------|
| **Gitee** | [xunjinlu/apinexus](https://gitee.com/xunjinlu/apinexus) | 主仓库 |
| **GitCode** | [xunjinlu/apinexus](https://gitcode.com/xunjinlu/apinexus) | 同步仓库 |
| **GitHub** | [whr884657/apinexus](https://github.com/whr884657/apinexus) | 同步仓库 |
| **发行版下载** | [Gitee Releases](https://gitee.com/xunjinlu/apinexus/releases) | 完整安装包 |

压缩包命名：`apinexus{版本号}.zip`（如 `apinexus13.22.3.zip`）。完整版本历史见 **[更新记录.md](更新记录.md)**。

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
├── detail.php                  # 接口详情（对外 /detail/{id}，伪静态 → ?id=）
├── profile.php                 # 开发者公开主页（对外 /profile/{id}）
├── api/                        # 本地业务接口（头部 ApiStats::hit(接口ID)）+ 统计代码使用说明.md
│   └── demo/                   # 演示包：aword.php（上游一言代理 + ApiStats 示例；勿与业务接口重名）
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
│   │   └── review.php / docs.php / feedback.php  # 审核/文档/反馈
│   ├── content/                # 内容运营（占位）
│   ├── finance/                # 交易财务（支付配置/订单/积分）
│   ├── system/                 # 系统管理扩展（日志等）
│   ├── index.php               # 控制台（KPI / 趋势 / TOP）
│   ├── screen.php              # 数据大屏（ECharts 飞线地图 / 四角实时面板）
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
│   ├── css/                    # 管理员后台 / 安装等系统样式（不含前台主题）
│   ├── js/                     # 管理员后台脚本等
│   └── img/                    # 内置图标、头像等（主题经核心类出站，勿在主题手写路径）
├── config/
│   ├── database.php            # 安装后生成（更新时不覆盖）
│   └── install.lock            # 安装锁定文件
├── core/
│   ├── bootstrap.php
│   ├── version.php             # VS_VERSION 版本常量
│   ├── ThemeManager.php        # 前台主题加载与切换；壳/页 CSS·JS 清单
│   ├── SiteMedia.php           # 内置图片统一出站 URL
│   ├── UserDashHello.php       # 用户控制台时段问候文案
│   ├── ApiManager.php          # 接口列表 CRUD 与状态
│   ├── ApiStats.php            # 本地/代理调用统计
│   ├── ApiProxy.php            # 代理网关 /apis/{短码}
│   ├── ProxyClientProfile.php  # 代理出站 UA/Referer 预设
│   ├── PlaygroundRelay.php     # 可选中继（兼容旧主题）
│   ├── ApiCategoryManager.php  # 接口分类（后台 CRUD）
│   ├── FrontendCategory.php    # 前台分类（主题调用）
│   ├── FrontendApi.php         # 前台公开接口（主题调用）
│   ├── FrontendUser.php        # 前台用户资料
│   ├── UserRole.php            # 用户角色与权限判断
│   ├── RedisService.php / RedisCache.php
│   ├── ApiLogManager.php / ApiLogArchive.php
│   ├── theme/default/          # 默认主题（含 assets/shell、assets/js）
│   ├── theme/slate/            # 主题二（结构同上，资源完全独立）
│   ├── Auth.php / UserAuth.php
│   ├── Updater.php             # 云端在线更新
│   ├── UpdateLog.php
│   └── DatabaseMigrator.php
├── data/                       # 运行时数据（更新临时文件等，自动创建）
└── install/
    ├── index.php               # 六步安装向导
    ├── database.sql            # 全新安装数据库结构
    └── migrations/             # 在线升级增量 SQL
```

**core 各 PHP 类详细说明、用法与主题对接规范见根目录 [CORE模块说明.md](CORE模块说明.md)。**

---

## 安装说明

1. 上传代码到 Web 服务器（或 `git clone` 后部署）
2. 确保 `config/` 目录可写
3. 创建 MySQL 空数据库
4. 访问 `https://你的域名/install/` 完成六步安装（先配 Nginx 伪静态）
5. 安装完成后访问 `/admin/login.php` 登录后台

---

## 伪静态 / URL 重写

### Apache

项目根目录 `.htaccess` 已含：`/apis/{短码}` 代理规则 + **通用** `/{页面}/{数字ID}` → `/{页面}.php?id=`。启用 `mod_rewrite` 即可。

### Nginx（宝塔「伪静态」请只粘贴英文规则，不要带中文注释）

**可直接粘贴（推荐完整）：**

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location ~ ^/([a-z0-9_-]+)/([0-9]+)/?$ {
    rewrite ^/([a-z0-9_-]+)/([0-9]+)/?$ /$1.php?id=$2 last;
}
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

若站点配置里已有 `location / { try_files ... }`，**只追加**上面的 `apis` + **通用路径**两段，并放在 `location /` **上面**。若仍留着旧的「仅 detail」单页规则，请删掉，改用通用第二段。  
完整规则（含 `/config` `/data` deny）见 [`nginx伪静态配置.md`](nginx伪静态配置.md) **情况 A**。

> **注意：** 不要写 `[a-z0-9]{3,64}` 或 `[0-9]{1,10}`。宝塔伪静态保存时会吞掉 `{…}`，导致 PCRE 报错。  
> **注意：** 不要 rewrite 到 `/xxx.php/$1`（PATH_INFO）；必须 `/$1.php?id=$2`。  
> **注意：** `apis` 段必须在通用段之上。  
> **路径式资源：** `/detail/11`、日后 `/article/5` 等共用**一条**通用规则，不必每页再加伪静态。

详情见 [`nginx伪静态配置.md`](nginx伪静态配置.md)。

---

## 在线更新

登录后台后会向**云端**检测最新版本。若本地版本低于云端，可在「系统升级」中安装；若本地已不低于云端，则提示无需更新。

**更新过程（分步进度）：**

1. 从云端下载资源包
2. 解压更新包
3. 覆盖系统文件（**绝不替换**站点数据库配置与安装锁定文件，**不覆盖**运行时数据目录）
4. 若该版本含数据库结构变更，则执行增量结构升级
5. 完成后自动清理更新临时文件

**服务器要求：** 支持解压更新包的 PHP 扩展、可写项目目录、可访问云端。

---

## 版本记录

> 此处**仅保留最新一条**版本记录；完整历史见 **[更新记录.md](更新记录.md)**。

### v13.22.7（2026-08-03）

- 默认主题个人主页去掉博客/贡献者按钮；未配置简介时拉取一言（纯随机）
- 个人主页接口卡：计费与 KEY 分标、标签深色可见；电脑端每行最多 4 卡
- 修复极验三/四代拼图滑块被 CSS 拉成长条；Nginx config/data deny 合并

更早版本请查看 [更新记录.md](更新记录.md)。

## 开源协议

本项目采用 **[MIT License](LICENSE)**（GitHub / Gitee 可识别的标准开源许可）。

中文补充说明与部署使用条款见 **[LICENSE.zh-CN.md](LICENSE.zh-CN.md)**（安装向导首次进入时须完整阅读并确认）。

### 您可以

- **学习研究**：阅读、学习本项目全部源码
- **个人使用**：在个人网站、项目中免费使用
- **商业使用**：在商业项目中免费使用
- **修改分发**：可修改代码并再发布（须保留版权声明与 MIT 许可全文）

### 作者声明（免责）

- 本项目按 **「原样（AS IS）」** 提供，**不提供任何明示或暗示的担保**
- 因使用、无法使用或依赖本项目而产生的任何直接、间接、附带、特殊或后果性损害（包括但不限于数据丢失、业务中断、安全漏洞、法律纠纷等），**作者不承担任何责任**
- 使用者应自行评估安全风险，并在生产环境中做好备份、加固与合规审查
- 详细条款以仓库 **[LICENSE](LICENSE)** 与 **[LICENSE.zh-CN.md](LICENSE.zh-CN.md)** 为准

---

## 作者与仓库

- Gitee：[https://gitee.com/xunjinlu/apinexus](https://gitee.com/xunjinlu/apinexus)
- GitCode：[https://gitcode.com/xunjinlu/apinexus](https://gitcode.com/xunjinlu/apinexus)
- GitHub：[https://github.com/whr884657/apinexus](https://github.com/whr884657/apinexus)
- 问题反馈：可通过任一仓库 Issues 提交

---

> 维护者本地文档（README 编写要点、发版检查清单等）不随仓库发布，请在本地查阅。
