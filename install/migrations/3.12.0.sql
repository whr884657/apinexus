-- misc-api 3.12.0
-- 代理公开地址统一为路径样式：/apis/{proxyslug}

UPDATE `{prefix}api`
SET `endpoint` = CONCAT('/apis/', `proxyslug`)
WHERE `apitype` = 1
  AND `proxyslug` <> '';
