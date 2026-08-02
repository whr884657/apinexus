# ApiNexus · Nginx 伪静态说明

本文说明：**该复制哪一段、贴到宝塔哪里、和原来的「去 .php」怎么共存。**  
下面灰色代码框里是给 Nginx / 宝塔用的规则，**框内不要改、不要加中文**。

> **v13.21.0：** 安装向导第 1 步「伪静态配置」默认只提供下方 **情况 A** 全文，并支持一键复制。新装请直接用情况 A。

---

## 一、宝塔里贴哪里？

1. 打开：**网站 → 你的站点 → 设置 → 伪静态**（或「配置文件」里对应站点的 rewrite 片段）。  
2. 先看现有内容里有没有已经写好的：

```nginx
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

3. **推荐直接复制「情况 A」整段** → 保存 → **重载 Nginx**。  
4. 浏览器测试：`/apis/已有短码`（代理）；`/apis`（列表）；`/detail/数字ID`（详情）；日后 `/article/数字ID` 等同理，**无需再加规则**。

---

## 二、该复制哪一段？

### 情况 A：整段复制（**推荐 / 安装向导默认**）

整段复制下面：

```nginx
location ^~ /config/ {
    deny all;
    return 403;
}
location ^~ /data/ {
    deny all;
    return 403;
}
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location ~ ^/([a-z0-9_-]+)/([0-9]+)/?$ {
    rewrite ^/([a-z0-9_-]+)/([0-9]+)/?$ /$1.php?id=$2 last;
}
error_page 404 /404.php;
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

### 情况 B：伪静态 / 站点配置里**已经有**上面的 `location / { try_files ... }`

**只复制**下面几段，并保证它们出现在原来的 `location /` **上面**（写在文件更靠前的位置）；若尚无 `error_page 404`，一并补上：

```nginx
location ^~ /config/ {
    deny all;
    return 403;
}
location ^~ /data/ {
    deny all;
    return 403;
}
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location ~ ^/([a-z0-9_-]+)/([0-9]+)/?$ {
    rewrite ^/([a-z0-9_-]+)/([0-9]+)/?$ /$1.php?id=$2 last;
}
error_page 404 /404.php;
```

> **重要：** Nginx **不读** `.htaccess`。务必加上面的 `/config/`、`/data/` deny，否则运行时日志与数据库配置文件可能被直链下载。

若站点**已有**旧的「仅 detail」单页规则，请**删掉**那条，改用上面的**通用**第二段。

### 两条规则分别干什么？

| 顺序 | 规则 | 作用 |
|------|------|------|
| 1（必须在上） | `/apis/{短码}` | **代理网关**（特殊：内部 `_vs_slug`，不是 `?id=`） |
| 2 | `/{页面名}/{数字ID}` | **通用路径式资源**：落到 `/{页面名}.php?id={数字ID}` |

因此：

- `/detail/11` → `detail.php?id=11`
- `/article/5` → `article.php?id=5`（以后有文章详情入口即可，**不用再写伪静态**）
- 任意根目录入口脚本 `xxx.php`，只要出站写成 `/xxx/数字`，同一条规则生效

**不要**为每个新详情页再复制一条 `location`。

---

## 三、复制时注意（常见翻车）

1. **不要**在代码框内容里加中文注释，宝塔界面容易乱。  
2. **不要**写成 `[a-z0-9]{3,64}` 或 `[0-9]{1,10}` —— 宝塔保存时可能吃掉花括号，报 `pcre_compile() failed: missing )`。长度由 PHP 校验即可。  
3. **不要**写成 `/xxx.php/$1`（PATH_INFO）—— 面板 PHP 常只认「以 `.php` 结尾」的 URI。必须用 `/$1.php?id=$2`。  
4. **`apis` 段必须写在通用段上面**，否则纯数字短码可能被当成 `apis.php?id=`。  
5. 通用段只匹配「恰好两段且第二段为纯数字」；`/articles`、`/admin/login`、`/user/apis` 等不受影响。  
6. **码支付回调不要加伪静态**：`core/play/codeplay/notify.php` / `return.php` 直访（带 `.php`）。  
7. **必须配置 `error_page 404 /404.php;`**：否则乱路径只显示 Nginx 默认页，看不到本站美化 404。若 PHP-FPM 场景下仍是白页/默认页，在站点 `server` 里再加 `fastcgi_intercept_errors on;`（视面板而定）。

---

## 四、可选：安全响应头（Nginx）

PHP 前台已通过 `AuthSecurity` 下发安全头；若扫描仍报缺失（CDN 剥离 / 纯静态），可在**站点配置**（非伪静态框）的 `server { }` 内追加：

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()" always;
```

保存后重载 Nginx。Apache 站点已在根 `.htaccess` 做同等兜底。

---

## 五、地址对照（方便自测）

| 浏览器地址 | 作用 |
|------------|------|
| `/apis` | 全部接口列表（`try_files`） |
| `/apis/短码` | 代理外链（专用规则 → `_vs_slug`） |
| `/detail/数字ID` | 接口详情（通用规则 → `detail.php?id=`） |
| `/其它根入口名/数字ID` | 同类资源页（同一条通用规则） |
| `/core/play/codeplay/notify.php` | 码支付异步回调（直访，无伪静态） |
| `/articles` 等 | 去 `.php` 的 `try_files` |
| 不存在的路径 / 无效 ID | 本站 `404.php`（须配置 `error_page 404`；业务层也会直接调 `vs_render_404_page`） |

保存、重载后仍异常时：先确认伪静态已生效，再确认 **apis 在通用规则之上**、通用规则在 `location /` 之上，并确认已配置 **`error_page 404 /404.php`**。
