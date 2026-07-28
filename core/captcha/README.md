# ApiNexus · core/captcha

系统级验证码（本地图 / 极验 3 / 极验 4）。

| 路径 | 说明 |
|------|------|
| `gt3/GeetestLib.php` | 官方三代 SDK |
| `gt3/GeetestLibResult.php` | 官方 SDK 返回体 |
| `gt3/CheckGeetestStatus.php` | bypass 云状态（session 缓存） |
| `gt4/LoginController.php` | 官方四代二次校验逻辑 |
| `local.php` | 本站 GD 图形验证码 |
| `register.php` | 三代初始化入口 |
| `image.php` | 本地验证码图 |
| `helper.php` | `vs_captcha_field` / `vs_captcha_js` |

门面：`core/Captcha.php`  
前端：`assets/js/captcha.js`
