# ApiNexus 2.3.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（主题完全隔离 + 青绿认证页）

---

## 变更摘要

### 主题严格隔离

- `ThemeManager::resolveActiveThemeFile()`：**不回退**其他主题；缺失文件时明确报错
- `assetUrl()` / `setActive()`：主题 ID 正则校验，拒绝 `..` 路径穿越
- 各主题顶栏、侧边栏、认证页 shell **必须在主题包内完整实现**

### 青绿平台（slate）

- 认证四页（login / register / forgot / bind）独立 `st-auth` 居中卡片视觉
- 新增 `assets/auth.css`、`assets/auth.js`；不再叠加 default 模板
- 用户中心独立 `user/layout.php`、`user.css`、`user.js`（青绿配色）

### 默认主题

- 认证页 `register` / `forgot` / `bind` 补全 `renderThemeAuthFoot()`
- `user.css` / `auth.css` 等资源仅来自 `core/theme/default/`

### 文档

- **[主题规范.md](../主题规范.md)**：新增「主题引入与渲染流程」、严格隔离与安全说明

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v2.3.0/apinexus2.3.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

- 无需数据库迁移
- 切换至青绿平台后，请确认 `core/theme/slate/user/auth/*.php` 均已部署

---

*ApiNexus 2.3.0*
