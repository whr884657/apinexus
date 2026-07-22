# ApiNexus 7.1.0 发行说明

**版本：** 7.1.0  
**日期：** 2026-07-23（含二次补发）  
**数据库：** 有结构变更（`content` / `coverlayout`）

## 重要（请先读）

若你曾把 `core/version.php` **手动回退**再升级，或更新后没有 `content` 表 / 没有 `coverlayout` 字段：

1. 刷新后台任意页（自动清理高于代码版本的 `schema_migrations`）
2. 打开 **系统升级 → 执行数据库结构更新**
3. 或重新在线更新；**请使用本补发 ZIP**（旧 7.0.0 跳板包已同步修复）

## 变更摘要

- 内容运营打磨、封面布局、分类转移、Markdown 主题色等
- 更新机制：prune / forceMigrateRange / 结构校验（E81/E83）
- 公告「不再提示」；视频短码插槽渲染
- 分类删除弹窗内 vs-pick 不再被遮挡

下载：https://gitee.com/xunjinlu/apinexus/releases/download/v7.1.0/apinexus7.1.0.zip
