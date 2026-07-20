# ApiNexus 5.1.1 发行说明

**版本：** 5.1.1  
**日期：** 2026-07-20  
**类型：** 小版本（首页随机 + Deprecated 修复）

## 变更

1. **首页接口目录纯随机**
   - 默认主题首页「接口目录」卡片改为 Fisher–Yates 纯随机，取前 8 条
   - 移除 `FrontendApi::listForTheme()` 与首页 JS 按 `calls` 排序（调用量排序不正确）

2. **PHP 8.1+ Deprecated 闪现修复**
   - `vs_seo_truncate()`：`preg_replace` 失败返回 null 时不再传给 `trim()`
   - SEO meta / notice 相关 `trim` 一律先转为字符串，消除页面刷新瞬间报错

## 升级

1. 覆盖代码文件  
2. 后台检查版本 → 5.1.1  
3. **无需**数据库结构更新  

## 数据库变更

否
