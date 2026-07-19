# ApiNexus 3.14.0 发行说明

**发布日期：** 2026-07-15  
**类型：** 中版本（代理网关 PATH_INFO 路径式纠偏）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v3.14.0/apinexus3.14.0.zip
- 标签：`v3.14.0`

## 变更摘要

1. **公开地址**  
   - 正确形态：`/apis.php/{短码}`（例：`/apis.php/sjspks`）  
   - 辅参：`/apis.php/sjspks?foo=1`  
   - **不再使用** `/apis/{短码}`（该地址在 Nginx `try_files` 下进不了 PHP，必然 404）

2. **入站逻辑**  
   - `/apis` 或 `/apis.php`（无路径段）→ 全部接口列表  
   - `/apis.php/{短码}`（PATH_INFO 有值）→ 代理跳转上游  
   - 去掉查询串 `?s=` 等兜底

3. **原理**  
   路径式参数挂在「真实存在的脚本文件」后面（`脚本.php/值`），由 PHP 读 `PATH_INFO`，**不依赖**把 `/apis/短码` 伪静态到脚本。

## 升级说明

1. 更新至 v3.14.0  
2. **系统管理 → 系统升级**，执行结构更新（`3.14.0`）  
3. 确认站点 PHP 处理支持 PATH_INFO（常见面板自带；若访问 `/apis.php/短码` 仍被 Nginx 判为找不到文件，需启用 `fastcgi_split_path_info`，见本地 `nginx伪静态配置.md`）  
4. 旧 `/apis/短码` 链接请改为 `/apis.php/短码`

## 库变更

| 项 | 说明 |
|----|------|
| `api.endpoint` | 代理类型更新为 `/apis.php/{proxyslug}` |
