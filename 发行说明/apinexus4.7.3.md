# ApiNexus 4.7.3

**发布日期：** 2026-07-20  
**下载：** [apinexus4.7.3.zip](https://gitee.com/xunjinlu/apinexus/releases/download/v4.7.3/apinexus4.7.3.zip)

## 变更说明

- **媒体类型**：按文件魔数纠偏 Content-Type；返回 `mediaKind`；勿再默认当 IMAGE（修复视频误标）
- **图片 POST 预览**：curl 重定向时取最后一跳 Content-Type，避免误判为文本导致无法显示
- **友链页标题**：补齐 `//` 双斜线标题（HTML 标记，避免被 reset）

## 升级说明

- 无数据库结构变更
