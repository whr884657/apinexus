-- 13.26.35：出口代理短码由三位升为五位（0-9a-z）；旧码由 PHP 幂等重置
ALTER TABLE `{prefix}ipproxy`
    MODIFY COLUMN `proxycode` char(5) NOT NULL DEFAULT '' COMMENT '调用短码（五位随机0-9a-z；vsproxy仅认此码，不认数字主键）';
