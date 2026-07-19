# ApiNexus 4.7.2

**发布日期：** 2026-07-20  
**下载：** [apinexus4.7.2.zip](https://gitee.com/xunjinlu/apinexus/releases/download/v4.7.2/apinexus4.7.2.zip)

## 变更说明

- **详情页绑错接口**：推荐卡 `foreach ($api)` 污染页面 `$api`，导致 `detailApiData` 变成随机推荐接口；已改为 `$cardApi` + 快照恢复
- **媒体可预览**：图/音/视频经中继落盘，返回同源 `core/playground/media.php` 供播放，不再「体积过大跳过」
- **POST + KEY**：参数一律拼进 Query；POST 使用 `application/x-www-form-urlencoded` 并显式 `Content-Length`，修复「未填密钥 / No Content Length」

## 升级说明

- 无数据库结构变更
- 需可写 `data/playground/`（运行时自动创建，gitignore）
