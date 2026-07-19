# ApiNexus 3.15.0 发行说明

**发布日期：** 2026-07-15  
**类型：** 中版本（代理公开地址去 .php，美观路径）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v3.15.0/apinexus3.15.0.zip
- 标签：`v3.15.0`

## 变更摘要

1. **对外地址（推荐）**  
   `/apis/{短码}`，例：`/apis/sjspks`  
   辅参：`/apis/sjspks?foo=1`

2. **兼容地址**  
   `/apis.php/{短码}` 仍然可用（PATH_INFO）

3. **伪静态（必配才能去 .php）**  
   `/apis/{短码}` → `/apis.php/{短码}`，并启用 PHP `PATH_INFO`  
   规则见发行包外本地说明 / 站点需同步的 Nginx、Apache 配置（`.htaccess` 已含 Apache 规则）

4. **列表与网关**  
   - `/apis` → 全部接口列表  
   - `/apis/{短码}` → 代理跳转上游

## 升级说明

1. 更新至 v3.15.0，执行结构更新 `3.15.0`  
2. **务必更新 Nginx**（或依赖 `.htaccess` 的 Apache）：增加 `/apis/{短码}` 重写，否则去 `.php` 地址仍会 404  
3. 测 `/apis/你的短码`；亦可测 `/apis.php/你的短码`

## 库变更

| 项 | 说明 |
|----|------|
| `api.endpoint` | 代理类型更新为 `/apis/{proxyslug}` |
