-- ApiNexus 3.15.0
-- 代理公开地址恢复为美观路径：/apis/{proxyslug}（须配合伪静态；/apis.php/短码 仍可用）

UPDATE `{prefix}api`
SET `endpoint` = CONCAT('/apis/', `proxyslug`)
WHERE `apitype` = 1
  AND `proxyslug` <> '';
