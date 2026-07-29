# ApiNexus 13.9.1

**发布日期：** 2026-07-29

## 变更摘要

- **安全加固（13.9.0 审计跟进）：** 密码仅 `password_hash`（废除双 MD5，升级后旧密码作废须忘记密码重置）；SSRF  harden；管理/前台 Session 拆分；OTP 失败限流；默认开启本地验证码（安装强制 GD）；付费代理先扣后调（失败退回）；AI `baseurl` 禁止打内网；前台基础安全头等
- **开源许可：** 根目录 `LICENSE` 为标准 **MIT**（平台可识别）；中文补充条款见 `LICENSE.zh-CN.md`
- **安装向导：** 首次进入须完整阅读许可并勾选同意后，方可进行环境检测与后续部署

## 升级注意

**有数据库变更。** 请后台执行系统升级以应用 `install/migrations/13.9.1.sql`：

1. 管理员 / 用户密码列扩容；**全部旧密码作废**，须通过「忘记密码」重置
2. 默认开启管理端与用户端各场景本地验证码（可在系统设置关闭）
3. Nginx 请按更新后的 `nginx伪静态配置.md` deny `/config/`、`/data/`
4. 若站点在反向代理后，限流需真实客户端 IP 时配置 `trust_proxy=1`
5. 升级后请重新登录（Session Cookie 名已拆分）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v13.9.1/apinexus13.9.1.zip
