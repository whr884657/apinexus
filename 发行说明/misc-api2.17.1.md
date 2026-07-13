# misc-api 2.17.1 发行说明

**发布日期：** 2026-07-13  
**类型：** 小版本（前台分类统一调度 + 展示修复）  
**数据库变更：** 无

---

## 变更摘要

### core 统一调度（主题开发入口）

新增与 `Auth.php`、`ApiCategoryManager.php` 同级的 **前台专用 core 类**，主题只调用这些类，无需关心表名、字段名：

| 类 | 作用 |
|----|------|
| `core/FrontendCategory.php` | 前台分类标签：`listTags()`、`nameMap()`、`tagVisibleLimit()` 等 |
| `core/FrontendApi.php` | 前台公开接口：`listForTheme()` |

**主题用法示例：**

```php
foreach (FrontendCategory::listTags() as $tag) {
    // $tag['id']  $tag['name']
}
$apiData = FrontendApi::listForTheme();
```

后续主题三、用户自研主题均可直接调用，无需在主题目录写数据库逻辑。

### 两主题分类识别统一

- 「全部」键统一为 **`all`**（默认主题全部接口页原先为空字符串，已对齐）
- 各分类键统一为数据库 **id** 字符串
- 已启用分类 **始终显示**，与下属接口数量无关

### 清理

- 删除各主题 `includes/api-payload.php`
- 前台分类相关方法从 `ApiCategoryManager` 迁至 `FrontendCategory`（后台 CRUD 仍在 `ApiCategoryManager`）

---

## 升级说明

无需数据库迁移。更新后刷新前台即可。

---

## 主要改动文件

| 文件 | 说明 |
|------|------|
| `core/FrontendCategory.php` | 前台分类统一调度 |
| `core/FrontendApi.php` | 前台公开接口统一调度 |
| `core/bootstrap.php` | 加载上述类 |
| `core/theme/default/pages/*.php` | 调用 FrontendCategory / FrontendApi |
| `core/theme/slate/pages/*.php` | 同上 |

---

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v2.17.1/misc-api2.17.1.zip
