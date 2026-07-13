# misc-api 2.16.4 发行说明

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

从 `svg图标.txt` 解析并写入 `assets/img/category-icons/`：

| 文件 | 说明 |
|------|------|
| `netease.svg` | 网易云 |
| `qq-music.svg` | QQ音乐 |
| `honor-of-kings.svg` / `honor-of-kings-alt.svg` | 王者荣耀 |
| `business-license.svg` / `business-license-alt.svg` | 营业执照 |
| `id-card.svg` | 身份证 |
| `icp.svg` | 备案 |
| `car-dealer.svg` | 车商备案 |
| `map.svg` | 地图 |
| `weibo.svg` | 微博 |
| `express.svg` | 快递 |

内置图标由 23 款增至 **35 款**，已注册至 `ApiCategoryManager::defaultIconPaths()`。

### 维护工具

- `core/CategoryIconImporter.php`：PHP 解析 `名称:<svg>` 格式 txt
- `install/sync-category-icons.php`：执行 `php install/sync-category-icons.php` 同步图标

---

## 升级说明

无需数据库迁移。更新后后台「添加分类」弹窗图标选择器可见新图标。

若本地有新版 `svg图标.txt`，可运行：

```bash
php install/sync-category-icons.php
```

---

## 主要改动文件

| 文件 | 说明 |
|------|------|
| `assets/css/modal.css` | 弹窗标题/底部样式 |
| `core/ApiCategoryManager.php` | +12 图标路径 |
| `core/CategoryIconImporter.php` | 新增 |
| `install/sync-category-icons.php` | 新增 |
| `assets/img/category-icons/*.svg` | +12 文件 |
