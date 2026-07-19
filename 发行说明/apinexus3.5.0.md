# ApiNexus 3.5.0 发行说明

**发布日期：** 2026-07-14  
**版本类型：** 大版本增量（`3.4.x` → `3.5.0`，默认主题认证页整页视觉与动效统一）  
**数据库变更：** 否

---

## 概述

将默认主题（主题 1）登录、注册、忘记密码及 OAuth 绑定页与前台 Feer 风格对齐：左侧深色展区保留彩色角色小人，右侧玻璃表单与极客切角按钮；动效由本主题打包的 anime.js（纯前端库，无 CDN）驱动。

---

## 主要变更

| 项 | 说明 |
|----|------|
| 视觉 | 网格/青绿粒子氛围 + LIVE 角标；表单区玻璃卡、等宽 kicker、切角提交按钮 |
| 动效 | `assets/vendor/anime.min.js` + `auth.js`：入场错落、角色呼吸（独立 motion 层）、粒子背景 |
| 结构 | motion 层与角色 skew / 手机端 scale 分层，避免 transform 互相覆盖 |
| 安全 | 登录「记住用户名」不再把明文密码写入 localStorage（并清除旧版残留） |
| 多端 | 手机显示标题区与角色缩放；支持返回首页；尊重 `prefers-reduced-motion` |

涉及文件：`core/theme/default/assets/auth.css`、`auth.js`、`user/auth/*`、`assets/vendor/anime.min.js`。

---

## 升级说明

- 无数据库迁移，后台「系统升级」覆盖即可；请保留 `config/database.php`。
- 浏览器强刷或清缓存后再看登录页（静态资源带 `?v=3.5.0`）。

---

## 下载

- Tag：`v3.5.0`
- 附件：`apinexus3.5.0.zip`
- 直链：https://gitee.com/xunjinlu/apinexus/releases/download/v3.5.0/apinexus3.5.0.zip
