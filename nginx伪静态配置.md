# misc-api · Nginx 伪静态（推荐完整片段）

> 本文件随发行包提供。先保留「去 .php」通用规则，再叠加代理短码规则。  
> **不要**写成 `/apis.php/$1`（PATH_INFO）：宝塔等面板的 PHP 规则常为 `location ~ \.php$`，只认以 `.php` 结尾，带路径段会直接 404。

```nginx
# ── 1) 代理美观地址（写在 location / 之前）─────────────────
# 浏览器：/apis/sjspks?foo=1
# 内部：  /apis.php?_vs_slug=sjspks&foo=1   （仍匹配面板的 *.php 规则）
location ~ ^/apis/([a-z0-9]{3,64})/?$ {
    rewrite ^/apis/([a-z0-9]{3,64})/?$ /apis.php?_vs_slug=$1 last;
}

# ── 2) 全站去 .php（你原来的规则，必须保留）────────────────
# /apis → apis.php（列表）；/articles → articles.php …
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}

# ── 3) PHP（面板一般已有；勿删）────────────────────────────
# location ~ \.php$ { ... fastcgi_pass ... }
```

## 行为对照

| 地址栏 | 走到哪 | 说明 |
|--------|--------|------|
| `/apis` | `try_files` → `apis.php` | 全部接口列表 |
| `/apis/sjspks` | rewrite → `apis.php?_vs_slug=sjspks` | 代理网关 |
| `/apis.php/sjspks` | 仅当面板支持 PATH_INFO 时可用 | 兼容；不作为推荐伪静态写法 |
| `/articles` | `try_files` → `articles.php` | 与代理规则互不影响 |

## 错误写法（不要用）

```nginx
# 错误：多数面板访问 /apis.php/短码 会 File not found
rewrite ... /apis.php/$1 last;
```

## 宝塔粘贴提示

1. 网站 → 设置 → 配置文件 / 伪静态  
2. **先**粘贴第 1 段 `location ~ ^/apis/...`  
3. **再**保留原有 `location / { try_files ... }`  
4. 保存并重载 Nginx  
5. 测：`https://你的域名/apis/已有短码`
