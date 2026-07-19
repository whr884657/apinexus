-- ApiNexus 3.14.0
-- 代理公开地址改为 PATH_INFO：/apis.php/{proxyslug}（不依赖 /apis/{短码} 伪静态）

UPDATE `{prefix}api`
SET `endpoint` = CONCAT('/apis.php/', `proxyslug`)
WHERE `apitype` = 1
  AND `proxyslug` <> '';
