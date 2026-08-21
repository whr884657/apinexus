# 极验入口脚本（本地化）

本目录存放极验官方 **Web 入口 loader**（非完整二次资源包）。

| 文件 | 用途 | 官方来源 |
|------|------|----------|
| `gt4.js` | 行为验证第四代入口，暴露 `initGeetest4` | https://static.geetest.com/v4/gt4.js |
| `gt.js` | 行为验证第三代入口，暴露 `initGeetest` | https://static.geetest.com/static/tools/gt.js |

## 说明

1. **本地化的是入口 JS**：页面优先加载本目录文件，失败再回落官方 CDN（见 `assets/js/captcha.js`）。
2. **弹层 / 字体 / 接口资源仍走官方域名**（如 `gcaptcha4.geetest.com`、`static.geetest.com`），需 CSP 白名单，见《极验验证码规范》。
3. 升级入口脚本时，可从上述官方 URL 重新下载覆盖本目录文件，并同步 bump `VS_VERSION` 缓存参数。

官方文档：

- 四代接入：https://docs.geetest.com/gt4/handbook
- 四代 Web API：https://docs.geetest.com/gt4/apirefer/api/web
- 三代 Web API：https://docs.geetest.com/sensebot/apirefer/api/web
- 三代流程：https://docs.geetest.com/sensebot/overview/guide/operate
