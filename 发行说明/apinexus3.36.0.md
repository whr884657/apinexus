# ApiNexus 3.36.0

## 变更说明

- **主题二 FAB**：右下角圆形按钮内三横线垂直居中（前台与用户中心）
- **主题设置**：电脑端左右分栏（左切换 / 右配置）；主题卡片网格不再拉伸出右侧空白
- **日志查询**：搜索、成功/失败筛选；电脑端列表、手机端小卡片；点击后抽屉查看详情
- **Redis**：
  - 修复「前台接口展示数据」从未写入（`FrontendApi::listForTheme` 现写入 `cache:frontend:api_list`）
  - 新增 API 调用日志分页轻量缓存（短 TTL，按筛选条件摘要键）

## 升级说明

- 无数据库变更
- 覆盖升级后请强刷后台与主题 CSS/JS（Ctrl+F5）
- 若已启用 Redis，访问前台接口列表页后可在「Redis 管理」看到「前台接口展示数据」变为已缓存

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.36.0/apinexus3.36.0.zip
