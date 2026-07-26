-- ApiNexus 10.17.0：调用密钥传递方式 + 站点/系统名称拆分
ALTER TABLE `{prefix}api`
  ADD COLUMN `keyways` varchar(64) NOT NULL DEFAULT 'query' COMMENT '调用密钥传递方式：query/header/bearer 逗号分隔' AFTER `needkey`;

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES
('system_name', 'ApiNexus');
