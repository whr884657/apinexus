# ApiNexus 10.13.0

**发布日期：** 2026-07-26  
**数据库变更：** 是（须执行结构更新）

## 本版摘要

完善**代理外链上游认证**：对接需密钥的第三方 API 平台时，可在后台配置认证方式，由本站代为附加密钥，调用方不可见。

## 上游认证方式

| 方式 | 行为 |
|------|------|
| 无需认证 | 与以往一致，302 跳转上游 |
| API Key | 按 **URL 参数** 或 **请求头** 附加静态密钥；服务端中继 |
| Bearer Token | `Authorization: Bearer <token>`；服务端中继 |

## 涉及范围

- 库表 `api`：新增 `upauth` / `upkeyvia` / `upkeyname` / `upkey`
- 网关 `ApiProxy`：有密钥时 curl 中继并回传响应
- 管理员「接口列表」、用户中心「API 管理」投稿表单

## 升级注意

1. 在线更新或覆盖后，请在后台「系统升级」执行**数据库结构更新**。
2. 已有「无需认证」的代理接口行为不变；新配密钥后才会走中继。

## 下载

- ZIP：`apinexus10.13.0.zip`
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.13.0
