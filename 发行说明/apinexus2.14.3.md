# ApiNexus 2.14.3 发行说明

**发布日期：** 2026-07-12  
**类型：** 小版本（补丁）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v2.14.3/apinexus2.14.3.zip
- 标签：`v2.14.3`

## 变更摘要

1. **Hero 首屏垂直居中**  
   v2.14.2 为修复并排重叠加入 `main-wrapper > section { display: block }`，误伤了 `.hero-section` 的 `display:flex`，首屏内容贴顶且 `min-height:100vh` 造成下方巨大留白。

2. **Hero 按钮与标题**  
   补全 Hero 区 `btn-geek` 样式；「在线测试」改用 ghost 变体；标题 `glitch-text` 改为 block 排版并取消 uppercase。

3. **首屏高度**  
   `min-height` 改为 `calc(100svh - 7rem)`，适配顶栏与公告条。

## 升级说明

无数据库变更，可直接在线更新或 ZIP 覆盖（保留 `config/database.php`）。
