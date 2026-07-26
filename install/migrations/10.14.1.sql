-- ApiNexus 10.14.1：doc / aidoc 字段注释更名（详细文档 / 代码示例）
ALTER TABLE `{prefix}api`
  MODIFY COLUMN `doc` mediumtext COMMENT '详细文档（Markdown）',
  MODIFY COLUMN `aidoc` mediumtext COMMENT '代码示例（Markdown）';
