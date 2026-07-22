# ApiNexus 5.8.0 发行说明

**版本：** 5.8.0  
**日期：** 2026-07-22  
**类型：** 中版本（日志查询性能架构改造）  
**数据库：** 有结构变更（复合索引 + 配置项）

## 变更

- 后台「日志查询」默认只查近 N 天（默认 7，可调至 90），杜绝无条件全表 `COUNT(*)`
- `COUNT` 不再 `LEFT JOIN` 用户表；无筛选时总数走 Redis 短缓存
- 翻页改为 keyset（`before_id`），避免深页 `OFFSET` 扫行
- 前端加载互斥 + `AbortController`，减少 F5/连点叠请求
- 查询会话尽量设置 `MAX_EXECUTION_TIME`；过期日志按保留天数分片清理
- 系统设置 → API 日志：可配默认查询天数、保留天数；复合索引写入迁移

## 升级注意

1. 后台执行 **数据库结构更新**（`install/migrations/5.8.0.sql`）
2. 大表首次加索引可能耗时，建议低峰操作
3. 下载：https://gitee.com/xunjinlu/apinexus/releases/download/v5.8.0/apinexus5.8.0.zip
