# ApiNexus 2.14.1 发行说明

**发布日期：** 2026-07-12  
**类型：** 小版本（补丁）— 布局修复、Logo 绑定、移除未请求功能

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v2.14.1/apinexus2.14.1.zip
- 标签：`v2.14.1`

## 变更摘要

1. **主题一布局修复**  
   v2.14.0 迁入参考 UI 后，Tailwind 工具类未正确生效，导致 Hero、统计区、在线调试、合作伙伴等组件重叠错位。本版新增 `feer-compat.css` 作为布局兼容层，并优先从 CDN 加载 Tailwind（失败时回退本地 vendor）。

2. **站点 Logo**  
   顶栏不再使用固定闪电图标，改为读取后台「站点设置」中的自定义 Logo，与当前站点绑定。

3. **移除 api-proxy.php**  
   删除 v2.14.0 中未经需求确认的同源代理文件及所有引用；在线调试改为浏览器直连请求，跨域失败时提示用户使用 curl 或新窗口打开。

4. **默认主题版本**  
   `core/theme/default/theme.json` → v1.5.1

## 升级说明

- 无数据库变更，可直接在线更新或覆盖升级（保留 `config/database.php`）。
- 若已手动添加 `api-proxy.php`，可安全删除。

## 相关文件

| 文件 | 说明 |
|------|------|
| `core/theme/default/assets/css/feer-compat.css` | Tailwind 兼容与布局修正 |
| `core/theme/default/layout/header.php` | 站点 Logo 渲染 |
| `core/ThemeManager.php` | Tailwind CDN + 回退 |
| `core/theme/default/assets/js/pages/index.js` | 直连调试逻辑 |
