# ApiNexus 2.15.0 发行说明

**发布日期：** 2026-07-13  
**类型：** 小版本（功能新增，含数据库变更）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v2.15.0/apinexus2.15.0.zip
- 标签：`v2.15.0`

## 变更摘要

### 后台 · 接口分类

在 **API 管理** 菜单下新增 **接口分类** 页面（`/admin/api/categories.php`）：

- 添加 / 编辑 / 删除分类
- 启用 / 禁用（禁用后前台筛选不展示）
- 排序（数值越小越靠前）
- 显示各分类关联接口数量
- 重命名分类时自动同步更新 `api` 表中对应 `category` 字段
- 有关联接口的分类不可删除

### 数据层

- 新表 `{prefix}api_category`
- 新类 `core/ApiCategoryManager.php`

### 前台

- 接口目录分类标签优先读取后台启用的官方分类
- 接口记录中的未归类名称仍会追加显示

## 升级说明

1. 覆盖升级或在线更新（保留 `config/database.php`）
2. 进入 **系统管理 → 系统升级**，执行数据库结构更新（应用 `2.15.0` 迁移）
3. 在 **API 管理 → 接口分类** 中维护分类

## 相关文件

| 路径 | 说明 |
|------|------|
| `admin/api/categories.php` | 分类管理页 |
| `assets/js/api-categories.js` | 前端交互 |
| `core/ApiCategoryManager.php` | 分类 CRUD |
| `install/migrations/2.15.0.sql` | 数据库迁移 |
