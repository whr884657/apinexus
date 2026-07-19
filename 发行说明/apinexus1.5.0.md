# ApiNexus 1.5.0 发行说明

**发布日期：** 2026-07-11  
**类型：** 大版本（跨页面表单安全与交互规范）

---

## 变更摘要

本版本系统性解决 **POST 后整页停留** 导致浏览器刷新时「重新发送表单」的问题，并统一后台改数据请求的 **CSRF + 同源** 校验。

### 用户管理

- 封禁 / 解封 / 删除改为 **AJAX**，操作后 **不刷新整页**，仅更新列表行/卡片状态
- 删除仍保留二次确认（VsModal）

### 账号设置

- 用户中心与管理员 **账号保存** 改为 AJAX（`data-ajax="1"`）
- 第三方 **OAuth 解绑** 改为 AJAX，解绑后局部更新绑定状态与按钮

### 系统设置

- 所有 POST 入口增加 `vs_require_secure_post()`（CSRF + 同源）
- 前端统一使用 `VS.postForm()` 自动附带 CSRF

### 公共能力

- `core/helpers.php`：`vs_require_secure_post()`
- `assets/js/common.js`：`VS.postForm()`、`VS.ensureCsrf()`、`VS.showMessage()`
- 用户中心布局输出 `window.VS_CSRF_TOKEN`

### 文档（本地维护，不进仓库/ZIP）

- 新增《请求与表单规范.md》：PRG、AJAX、CSRF 检查清单

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v1.5.0/apinexus1.5.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

- **无需数据库迁移**（`db_changes: false`）
- 自 v1.4.x 在线升级或直接覆盖业务文件即可
- 升级后建议清浏览器缓存，确保加载新版 `common.js`、`users.js`、`account.js`

---

*ApiNexus 1.5.0*
