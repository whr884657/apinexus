-- 13.12.0：代理接口 JSON 返回字段改写配置
ALTER TABLE `{prefix}api`
  ADD COLUMN `jsonrewrite` mediumtext NULL COMMENT '代理JSON改写规则（仅代理类型；空表示不改写）' AFTER `upreferer`;
