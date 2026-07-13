# misc-api 3.2.0 发行说明

**发布日期：** 2026-07-13  
**版本类型：** 小版本（Redis 业务缓存 + UI 修复）  
**数据库变更：** 否

---

## 概述

本版本将 Redis 从「仅监控」升级为 **misc-api 业务缓存层**，减轻 MySQL 读取与发信限流写入压力；并重做 Redis 管理页与接口分类表格对齐问题。

---

## Redis 业务缓存

| 能力 | 说明 |
|------|------|
| 公开接口列表 | `ApiManager::listPublic()` 缓存 120 秒 |
| 前台分类标签 | `FrontendCategory::listTags()` 缓存 300 秒 |
| 发信限流 | `RateLimitStore` 优先 Redis INCR，不可用时回退 MySQL |
| 自动失效 | 分类/接口审核变更时 `RedisCache::invalidateFrontend()` |

新增 `core/RedisCache.php`。

---

## Redis 管理页

- 仅展示 **misc-api 业务缓存**（命中率、各缓存项、键数量、占用估算）
- 支持 **清空业务缓存**、**刷新**
- 服务器 INFO 收折为「参考信息」
- 手机端卡片式布局

---

## 接口分类修复

- **桌面端**：固定列宽，避免操作按钮溢出导致列错位
- **手机端**：卡片显示「接口数量：N」

---

## 下载

- Tag：`v3.2.0`
- 附件：`misc-api3.2.0.zip`
