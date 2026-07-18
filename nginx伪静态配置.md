# misc-api · Nginx 伪静态说明

本文说明：**该复制哪一段、贴到宝塔哪里、和原来的「去 .php」怎么共存。**  
下面灰色代码框里是给 Nginx / 宝塔用的规则，**框内不要改、不要加中文**。

---

## 一、宝塔里贴哪里？

1. 打开：**网站 → 你的站点 → 设置 → 伪静态**（或「配置文件」里对应站点的 rewrite 片段）。  
2. 先看现有内容里有没有已经写好的：

```nginx
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

3. 按下面「情况 A / B」复制对应代码块 → 保存 → **重载 Nginx**。  
4. 浏览器测试：`https://你的域名/apis/已有短码`（应能跳转）；`/apis` 仍是接口列表。

---

## 二、该复制哪一段？

### 情况 A：伪静态里**还没有** `location / { try_files ... }`

整段复制下面（推荐）：

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location ~ ^/codepay/(notify|return)/?$ {
    rewrite ^/codepay/(notify|return)/?$ /codepay.php/$1 last;
}
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

### 情况 B：伪静态 / 站点配置里**已经有**上面的 `location / { try_files ... }`

**只复制**下面这一段，并保证它出现在原来的 `location /` **上面**（写在文件更靠前的位置）：

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location ~ ^/codepay/(notify|return)/?$ {
    rewrite ^/codepay/(notify|return)/?$ /codepay.php/$1 last;
}
```

---

## 三、复制时注意（常见翻车）

1. **不要**在代码框内容里加中文注释，宝塔界面容易乱。  
2. **不要**写成 `[a-z0-9]{3,64}` —— 宝塔保存时可能吃掉花括号，报 `pcre_compile() failed: missing )`。长度由 PHP 校验即可。  
3. **不要**写成 `/apis.php/$1`（PATH_INFO）—— 很多面板的 PHP 规则只认「以 `.php` 结尾」的路径，会 404。必须用 `apis.php?_vs_slug=$1`。  
4. 短码规则与原来的「全站去 .php」可同时存在：`/apis` 走列表，`/apis/短码` 走代理，`/articles` 等仍走 `try_files`。  
5. **接口详情页不需要伪静态**：出站/入站用 PATH_INFO（`/detail.php/{id}`），由 PHP 解析，见《查询串转路径样式规范》。

---

## 四、地址对照（方便自测）

| 浏览器地址 | 作用 |
|------------|------|
| `/apis` | 全部接口列表 |
| `/apis/短码` | 代理外链（内部用 `_vs_slug`，地址栏仍好看） |
| `/codepay/notify` | 码支付异步回调（亦可直接用 `/codepay.php/notify`） |
| `/codepay/return` | 码支付浏览器回跳 |
| `/detail.php/数字ID` | 接口详情（PATH_INFO，**无**伪静态规则） |
| `/articles` 等 | 和以前一样，走去 `.php` 的 `try_files` |

保存、重载后仍异常时：先确认伪静态已生效，再确认短码那段是否在 `location /` 之前。
