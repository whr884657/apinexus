# misc-api 2.14.2 发行说明

**发布日期：** 2026-07-12  
**类型：** 小版本（补丁）

## 下载

- ZIP：https://gitee.com/xunjinlu/misc-api/releases/download/v2.14.2/misc-api2.14.2.zip
- 标签：`v2.14.2`

## 问题与修复

v2.14.1 虽补充 Tailwind 兼容层，但首页仍错乱。根因是 `core/theme/default/assets/css/front-common.css` 中从 API 文档页误合并的规则：

```css
.main-wrapper { display: flex; ... }
```

首页 `<main class="main-wrapper">` 内多个 `<section>` 因此被当作 **横向 flex 子项** 挤在同一行，出现截图中的重叠。

**修复：**

1. 删除 `front-common.css` 中对 `.main-wrapper` 的全局 `display:flex`
2. `index.css` 明确 `.main-wrapper { display: block; width: 100%; }`
3. `feer-compat.css` 对 `body.feer-front .main-wrapper` 强制 block 纵向排版

## 升级说明

无数据库变更，可直接在线更新或 ZIP 覆盖（保留 `config/database.php`）。
