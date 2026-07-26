-- ApiNexus 10.13.0：代理上游认证（无需 / API Key / Bearer）
ALTER TABLE `{prefix}api`
  ADD COLUMN `upauth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '上游认证：0无需 1API Key 2Bearer Token' AFTER `proxyslug`,
  ADD COLUMN `upkeyvia` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'API Key传递：0URL参数 1请求头（仅upauth=1）' AFTER `upauth`,
  ADD COLUMN `upkeyname` varchar(64) NOT NULL DEFAULT '' COMMENT 'API Key参数名或头名（如api_key、X-API-Key）' AFTER `upkeyvia`,
  ADD COLUMN `upkey` varchar(500) NOT NULL DEFAULT '' COMMENT '上游密钥或Bearer令牌（仅服务端使用，不对外暴露）' AFTER `upkeyname`;
