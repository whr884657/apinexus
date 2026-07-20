# ApiNexus 5.5.0 发行说明

**版本：** 5.5.0  
**日期：** 2026-07-21  
**类型：** 中版本（赞助功能 + 库表 kind 扩展）  
**数据库：** 有（`install/migrations/5.5.0.sql`）

## 变更

### 数据模型

`{prefix}link` 继续共用：

| kind | 含义 | 主要字段 |
|------|------|----------|
| 0 | 友情链接 | name / siteurl / icon / description / contact + 审核 |
| 1 | 合作伙伴 | name / siteurl / icon（无 description） |
| 2 | 赞助 | name / icon / siteurl（选填）/ **description=赞助说明** |

### 后台

- 「交易财务 → 赞助管理」完整 CRUD（替代占位页）
- 「系统设置 → 站点扩展」新增：`sponsor_qr_alipay` / `sponsor_qr_wechat` / `sponsor_qr_qq`

### 前台

- 新增 `FrontendSponsor`（主题只调本类）
- **默认主题**赞助页：收款码（按配置显示）+ 赞助榜 + 动效
- 主题二赞助页仍为占位，后续对齐

## 升级

1. 覆盖代码  
2. 后台执行 **数据库结构更新**  
3. 在系统设置填写收款码；在赞助管理添加记录  
4. 打开默认主题 `/sponsor` 验收  
