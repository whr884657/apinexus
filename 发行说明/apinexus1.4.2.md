# ApiNexus 1.4.2 发行说明

**发布日期：** 2026-07-11  
**类型：** 小版本（OAuth 绑定 Bug 修复）  
**数据库变更：** 无

---

## 问题描述

用户在「账号设置」点击 Gitee 绑定后，回调跳转登录页并提示：

> 授权状态无效或已过期，请重试

根因：OAuth 回调为 Gitee → 站点的跨站跳转，Session Cookie 使用 `SameSite=Strict` 时浏览器不携带会话，导致 `OAuthState` 存于 Session 的数据丢失。

---

## 修复内容

1. **Session Cookie `SameSite` 调整为 `Lax`**，允许 OAuth 授权回调携带会话
2. **OAuth state 改为 HMAC 签名令牌**，校验不再依赖 Session 存储
3. **绑定流程**：回调时若会话短暂丢失，信任签名 state 中的 `user_id` 完成绑定
4. **错误跳转**：绑定 intent 的 OAuth 错误跳转至账号设置页

---

## 升级说明

自 1.4.x 在线升级即可，无数据库变更。

---

## 下载

| 项目 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v1.4.2/apinexus1.4.2.zip |
| 标签 | v1.4.2 |
