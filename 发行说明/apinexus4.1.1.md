# ApiNexus 4.1.1

**发布日期：** 2026-07-19

## 变更摘要

- **修复**：在线更新「文件覆盖」阶段若无法写入 `发行说明/*.md` 等文档，不再导致整次更新失败
- 文件写入增加 chmod / 删旧 / copy / file_put_contents 多级兜底
- 发行说明、更新记录等非关键路径写入失败时自动跳过并继续

## 若你仍卡在旧版更新失败

当前站点若仍是 **4.1.0 及更早** 的更新器，请先对站点目录执行（宝塔终端 / SSH）：

```bash
chmod -R u+w 发行说明
```

然后到后台「系统升级」再次检测并安装 **4.1.1**。

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v4.1.1/apinexus4.1.1.zip