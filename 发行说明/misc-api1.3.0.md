# misc-api 1.3.0 发行说明

**发布日期：** 2026-07-11  
**版本类型：** 大版本（OAuth 聚合登录 + 用户管理）

## 更新摘要

- 用户端 QQ / Gitee OAuth 聚合登录
- 用户表 OAuth 绑定字段
- 后台用户管理页
- 系统设置 OAuth 配置

## 详细变更

### 用户端 OAuth

- 登录页登录按钮下方新增「第三方登录」入口（QQ、Gitee 小图标）
- 仅**已注册用户**可使用：首次第三方登录需输入本站账号密码完成绑定
- 已绑定用户可直接通过 QQ / Gitee 登录
- 注册页、忘记密码页**不显示**第三方登录按钮

### 数据库

- `vs_user` 表新增：
  - `oauth_qq_openid` — QQ OpenID
  - `oauth_gitee_id` — Gitee 用户 ID
- 新增配置项 `oauth_config`（JSON）

### 管理后台

- 侧边栏新增 **用户管理** 大类
- 用户列表展示：头像、用户名、邮箱、QQ/Gitee 绑定状态、注册时间、最后登录
- 电脑端表格、手机端卡片自适应
- 系统设置新增 **第三方登录** 配置（QQ App ID/Key、Gitee Client ID/Secret、回调地址说明）

## 配置说明

1. 后台 **系统设置 → 第三方登录** 填写 QQ / Gitee 应用参数并启用
2. 在 QQ 互联、Gitee 应用中将回调地址配置为页面显示的 URL：
   - `{站点}/user/oauth/callback.php?provider=qq`
   - `{站点}/user/oauth/callback.php?provider=gitee`
3. 用户须先通过邮箱注册，再在登录页使用第三方登录完成绑定

## 升级说明

1. 备份数据库与网站文件
2. 后台安装 v1.3.0（含 `install/migrations/1.3.0.sql`）
3. 配置 OAuth 应用参数后测试登录流程

## 参考文档

- [QQ 互联 OAuth2.0](https://wiki.connect.qq.com/oauth2-0%E5%BC%80%E5%8F%91%E6%96%87%E6%A1%A3)
- [Gitee OAuth 文档](https://gitee.com/api/v5/oauth_doc)
