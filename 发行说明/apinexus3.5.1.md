# ApiNexus 3.5.1 发行说明

**发布日期：** 2026-07-14  
**版本类型：** 小版本（UI 回退修复）  
**数据库变更：** 否

---

## 概述

恢复默认主题（主题 1）登录、注册、忘记密码及 OAuth 绑定页在 `3.5.0` 之前的原 UI：左侧彩色角色小人 + 右侧白色表单双栏布局，撤销 Feer 深色改版与 anime.js 动效。

---

## 变更

| 项 | 说明 |
|----|------|
| UI | 回退至 `3.4.3` 时代认证页样式与结构 |
| 资源 | 移除 `core/theme/default/assets/vendor/anime.min.js` |
| 动效 | 继续使用全局 `auth-characters.js` 角色交互 |

涉及：`auth.css` / `auth.js` / `user/auth/*`。

---

## 升级说明

- 无数据库迁移；在线升级或覆盖 ZIP 即可。
- 建议浏览器强刷认证页缓存（`?v=3.5.1`）。

---

## 下载

- Tag：`v3.5.1`
- 附件：`apinexus3.5.1.zip`
- 直链：https://gitee.com/xunjinlu/apinexus/releases/download/v3.5.1/apinexus3.5.1.zip
