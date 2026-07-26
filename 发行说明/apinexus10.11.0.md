# ApiNexus 10.11.0

## 本版要点

- **关于页**：可在「发布文章」时绑定关于页；绑定文章不进文章列表；关于页展示该文 Markdown
- **双主题**：默认主题与主题二（slate）关于页均走 `FrontendAbout`；绑定正文加载 Markdown 样式表
- **文章管理**：隐藏/显示、状态色标、操作钮间距与语义色
- **公告管理**：置顶/弹窗标签配色、按钮间距
- **友情链接**：待审核 / 已通过 / 已禁用 分栏；通过后不可拒绝，只可禁用或删除
- **控制台**：刷新钮 26×26 正方形

## 升级注意

- **有数据库结构变更**：须执行「数据库结构更新」（`content.bindpage`）
- 强刷 `admin-content.js`、`admin-links.js`、`admin.css`、`admin-dashboard.css` 及主题 `about.php`
