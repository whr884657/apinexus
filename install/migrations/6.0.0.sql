-- ApiNexus 6.0.0：公开个人主页 / 贡献者资料字段 + 全站默认壁纸

ALTER TABLE `{prefix}user`
  ADD COLUMN `bio` varchar(200) NOT NULL DEFAULT '' COMMENT '个人简介' AFTER `avatar`,
  ADD COLUMN `blog` varchar(500) NOT NULL DEFAULT '' COMMENT '个人主页或博客链接' AFTER `bio`,
  ADD COLUMN `wallpaper` varchar(500) NOT NULL DEFAULT '' COMMENT '个人主页背景图链接（空则用全站默认）' AFTER `blog`;

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('profile_wallpaper', 'https://picsum.photos/1600/600')
ON DUPLICATE KEY UPDATE `value` = `value`;
