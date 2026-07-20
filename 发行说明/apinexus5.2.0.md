# ApiNexus 5.2.0 发行说明

**版本：** 5.2.0  
**日期：** 2026-07-20  
**类型：** 中版本（登录 CSRF / 双协议会话修复）

## 问题现象

管理员后台与两套主题用户登录，在以下场景高频提示「登录凭证已失效」：

- 站点同时支持 HTTP 与 HTTPS
- 站点接入 CDN 缓存
- 清除浏览器缓存后仍失败，换网络/换手机偶发恢复

## 根因

1. **会话 Cookie 的 `Secure` 随当前请求协议切换**：HTTPS 写入 Secure Cookie，HTTP 写入非 Secure Cookie，浏览器同时保留两份会话，CSRF 与页面凭证必然错位。
2. **CDN 可能缓存登录页 HTML**，页面内嵌 CSRF 与真实会话脱节。

## 修复

1. 会话 Cookie **不再按当前是否 HTTPS 动态设 Secure**；默认双协议共享同一会话；仅配置 `force_https=1` 时启用 Secure。
2. HTTPS 响应主动清除历史 Secure 会话 Cookie，消除双 Cookie。
3. 认证页加强 `Cache-Control` / `CDN-Cache-Control: no-store`，禁止边缘缓存。
4. CSRF 失败时 **轮换** 新凭证并返回；前端 `auth-csrf.js` 自动回填并 **重试一次**。
5. 管理员登录 + 默认主题 / slate 用户登录统一走 `VsAuthCsrf`。

## 升级

1. 覆盖代码后，建议用户 **清除本站 Cookie 一次**（或无痕窗口）再登录。  
2. **无需**数据库结构更新。  
3. 若站点仅允许 HTTPS，可在配置中设置 `force_https=1`（写入 `vs_config`）。  
4. CDN 请对 `/admin/login`、`/user/login` 等认证路径关闭缓存。

## 数据库变更

否
