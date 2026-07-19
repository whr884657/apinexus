# ApiNexus 2.17.0 发行说明

**发布日期：** 2026-07-13  
**类型：** 中版本（双主题前台 · 接口分类）  
**数据库变更：** 无

---

## 变更摘要

### 双主题 · 接口分类对接数据库

- **默认主题**与**主题二**首页、全部接口页的分类标签均从 `ApiCategoryManager` 读取已启用分类
- 「全部」后跟随数据库分类，**不显示分类图标**（纯文字标签）
- 分类标签 **自动换行**（取消主题二横向滑动）
- 默认最多显示 **15** 个分类，第 16 个起折叠，点击 **「更多」** 展开 / **「收起」** 还原

### 主题二 · 全部接口页重构

- 移除「公开接口 / 我的接口」占位卡片与「建设中」提示
- 顶部 **搜索框** + **分类标签** + 接口卡片网格（与默认主题能力对齐）
- 首页展示真实接口列表（搜索 + 分类筛选，预览 8 条）

### 后端优化

- `ApiCategoryManager::listEnabled()` 按 `sort_order ASC, id ASC` 排序，与后台排序一致
- 新增共享 `core/includes/theme-api-payload.php`，两主题共用接口/分类数据构建逻辑

---

## 升级说明

无需数据库迁移。更新后刷新前台即可看到数据库分类标签。

---

## 主要改动文件

| 文件 | 说明 |
|------|------|
| `core/includes/theme-api-payload.php` | 共享前台 apiData / categoryNames |
| `core/theme/default/pages/home.php` | 分类可见数 15 + 「更多」 |
| `core/theme/default/pages/apis.php` | 同上 |
| `core/theme/slate/pages/home.php` | 数据库分类 + 真实接口列表 |
| `core/theme/slate/pages/apis.php` | 搜索 + 分类 + 卡片列表 |
| `core/theme/slate/assets/theme.js` | 筛选 / 分页 / 展开逻辑 |
| `core/theme/slate/assets/theme.css` | 换行分类 + 接口卡片样式 |
| `core/ApiCategoryManager.php` | listEnabled 排序优化 |

---

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v2.17.0/apinexus2.17.0.zip
