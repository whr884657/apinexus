-- ApiNexus 5.0.0
-- 友情链接 / 合作伙伴共用 link 表：新增类型 kind、启用 enabled

ALTER TABLE `{prefix}link`
    ADD COLUMN `kind` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型：0友情链接 1合作伙伴' AFTER `contact`,
    ADD COLUMN `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用：0禁用 1启用' AFTER `kind`,
    ADD KEY `idx_kind` (`kind`),
    ADD KEY `idx_enabled` (`enabled`);

ALTER TABLE `{prefix}link`
    MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '审核状态：0待审 1通过 2拒绝（合作伙伴固定为1）',
    MODIFY COLUMN `name` varchar(50) NOT NULL COMMENT '名称',
    MODIFY COLUMN `siteurl` varchar(255) NOT NULL COMMENT '跳转链接',
    MODIFY COLUMN `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标链接',
    COMMENT='友情链接与合作伙伴';

UPDATE `{prefix}link` SET `kind` = 0, `enabled` = 1;

INSERT INTO `{prefix}link` (`name`, `siteurl`, `icon`, `description`, `contact`, `kind`, `enabled`, `status`, `sort`, `createtime`)
SELECT v.`name`, v.`siteurl`, v.`icon`, '', '', 1, 1, 1, v.`sort`, NOW()
FROM (
    SELECT 'Gitee' AS `name`, 'https://gitee.com/xunjinlu/apinexus' AS `siteurl`, 'https://gitee.com/static/images/logo_themecolor.png' AS `icon`, 10 AS `sort`
    UNION ALL
    SELECT 'GitHub', 'https://github.com', 'https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png', 20
    UNION ALL
    SELECT 'PHP', 'https://www.php.net', 'https://www.php.net/favicon.ico', 30
) AS v
WHERE NOT EXISTS (SELECT 1 FROM `{prefix}link` WHERE `kind` = 1 LIMIT 1);
