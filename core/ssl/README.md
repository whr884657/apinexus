# 出站 TLS CA 包（可选）

官方出站 HTTPS 走 `vs_curl_ssl_verify()` / `vs_stream_ssl_options()`，**必须校验证书**。

CA 查找顺序：

1. php.ini `curl.cainfo`
2. php.ini `openssl.cafile`
3. 本目录 `cacert.pem`（若存在）
4. 以上都没有：仍开启校验，交给 PHP/cURL 默认信任库；失败即失败，没有全局关闭开关

**禁止**对 `/etc/ssl/certs/`、`/etc/pki/` 做 `is_file` 探测（E294 / 面板 `open_basedir`）。

发行包默认可以不带 `cacert.pem`。若目标机 php.ini 未配 CA、且校验失败，把 [curl.se CA 包](https://curl.se/ca/cacert.pem) 放到本目录并命名为 `cacert.pem`。
