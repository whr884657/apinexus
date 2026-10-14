# Cloudflare Turnstile 脚本说明

## 官方要求（强制）

主路径**必须**使用官方 CDN：

```
https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit
```

官方明确：**禁止**将 `api.js` 作为长期代理/缓存的唯一来源，否则 Cloudflare 升级脚本后可能导致小组件失效。

## 本站双用策略

| 优先级 | 来源 |
|--------|------|
| 1（强制） | 上述官方 CDN |
| 2（降级） | 本目录 `api.js`（快照，仅 CDN 不可达时尝试） |

`assets/js/captcha.js` 的 `loadTurnstileScript()` 已按「CDN → 本地」实现。

## 更新本地快照

在可联网环境执行（示例）：

```bash
curl -fsSL "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" -o api.js
```

发版前抽测：断网或拦 CDN 时降级是否可用；正常网络下须走 CDN。
