# ApiNexus 2.16.1 发行说明

**发布日期：** 2026-07-13  
**类型：** 小版本（UI 优化，无数据库变更）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v2.16.1/apinexus2.16.1.zip
- 标签：`v2.16.1`

## 变更摘要

1. **分类页布局**
   - 恢复白色背景板（`vs-panel`），页面顶栏仍显示「接口分类」
   - 去除内容区重复标题与「维护前台接口…」说明
   - 添加分类按钮在左、搜索框在右，同一行无多余空白

2. **搜索交互**
   - 搜索框有内容时，右侧出现「搜索」按钮
   - 空内容时按钮自动隐藏

3. **弹窗规范**
   - 按 `开发规范/弹窗开发规范.md` 重构为 `vs-overlay`
   - 电脑：居中弹窗 + 缩放动画
   - 手机：底部 80vh 抽屉 + 拖拽把手

4. **内置图标**
   - 23 款内置 SVG 替换旧版简易图标（图片、视频、AI、热榜等）

## 升级说明

覆盖或在线更新即可，**无需**数据库结构更新。

## 相关文件

| 路径 | 说明 |
|------|------|
| `admin/api/categories.php` | 页面结构 |
| `assets/js/api-categories.js` | 交互逻辑 |
| `assets/css/modal.css` | `vs-overlay` 基座样式 |
| `assets/img/category-icons/` | 内置 SVG 图标 |
