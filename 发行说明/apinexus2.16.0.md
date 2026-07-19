# ApiNexus 2.16.0 发行说明

**发布日期：** 2026-07-13  
**类型：** 中版本（功能改版 + 数据库迁移）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v2.16.0/apinexus2.16.0.zip
- 标签：`v2.16.0`

## 变更摘要

1. **修复无刷新操作**  
   接口分类添加、编辑、删除、启禁后即时更新列表，无需手动刷新。

2. **UI 重构**  
   - 去除内容区重复「接口分类」标题与背景板  
   - 卡片式列表 + 搜索框  
   - 右上角「+ 添加分类」按钮  

3. **添加/编辑弹窗**  
   - 电脑：屏幕居中弹窗  
   - 手机：底部抽屉（约 80% 屏高）  
   - 字段：图标、名称、描述（已移除排序）  

4. **分类图标**  
   - 内置 8 款 SVG（`assets/img/category-icons/`）  
   - 支持自定义 HTTPS 图片链接  

5. **数据库**  
   - `category` 表新增 `icon`、`description` 字段  
   - 迁移：`install/migrations/2.16.0.sql`  

## 升级说明

1. 覆盖或在线更新至 v2.16.0  
2. **系统管理 → 系统升级**，执行数据库结构更新  
3. 进入 **API 管理 → 接口分类** 验证添加/删除是否即时生效  

## 相关文件

| 路径 | 说明 |
|------|------|
| `admin/api/categories.php` | 分类页模板 |
| `assets/js/api-categories.js` | 交互逻辑 |
| `core/ApiCategoryManager.php` | 分类业务 |
| `install/migrations/2.16.0.sql` | 字段迁移 |
