-- ApiNexus 13.26.48 · 反向代理开关与名单、大屏枢纽 IP（仅补缺，不覆盖已有值）

INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('proxyon', '0');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('proxyips', '');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('screenhubip', '');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('checkin_streak_mode', 'off');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('checkin_streak_value', '0');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('subon', '0');
INSERT IGNORE INTO `{prefix}config` (`key`, `value`) VALUES ('mail_notify_apistatus', '0');

ALTER TABLE `{prefix}user` MODIFY COLUMN `lastcheckin` text NULL COMMENT '最近签到JSON（days最多7个日期，streak为连续天数；空表示从未签到）';

UPDATE `{prefix}user`
SET `lastcheckin` = CONCAT('{"days":["', LEFT(`lastcheckin`, 10), '"],"streak":1}')
WHERE `lastcheckin` IS NOT NULL
  AND `lastcheckin` <> ''
  AND `lastcheckin` NOT LIKE '{%'
  AND LEFT(`lastcheckin`, 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$';
