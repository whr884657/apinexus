# ApiNexus 3.29.0 发行说明

**日期：** 2026-07-18  
**类型：** 大版本（用户密钥体系 + API 管理体验）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.29.0/apinexus3.29.0.zip

## 变更

1. **用户密钥表 `apikey`**  
   字段：`userid`、`remark`、`secret`、`status`、`calls`、`createtime`。  
   密钥格式：`SK-` + 32 位随机字符；每用户最多 3 个。  
   **注意：** 表名不用 `token`（避免与大模型计费 token 语义冲突）。若早期包已建 `token`，结构更新时会自动重命名为 `apikey`。

2. **用户中心 · 令牌管理**  
   手机卡片 / 电脑列表；添加与编辑使用半屏/居中表单弹窗；支持重置、禁用、删除。

3. **管理员 · 令牌管理**  
   路径 `/admin/api/tokens`，可查看全站密钥并重置/禁用/删除。

4. **统计接入密钥校验**  
   本地 `ApiStats::hit` 与代理 `/apis/{短码}` 均识别 `key` / `api_key` / `apikey` / `X-API-Key`；校验是否存在且启用；写入调用用户归属；成功调用累加该密钥 `calls`。`needkey=必须` 时未带密钥将拒绝。

5. **用户 API 管理体验**  
   修复手机卡片无间隙；请求方式与链接上移贴齐头像；补充分页与「共 N 个接口」。

## 升级注意

- **须执行数据库结构更新**（迁移 `3.29.0.sql`；若已有误建的 `token` 表会自动改名为 `apikey`）。  
- 强刷 `admin.css`、`user-tokens.js`、`user-api-manage.js`、`admin-tokens.js`。  
- 废弃文件：若站点仍残留 `core/TokenManager.php`，请删除（已由 `ApiKeyManager.php` 替代）。

## 库变更

是（新建 `apikey` 表；勿再用 `token`）。
