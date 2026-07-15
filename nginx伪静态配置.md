# misc-api Nginx rewrite

## Baota / panel — paste THIS only (no comments)

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
location / {
    try_files $uri $uri/ $uri.php$is_args$args;
}
```

If your site already has `location / { try_files ... }`, paste ONLY this:

```nginx
location ~ ^/apis/([a-z0-9]+)/?$ {
    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=$1 last;
}
```

Put it **above** the existing `location /` block (site config), or at the **top** of the rewrite file.

## Do NOT use

```nginx
# BAD: {3,64} is broken by Baota panel (strips braces) -> pcre missing )
location ~ ^/apis/([a-z0-9]{3,64})/?$ { ... }

# BAD: PATH_INFO style — many panels match only URI ending with .php
rewrite ... /apis.php/$1 last;
```

## Behavior

| URL | Result |
|-----|--------|
| `/apis` | list page via try_files |
| `/apis/slug` | proxy via `_vs_slug` |
| `/articles` | try_files as before |

Slug length is validated in PHP (3-64). Nginx only needs `[a-z0-9]+`.

## After paste

Reload nginx. Test: `/apis/your-slug`
