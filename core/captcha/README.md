<?php
/**
 * 文件：core/captcha/README.md
 * 作用：行为验证 SDK 目录说明（随仓库发布）
 */

# 行为验证（极验）

本目录存放系统级人机验证服务端代码，**不**放在主题内。

| 路径 | 说明 |
|------|------|
| `geetest3/` | 极验 3 代服务端 SDK（`GeetestLib`） |
| `geetest4/` | 极验 4 代二次校验（`Geetest4`） |

统一门面：`core/Captcha.php`  
3 代初始化入口：`/captcha/register.php`  
前端脚本：`assets/js/geetest-auth.js`（加载官方 CDN `gt.js` / `gt4.js`）

配置在「系统设置 → 行为验证」，按场景开关管理员登录/忘记密码、用户登录/注册/忘记密码。
