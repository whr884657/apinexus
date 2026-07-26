# ApiNexus 10.14.1

**发布日期：** 2026-07-27  
**数据库变更：** 是（字段注释，须执行结构更新）

## 本版摘要

补齐默认主题详情「快速上手」横滑语言 Tab（图标 + 名称），点击切换对应示例；同步 `doc` / `aidoc` 库注释与类说明。

## 主要变更

| 模块 | 说明 |
|------|------|
| 快速上手 | PHP 渲染 Tab；cURL / TypeScript / Browser / Python / Go / Java / PHP / C++ / Rust |
| 图标 | `assets/img/lang/`；未选中灰图、选中彩图；cURL 仅一份（未选中灰度） |
| 数据库 | `doc`→详细文档，`aidoc`→代码示例（注释）；迁移 `10.14.1.sql` |

## 升级注意

1. 覆盖后请在后台「系统升级」执行**数据库结构更新**（仅改注释，数据不变）。
2. 强刷浏览器缓存以加载新图标与 CSS。

## 下载

- ZIP：`apinexus10.14.1.zip`
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.14.1
