-- 5.5.0：link.kind 扩展赞助；赞助收款码配置键
ALTER TABLE `{prefix}link`
  MODIFY COLUMN `description` varchar(200) NOT NULL DEFAULT '' COMMENT '简介：友情链接简介 / 赞助说明（金额或其它支持）',
  MODIFY COLUMN `kind` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型：0友情链接 1合作伙伴 2赞助',
  MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '审核状态：0待审 1通过 2拒绝（合作伙伴与赞助固定为1）',
  COMMENT='友情链接、合作伙伴与赞助';

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('sponsor_qr_alipay', ''),
('sponsor_qr_wechat', ''),
('sponsor_qr_qq', '')
ON DUPLICATE KEY UPDATE `key` = VALUES(`key`);
