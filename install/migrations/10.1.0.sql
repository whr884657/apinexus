-- 文章评论：引用回复 + 个人网址（v10.1.0）
ALTER TABLE `{prefix}comment`
  ADD COLUMN `parentid` int unsigned NOT NULL DEFAULT 0 COMMENT '引用的父评论ID（0表示顶层）' AFTER `contentid`,
  ADD COLUMN `website` varchar(255) NOT NULL DEFAULT '' COMMENT '个人网址（选填）' AFTER `email`,
  ADD KEY `idx_parentid` (`parentid`);
