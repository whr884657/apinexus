# ApiNexus 4.1.0

**发布日期：** 2026-07-19

## 变更摘要

- **三仓库更新兜底**：在线更新默认使用 Gitee；失败后依次尝试 GitCode、GitHub
- 清单文件 `update.json`、更新记录 `update-log.json`、更新包 ZIP 均按同一顺序拉取
- README 增加 Gitee / GitCode / GitHub 仓库链接；版本记录仅保留最新一条，完整历史见仓库根目录 `更新记录.md`
- 移除 README「功能列表」大表（能力说明保留在「项目简介」）

## 升级说明

- 无数据库结构变更，安装更新后无需执行迁移
- 若 Gitee 不可达，站点需能访问 `raw.gitcode.com` 或 `raw.githubusercontent.com` / `github.com` 方可自动兜底

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v4.1.0/apinexus4.1.0.zip

镜像仓库：

- https://gitcode.com/xunjinlu/apinexus
- https://github.com/whr884657/apinexus