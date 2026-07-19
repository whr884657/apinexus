# ApiNexus 2.16.4 发行说明

**发布日期：** 2026-07-13  
**类型：** 小版本（UI + 图标资源）  
**数据库变更：** 无

---

## 变更摘要

### 弹窗 UI

- 标题栏：浅灰背景 `#f8fafc` + 底部分隔线，与表单正文明显区分
- 底部操作区：顶部分隔线；取消/保存按钮加大
  - 电脑端：`min-height 46px`，`min-width 128px`，字号 15px
  - 手机端：`min-height 48px`，字号 16px，双按钮等宽铺满

### 分类图标（+12）

内置分类 SVG 新增 12 款，合计 **35 款**（网易云、QQ音乐、王者荣耀、营业执照、身份证、备案、车商备案、地图、微博、快递等），位于 `assets/img/category-icons/`，已在 `ApiCategoryManager::defaultIconPaths()` 注册。

---

## 升级说明

无需数据库迁移。更新后后台「添加分类」弹窗图标选择器可见新图标。

---

## 主要改动文件

| 文件 | 说明 |
|------|------|
| `assets/css/modal.css` | 弹窗标题/底部样式 |
| `core/ApiCategoryManager.php` | +12 图标路径 |
| `assets/img/category-icons/*.svg` | +12 文件 |
