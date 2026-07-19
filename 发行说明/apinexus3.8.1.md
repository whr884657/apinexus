# ApiNexus 3.8.1 发行说明

**发布日期：** 2026-07-14  
**类型：** 小版本（清理从旧文件管理系统误迁入的残留代码）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v3.8.1/apinexus3.8.1.zip
- 标签：`v3.8.1`

## 变更摘要

1. **DatabaseMigrator**  
   删除对已不存在的 `core/Storage/LocalStorage/*` 的引用；去掉 `file_folder` / `file_item` 等旧表校验；新增 `purgeLegacyArtifacts()`，升级时自动清理残留表与配置键。

2. **Domain.php**  
   多域名功能早已在 v1.2.0 移除，本次删除死代码与 bootstrap 加载。

3. **后台布局**  
   移除不存在的 `upload-queue.css` / `upload-queue.js` 引用，避免每个后台页 404。

4. **文档**  
   同步 `CORE模块说明.md`。

## 升级说明

1. 覆盖或在线更新至 v3.8.1  
2. 本版无强制库结构变更；进入「系统升级」执行一次结构更新即可触发残留清理  
3. 无需改业务配置
