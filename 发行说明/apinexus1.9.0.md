# ApiNexus 1.9.0 发行说明

**发布日期：** 2026-07-11  
**类型：** 大版本（前台主题系统 + 多页面 + 配置项）

---

## 变更摘要

### 前台主题系统

- 默认主题目录：`core/theme/default/`
- 结构：`layout/`（头尾侧栏）、`pages/`（页面内容）、`assets/theme.css`
- 页面 PHP（如 `index.php`）仅负责路由，通过 `ThemeManager` 动态加载当前主题模板
- 配置项：`vs_config.frontend_theme`（默认 `default`）

### 新增前台页面

| 页面 | 文件 |
|------|------|
| 首页 | `index.php` |
| 全部接口 | `apis.php` |
| 文章 | `articles.php` |
| 友情链接 | `links.php` |
| 赞助 | `sponsor.php` |
| 关于 | `about.php` |

### 顶栏响应式

- **电脑端**：Logo + 横向导航 + 登录/注册按钮
- **手机端**：左侧汉堡菜单展开侧边栏；底部「登录/注册」按钮（替代原「用户中心」文案）

### 后台

- **系统管理 → 主题设置**：可视化选择并保存前台主题

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v1.9.0/apinexus1.9.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

1. 备份数据库与 `config/`
2. 覆盖业务文件后访问 **系统升级**，执行 `1.9.0` 迁移（写入 `frontend_theme` 配置）
3. 新安装已自带默认主题与配置

---

*ApiNexus 1.9.0*
