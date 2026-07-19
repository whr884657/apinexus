# ApiNexus 3.12.0 发行说明

**发布日期：** 2026-07-15  
**类型：** 中版本（代理公开地址改为路径样式、伪静态与入站解析统一）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v3.12.0/apinexus3.12.0.zip
- 标签：`v3.12.0`

## 变更摘要

1. **公开地址样式**  
   - 旧：`/proxy.php?s={短码}`  
   - 新：`/apis/{短码}`（例：`/apis/sjspks`）  
   - 附加参数仍用查询串：`/apis/sjspks?foo=1`

2. **入口合并**  
   - 移除根目录 `proxy.php`  
   - `apis.php`：无短码时展示「全部接口」列表；有短码时走外链网关跳转上游

3. **入站解析**（`ApiProxy::resolveSlugFromRequest`）  
   - 查询参数 `s`（兼容）  
   - `PATH_INFO` 首段  
   - `REQUEST_URI` 匹配 `/apis/{短码}` 或 `/apis.php/{短码}`

4. **伪静态**  
   - Nginx / Apache 增加 `/apis/{短码}` → `apis.php/{短码}` 规则  
   - 请同步更新站点伪静态配置

## 升级说明

1. 更新至 v3.12.0  
2. **系统管理 → 系统升级**，执行结构更新（`3.12.0`），将历史代理 `endpoint` 统一为 `/apis/{短码}`  
3. 更新 Nginx / Apache 伪静态（见发行包外本地说明或下方示例）  
4. 旧地址 `/proxy.php?s=` 不再提供；请改用 `/apis/{短码}`

### Nginx 要点（示例）

```nginx
location ~ ^/apis/([a-z0-9]{3,64})$ {
    rewrite ^/apis/([a-z0-9]{3,64})$ /apis.php/$1 last;
}
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
# PHP 处理须支持 PATH_INFO（fastcgi_split_path_info + PATH_INFO）
```

## 库变更

| 项 | 说明 |
|----|------|
| `api.endpoint` | 代理类型行更新为 `/apis/{proxyslug}`（无新列） |
