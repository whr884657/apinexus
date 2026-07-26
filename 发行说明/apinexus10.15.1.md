# ApiNexus 10.15.1

**发布日期：** 2026-07-27  
**数据库变更：** 否

## 本版摘要

修复 AI「测试连接」误判；兼容 Chat Completions 与 Responses API；支持从上游自动拉取模型列表。

## 主要变更

| 模块 | 说明 |
|------|------|
| 测试连接 | HTTP 成功即连通；空正文不再判失败 |
| 协议 | auto / chat / responses |
| 模型 | 拉取 GET /models |
| 服务商 | 增加 Gemini OpenAI 兼容层预设；Claude 等用自定义中转 |

## 提示词位置

`core/AiApiDoc.php`：`generateDetailDoc`、`generateCodeSamples`

## 下载

- ZIP：apinexus10.15.1.zip
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.15.1
