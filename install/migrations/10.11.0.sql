-- ApiNexus 10.11.0：文章可绑定关于页；隐藏态沿用 status=2
ALTER TABLE `{prefix}content`
  ADD COLUMN `bindpage` tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑定页面：0无 1关于页（仅文章；绑定后不进文章列表）' AFTER `status`;

ALTER TABLE `{prefix}content`
  ADD KEY `idx_kind_bindpage` (`kind`, `bindpage`, `status`);
