# ApiNexus 3.2.1 发行说明

**发布日期：** 2026-07-14  
**版本类型：** 小版本（注册页 UI 优化）  
**数据库变更：** 否

---

## 概述

本版本优化用户注册页的身份选择交互：移除「注册身份」卡片区块，在用户名输入框上方增加横条分段选择器，两主题布局一致。

---

## 注册页身份选择

| 变更 | 说明 |
|------|------|
| 移除 | 「注册身份」标题与双卡片说明区块 |
| 新增 | 用户名上方横条按钮，宽度与输入框对齐 |
| 交互 | 左半「普通用户」、右半「开发者」；滑块随点击滑动 |
| 提示 | 切换身份时弹出 Toast，说明该身份权限与用途 |

后端仍通过 `role=user|developer` 提交，与 v3.0.0 角色体系兼容。

---

## 涉及文件

- `core/theme/default/user/auth/register.php`
- `core/theme/slate/user/auth/register.php`
- `assets/css/auth-login.css`
- `core/theme/slate/assets/auth.css`

---

## 下载

- Tag：`v3.2.1`
- 附件：`apinexus3.2.1.zip`
