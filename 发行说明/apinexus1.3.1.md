# ApiNexus 1.3.1 发行说明

**发布日期：** 2026-07-11  
**类型：** 小版本（OAuth 体验优化与安全加固）  
**数据库变更：** 无（自 1.3.0 升级无需执行迁移 SQL）

---

## 本版摘要

- 登录页第三方登录图标居中，紧贴登录按钮下方
- 用户登录后可在「账号设置」直接绑定/解绑 QQ、Gitee
- OAuth 流程安全加固
- 后台用户管理移动端 UI 优化
- 全站界面提示（`.vs-notice`）去除图标，仅保留色块

---

## 变更详情

### 用户端

1. **登录页**：第三方登录区域居中显示，位于「登录」按钮正下方
2. **账号设置**：新增「第三方账号」区块，支持绑定与解绑（须管理员已在系统设置启用对应 OAuth）

### OAuth 安全

- State 携带 `intent`（login / bind）与 `user_id`，一次性消费防 CSRF
- 授权码会话内防重放
- 待绑定会话增加 identity 哈希完整性校验
- OAuth 发起与回调 IP 频率限制

### 管理后台

- 用户列表移除「可查看第三方账号绑定状态」冗余文案
- 移动端用户卡片尺寸紧凑化，一屏可展示更多用户

### UI 规范

- `vs_render_notice()` 不再渲染图标，仅保留类型色块（详见本地《界面提示规范.md》）

---

## 升级说明

### 自 1.3.0 在线升级

1. 后台「系统升级」检测并安装 v1.3.1
2. 无数据库结构变更，安装后即可使用

### 手动覆盖

1. 下载 [apinexus1.3.1.zip](https://gitee.com/xunjinlu/apinexus/releases/download/v1.3.1/apinexus1.3.1.zip)
2. 覆盖站点文件（**保留** `config/database.php`、`config/install.lock`）
3. 无需执行额外 SQL

---

## 下载

| 项目 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v1.3.1/apinexus1.3.1.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |
| 标签 | v1.3.1 |
