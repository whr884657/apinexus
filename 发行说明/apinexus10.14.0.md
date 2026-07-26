# ApiNexus 10.14.0

**发布日期：** 2026-07-26  
**数据库变更：** 否

## 本版摘要

精简 Markdown 扩展能力，修复详情页文档渲染，并将默认主题「AI 文档」改为**快速上手**多语言调用示例。

## 主要变更

| 模块 | 说明 |
|------|------|
| Markdown | 去掉居中、首行缩进；引用无背景、引号贴角；标题加粗加黑；列表正常显示；代码块可复制 |
| 默认主题详情 | 详细文档标题修复；快速上手：cURL / TypeScript / Browser / Python / Go / Java / PHP / C++ / C / Rust |
| 后台文案 | 「详细文档」「代码示例」（字段仍为 `doc` / `aidoc`） |

## 升级注意

1. 覆盖更新即可，**无需**执行数据库结构更新。
2. 旧文档中的 `:::indent` 将不再渲染为缩进组件（按普通文本处理）。

## 下载

- ZIP：`apinexus10.14.0.zip`
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.14.0
