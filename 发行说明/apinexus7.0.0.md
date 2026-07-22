# ApiNexus 7.0.0 发行说明

**版本：** 7.0.0  
**日期：** 2026-07-22  
**类型：** 大版本（内容运营 + Markdown 内核）  
**数据库：** 有结构变更（新增 `content` 共用表）

## 变更

- **公告管理**：发布/编辑/删除、置顶、弹窗；首页跑马灯与弹窗读真实数据
- **文章管理**：发布/编辑/删除、封面图；前台 `/articles` 列表与 `/articles/{id}` 详情
- **共用表** `content`：`kind=0` 公告，`kind=1` 文章（范式同友链 `link.kind`）
- **Markdown**：`core/markdown/` 本地编辑器 + Parsedown/marked 渲染；短码含卡片、提示、折叠、按钮、时间轴、音乐、视频、缩进等
- **API 文档**：后台/用户中心普通文档与 AI 文档接入同一编辑器；详情页服务端渲染
- **日志查询**：修复首屏一直「加载中」；去掉页面提示文案

## 升级注意

1. 后台执行 **数据库结构更新**（`install/migrations/7.0.0.sql`）
2. 管理员发布公告/文章前须已绑定前台用户身份
3. 评论管理仍为占位，后续版本再做
4. 下载：https://gitee.com/xunjinlu/apinexus/releases/download/v7.0.0/apinexus7.0.0.zip
