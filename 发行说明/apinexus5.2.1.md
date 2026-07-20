# ApiNexus 5.2.1 发行说明

**版本：** 5.2.1  
**日期：** 2026-07-21  
**类型：** 小版本（登录会话回归修复）

## 问题

升级 5.2.0 后：管理员/用户均可登录进入后台，但**任意页面一刷新即强制退出登录**；两套主题用户中心同现。

## 原因

v5.2.0 在每个 HTTPS 请求中下发「清除 Secure 会话 Cookie」。普通页面刷新时 PHP 通常不再重写会话 Cookie，部分浏览器会把非 Secure 会话 Cookie 一并删掉。

## 修复

1. `configureSessionCookies()` **不再**每请求清除 Secure Cookie  
2. 仅登录成功调用 `clearLegacySecureSessionCookie()` 做一次迁移清除  
3. 退出登录 `clearSessionCookie()` 同时清除 Secure / 非 Secure  

## 升级

1. 覆盖代码后建议清一次本站 Cookie 再登录  
2. **无需**数据库结构更新  

## 数据库变更

无
