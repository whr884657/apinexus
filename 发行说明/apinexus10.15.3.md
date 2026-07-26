# ApiNexus 10.15.3

**发布日期：** 2026-07-27  
**数据库变更：** 否

## 本版摘要

保存含代码示例的文档时用 VS64 编码规避 WAF 误拦；默认主题详情页代码块补齐 IDE 级高亮。

## 主要变更

| 模块 | 说明 |
|------|------|
| 表单传输 | `doc`/`aidoc` 等字段客户端 Base64（`VS64:`）提交，服务端解码入库 |
| 覆盖面 | 后台接口、用户投稿、文章/公告 Markdown 正文 |
| 详情页 | Markdown + 快速上手走 `VsSyntax` 着色 |

## 三遍复查

1. 本版：未带前缀仍兼容明文；编码不改库内存储形态
2. 上版 10.15.2：AI 可视化 / 半折叠文档保留
3. 交叉：关 WAF 不是方案；应用层编码为主

## 二次复查（同版修补）

| 问题 | 处理 |
|------|------|
| VS64 非法/超长可能入库包装串 | 解码失败/超 300KB → 空串；前缀改为 `VS64B:`（兼容旧 `VS64:`） |
| `:::button url=javascript:` 存储型 XSS | `vs_safe_embed_url` / JS 对齐；color 限制 |
| 文章正文无长度上限 | `ContentManager` 约 20 万字节 |

## 下载

- ZIP：apinexus10.15.3.zip
- Release：https://gitee.com/xunjinlu/apinexus/releases/tag/v10.15.3
