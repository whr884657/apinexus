# misc-api 3.29.0 发行说明

**日期：** 2026-07-18  
**类型：** 大版本（令牌体系 + API 管理体验）

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v3.29.0/misc-api3.29.0.zip

## 变更

1. **用户令牌表 `token`**  
   字段：`userid`、`remark`、`secret`、`status`、`calls`、`createtime`。  
   令牌格式：`SK-` + 32 位随机字符；每用户最多 3 个。

2. **用户中心 · 令牌管理**  
   手机卡片 / 电脑列表；添加与编辑使用半屏/居中表单弹窗；支持重置、禁用、删除。

3. **管理员 · 令牌管理**  
   路径 `/admin/api/tokens`，可查看全站令牌并重置/禁用/删除。

4. **用户 API 管理体验**  
   修复手机卡片无间隙；请求方式与链接上移贴齐头像；补充分页与「共 N 个接口」。

## 升级注意

- **须执行数据库结构更新**（迁移 `3.29.0.sql`）。  
- 强刷 `admin.css`、`user-tokens.js`、`user-api-manage.js`、`admin-tokens.js`。  
- 本版仅完成令牌存储与管理界面；接口调用时强制校验密钥将在后续版本接入。

## 库变更

是（新建 `token` 表）。
