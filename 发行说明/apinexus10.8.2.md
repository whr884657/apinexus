# ApiNexus 10.8.2

## 本版要点

- **默认主题顶栏**：电脑端恢复「登录」「注册」按钮（根因：兼容层 `.hidden` 带 `!important`，且缺少 `md:inline-flex`）
- **双层 SEO**：根目录入口 `vs_page_seo_pack` + 主题 `vs_render_theme_seo_block` / head 内 JSON-LD 与 OG，便于微信/QQ/微博抓描述与图标
- **外链跳转弹窗**：「继续访问」浅色主题改为黑底白字，避免看不清

## 升级注意

- 无数据库结构变更
- 强刷默认主题静态资源（`feer-compat.css`、`external-link-modal.js`）
- 社交分享要出图：请在系统设置配置 **系统描述** 与 **Logo（≥300px PNG/JPG）**
