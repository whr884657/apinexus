# ApiNexus 2.0.0 发行说明

**发布日期：** 2026-07-11  
**类型：** 大版本（双主题体系重构）

---

## 变更摘要

### 主题架构

- 移除共用 `assets/css/frontend.css`、`assets/js/frontend.js`
- 各主题独立：`core/theme/{id}/assets/theme.css` + `theme.js`
- 页面 PHP 路由不变，切换主题后前台即时变更

### 两套主题

| ID | 名称 | 特点 |
|----|------|------|
| `default` | 默认主题 | 浅色卡片、左侧抽屉、顶栏导航 |
| `slate` | 深岩主题 | 深色流式布局、右侧抽屉、底部 Dock |

### 前台页面顺序

首页 → 全部接口 → 文章 → **贡献者** → 友情链接 → 赞助 → 关于

### 默认主题优化

- 手机端：站点名称/图标居左，汉堡菜单居右
- 移除首页与侧栏冗余「注册账号」按钮（登录页已有「立即注册」）

### 后台主题设置

- 主题预览图（`assets/preview.svg`）
- 画廊卡片式选择 + 保存切换

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v2.0.0/apinexus2.0.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

- 无需数据库迁移
- 覆盖业务文件后，于后台 **主题设置** 选择主题并保存

---

*ApiNexus 2.0.0*
