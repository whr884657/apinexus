# ApiNexus 10.10.1

## 本版要点

- **缓存**：控制台 `console_full` 整页快照（不含最近调用）；「今日调用」走 `cache:apilog:today_count`；Redis 监控可见 `cache:dashboard:*`
- **TTL**：控制台分层缓存约 8～300 秒，减轻首屏/软刷扫库
- **折线**：平滑曲线控制点钳制，不再下穿 X 轴；鼠标悬停显示当日数值
- **最近调用**：新增 HTTP 状态码列（200 / 403 / 401 等）；软刷不覆盖 live 列表
- **刷新**：图标按钮再缩小（约 28px）

## 升级注意

- 无数据库结构变更
- 强刷 `admin-dashboard.js` / `admin-dashboard.css`；确认 Redis 已启用时监控页能看到「控制台统计」
