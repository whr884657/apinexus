-- ApiNexus 7.1.0：文章封面布局字段

ALTER TABLE `{prefix}content`
  ADD COLUMN `coverlayout` tinyint(1) NOT NULL DEFAULT 0 COMMENT '封面布局：0左侧 1右侧 2背景（仅文章）' AFTER `cover`;
