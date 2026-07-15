# misc-api 3.16.0 发行说明

**发布日期：** 2026-07-15  
**类型：** 中版本（代理伪静态改面板兼容写法）

## 下载

- ZIP：https://gitee.com/xunjinlu/misc-api/releases/download/v3.16.0/misc-api3.16.0.zip
- 标签：`v3.16.0`

## 问题

此前伪静态写成：

```nginx
rewrite ... /apis.php/$1 last;
```

在宝塔等面板上，PHP 规则多为 `location ~ \.php$`（**必须以 .php 结尾**），内部 URI `/apis.php/短码` 匹配失败 → File not found / 404。  
浏览器地址即使是 `/apis/短码`，rewrite 目标错了仍然进不了 PHP。

## 正确伪静态（兼容原有去 .php）

```nginx
# 写在 location / 之前
location ~ ^/apis/([a-z0-9]{3,64})/?$ {
    rewrite ^/apis/([a-z0-9]{3,64})/?$ /apis.php?_vs_slug=$1 last;
}

# 你原来的规则保留
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

- 地址栏仍是美观的 `/apis/{短码}`  
- 内部落到普通 `apis.php?...`，能匹配面板 PHP  
- `_vs_slug` 仅内部使用，跳转上游时会剔除  
- `/apis` 列表仍走 `try_files`，不受影响

完整说明见发行包内 `nginx伪静态配置.md`。

## 升级说明

1. 更新至 v3.16.0（无库结构变更）  
2. **替换站点伪静态**：删掉错误的 `/apis.php/$1`，改用上方 `?_vs_slug=$1`  
3. 重载 Nginx，访问 `/apis/你的短码` 验证

## 库变更

无。
