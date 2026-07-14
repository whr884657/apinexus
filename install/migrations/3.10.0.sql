-- misc-api 3.10.0
-- 审核三态（待审/通过/不通过）+ 拒绝原因；供用户提交 API 与邮件通知

-- 旧版 audit：0=不通过 → 现 2=不通过；1=通过 保持不变
UPDATE `{prefix}api` SET `audit` = 2 WHERE `audit` = 0;

ALTER TABLE `{prefix}api`
  MODIFY COLUMN `audit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '审核状态：0待审核 1通过 2不通过（管理员发布默认1）';

ALTER TABLE `{prefix}api`
  ADD COLUMN `rejectreason` varchar(500) NOT NULL DEFAULT '' COMMENT '审核不通过原因（管理员可选填写）' AFTER `audit`;
