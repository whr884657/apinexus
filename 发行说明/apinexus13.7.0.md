# ApiNexus 13.7.0

**发布日期：** 2026-07-29

## 变更摘要

- **分端验证方式：** 管理员与用户可分别选择本站图形 / 行为验证三代 / 四代，避免第三方服务异常时管理员无法登录
- **极验样式：** 修复认证页对极验内部元素强制 `width:100%` 导致 logo 变形
- **设置回显：** 三代/四代密钥在系统设置中明文显示并可直接修改
- **数据库：** 迁移写入 `captcha_mode_admin` / `captcha_mode_user`（从旧 `captcha_mode` 复制）

## 升级注意

升级后请到后台「系统升级」执行数据库结构更新，再到「系统设置 → 验证码」确认两侧方式与场景开关。

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v13.7.0/apinexus13.7.0.zip
