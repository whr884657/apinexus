# ApiNexus · core/captcha

系统级验证码（本地图 / 极验 3 / 极验 4 / Cloudflare Turnstile）。

| 路径 | 说明 |
|------|------|
| `gt3/GeetestLib.php` | 官方三代 SDK |
| `gt3/GeetestLibResult.php` | 官方 SDK 返回体 |
| `gt3/CheckGeetestStatus.php` | bypass 云状态（session 缓存） |
| `gt4/LoginController.php` | 官方四代二次校验逻辑 |
| `cf/Turnstile.php` | Cloudflare Turnstile Siteverify（fail-closed） |
| `local.php` | 本站 GD 图形验证码（大写+小写+数字混排绘图；**校验不区分大小写**；旋转/波纹抗 OCR；排除 0/O/o、1/I/l） |
| `image.php` | 出图入口；同 IP/会话刷图限流 |
| `register.php` | 三代初始化入口 |
| `helper.php` | `vs_captcha_field` / `vs_captcha_js` |

门面：`core/Captcha.php`  
前端：`assets/js/captcha.js`

## 前端

| 路径 | 说明 |
|------|------|
| `assets/js/captcha.js`（仅根目录，禁止主题 shell 副本） | 挂载 / 校验 / 重置（含 Turnstile） |
| `assets/js/geetest/gt4.js` / `gt.js` | 极验入口 loader（本地优先，CDN 回落） |
| `assets/js/turnstile/api.js` | Turnstile 可选本地降级（**CDN 优先**） |

配置键 Turnstile：`cfid` / `cfkey`（无下划线）。  
详见《极验验证码规范》《CloudflareTurnstile系统接入规范》与 E418。
