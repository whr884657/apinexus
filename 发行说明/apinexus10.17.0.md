# ApiNexus 10.17.0

**发布日期：** 2026-07-27  
**数据库变更：** 是（须执行结构更新）

## 本版摘要

统一守卫/代理错误 JSON 格式（含 `http`）；接口可配置密钥传递方式 `keyways`；站点名称与系统名称分工；默认主题可开关 API 免责声明；AI 生成文档与 `:::qs` 示例加固；用户管理与 IP 归属地设置文案优化。

## 主要变更

| 模块 | 说明 |
|------|------|
| 守卫错误 | `ApiStats` / `ApiProxy` 经 `vs_api_error_exit` 输出 `{ code:0, msg, http }` |
| keyways | 表 `api.keyways`：query / header / bearer 逗号多选；多通道并存时任一有效密钥即可 |
| 站点名 | `site_name` 前台展示；`system_name` 后台侧栏/用户中心（缺省=站点名） |
| 免责声明 | 系统启用开关 + 正文；默认主题 `show_api_disclaimer` 控制是否展示（两者同时满足） |
| 在线测试 | 详情页 Playground 按鉴权方式传 Query / `X-API-Key` / Bearer |
| AI | 禁止 HTML / vs-syn 泄漏；代码示例 `:::qs lang=… auth=…` 必填 auth |
| 用户管理 | 角色 Tab、OAuth 徽章、积分调整、桌面表格 + 移动卡片 |
| IP 归属地 | 设置页认证方式选项文案与提示对齐 |

## 升级注意

1. **必须执行数据库结构更新**（后台「系统更新」或迁移 `10.17.0.sql`）：
   - `api` 表新增 `keyways` 字段（默认 `query`）
   - `config` 表插入 `system_name`（默认 `ApiNexus`）
2. 升级后可在接口编辑中选择「密钥传递方式」；旧接口保持 Query 传参。
3. 若需隐藏详情页免责声明：默认主题「主题设置」关闭「显示 API 详情免责声明」。

## 下载

- ZIP：apinexus10.17.0.zip
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.17.0
