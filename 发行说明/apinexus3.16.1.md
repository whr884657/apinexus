# ApiNexus 3.16.1 发行说明

**日期：** 2026-07-15  
**类型：** 小版本（宝塔伪静态保存失败）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.16.1/apinexus3.16.1.zip

## 原因

宝塔「伪静态」保存时会吃掉正则里的 `{3,64}`，变成 `^/apis/([a-z0-9]`，Nginx 报：

`pcre_compile() failed: missing )`

## 请改用（只贴英文，勿带中文注释）

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

短码长度仍由 PHP 校验（3～64）。

## 库变更

无。
