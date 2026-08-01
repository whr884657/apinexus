-- 13.22.5：代理上游请求方式（GET/POST 可选）
ALTER TABLE `{prefix}api`
  ADD COLUMN `upmethod` tinyint(1) NOT NULL DEFAULT 0 COMMENT '上游请求方式：0=GET 1=POST（仅代理类型）' AFTER `upkey`;
