# ApiNexus 10.15.0

**发布日期：** 2026-07-27  
**数据库变更：** 否（配置键首次保存时写入）

## 本版摘要

站点级 AI 对接：管理员可配置大模型，一键生成接口详细文档与九种语言快速上手代码示例；前台快速上手改为只读取 `aidoc` 中的 `:::qs` 短码，并支持 IDE 级语法高亮。

## 主要变更

| 模块 | 说明 |
|------|------|
| 系统设置 | 「AI 对接」：启用、服务商、根地址、API Key、模型、超时、文档字数上限 |
| 接口列表 | 「AI 生成详细文档」「AI 生成代码示例」；编辑自动草稿 / 新建本地草稿 |
| 快速上手 | 解析 `:::qs lang=curl|typescript|browser|python|go|java|php|cpp|rust`；无内容则提示 |
| 高亮 | 本地 `VsSyntax` 扩展多语言关键字 |
| 文档中心 | 请求示例优先展示 qs 块；面板提供「编辑」跳转 |
| 安全 | 生成上下文不含上游 URL / `upkey`；仅管理员可用 |

## 升级注意

1. 覆盖源码后无需强制跑库结构更新；到「系统设置 → AI 对接」填写密钥即可使用。
2. 历史写死在主题里的示例已移除；请为接口填写或 AI 生成 `aidoc`。
3. LongCat 根地址须能拼出 `…/openai/v1/chat/completions`。
4. 强刷浏览器缓存以加载新 JS/CSS。

## 下载

- ZIP：`apinexus10.15.0.zip`
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.15.0
