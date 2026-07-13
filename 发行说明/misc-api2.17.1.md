# misc-api 2.17.1 发行说明

**发布日期：** 2026-07-13  
**类型：** 小版本（结构优化 + 分类展示修复）  
**数据库变更：** 无

---

## 变更摘要

### 取消两主题共用 payload

- 删除 `core/includes/theme-api-payload.php`
- **默认主题**：`core/theme/default/includes/api-payload.php` → `default_theme_page_payload()`
- **主题二**：`core/theme/slate/includes/api-payload.php` → `slate_theme_page_payload()`
- 各主题后续可独立调整，互不影响

### 分类始终展示（与接口数量无关）

- 新增 `ApiCategoryManager::frontendCategoryNames()`：输出全部 **已启用** 分类
- 某分类下暂无公开接口时，分类标签 **仍显示**
- 前台分类键改为数据库 **id**（不再用自增序号或从接口行动态补充分类名）

### 接口列表边界

- `apiData` 仍来自 `ApiManager::listPublic()`（仅已通过审核的公开接口）
- 用户侧接口提交等功能未上线时，列表可为空；**分类标签不受影响**

---

## 升级说明

无需数据库迁移。更新后刷新前台即可。

---

## 主要改动文件

| 文件 | 说明 |
|------|------|
| `core/ApiCategoryManager.php` | `frontendCategoryNames()` / `categoryNameToIdMap()` |
| `core/theme/default/includes/api-payload.php` | 默认主题独立 payload |
| `core/theme/slate/includes/api-payload.php` | 主题二独立 payload |

---

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v2.17.1/misc-api2.17.1.zip
