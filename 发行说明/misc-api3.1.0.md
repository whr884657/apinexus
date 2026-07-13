# misc-api 3.1.0 发行说明

**发布日期：** 2026-07-13  
**版本类型：** 小版本（Redis 管理功能）  
**数据库变更：** 否

---

## 概述

Redis 管理页由占位升级为**实时监控面板**，并新增 `RedisService` 统一采集 Redis INFO。关于页环境信息中「当前域名」改为「Redis 版本」。

---

## Redis 管理页（`/admin/system/redis.php`）

| 模块 | 内容 |
|------|------|
| 连接信息 | 地址、db、键前缀、是否配置密码 |
| 核心指标 | Redis 版本、运行时长、内存占用/峰值、键总数、本系统前缀键数、命中率、客户端数 |
| 内存与性能 | RSS、maxmemory、使用率、碎片率、QPS、命令累计、命中/未命中、过期/驱逐 |
| 服务器 | 模式、角色、OS、架构、阻塞客户端、端口 |

支持 **刷新监控**（POST + CSRF + AJAX，静态更新 DOM）。

---

## 配置（可选）

在 `vs_config` 中可配置：

| 键 | 默认 |
|----|------|
| `redis_host` | `127.0.0.1` |
| `redis_port` | `6379` |
| `redis_password` | 空 |
| `redis_database` | `0` |
| `redis_prefix` | `misc_api:` |

---

## 下载

- Tag：`v3.1.0`
- 附件：`misc-api3.1.0.zip`
