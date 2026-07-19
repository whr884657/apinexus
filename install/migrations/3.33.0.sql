-- ApiNexus 3.33.0：积分余额、接口计费、订单表、支付配置、apilog.cost 精度
-- 规范：字段无下划线；中文 COMMENT；多态用数字

ALTER TABLE `{prefix}user`
  ADD COLUMN `points` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '积分余额' AFTER `role`;

ALTER TABLE `{prefix}api`
  ADD COLUMN `charge` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否收费：0免费 1收费' AFTER `needkey`,
  ADD COLUMN `price` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '每次调用扣除积分（收费时有效）' AFTER `charge`;

ALTER TABLE `{prefix}apilog`
  MODIFY COLUMN `charged` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否扣费：0否 1是',
  MODIFY COLUMN `cost` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '本次扣费积分数';

CREATE TABLE IF NOT EXISTS `{prefix}orders` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `orderno` varchar(64) NOT NULL COMMENT '订单号（全局唯一）',
    `userid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联用户ID（对应user.id）',
    `direct` tinyint(1) NOT NULL COMMENT '方向：0减少 1增加',
    `kind` tinyint(1) NOT NULL COMMENT '类型：增加时0用户充值1管理员加款；减少时0API调用1管理员扣款2AI调用(预留)',
    `amount` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '变动积分（正数）',
    `balance` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '变动后积分余额',
    `money` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '实付金额（元，充值订单）',
    `apiid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联接口ID（API扣费时）',
    `keyid` int unsigned NOT NULL DEFAULT 0 COMMENT '关联密钥ID（API扣费时）',
    `paytype` varchar(16) NOT NULL DEFAULT '' COMMENT '支付方式：alipay/wxpay/qqpay',
    `tradeno` varchar(64) NOT NULL DEFAULT '' COMMENT '上游平台订单号',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '订单状态：0待支付 1已完成 2已取消',
    `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注说明',
    `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `paytime` datetime DEFAULT NULL COMMENT '支付完成时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_orderno` (`orderno`),
    KEY `idx_userid` (`userid`),
    KEY `idx_direct` (`direct`),
    KEY `idx_kind` (`kind`),
    KEY `idx_status` (`status`),
    KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分与支付订单';

INSERT INTO `{prefix}config` (`key`, `value`) VALUES
('pay_url', ''),
('pay_pid', ''),
('pay_key', ''),
('pay_channel', '{"alipay":"","wxpay":"","qqpay":""}'),
('pay_methods', '["alipay","wxpay"]'),
('pay_rate', '1000'),
('pay_packages', '[{"id":"base1","name":"体验包","money":"1.00","points":"1000","hot":0},{"id":"base10","name":"常用包","money":"10.00","points":"11000","hot":1},{"id":"base50","name":"超值包","money":"50.00","points":"60000","hot":0}]')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
