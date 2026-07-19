# ApiNexus 3.16.2 发行说明

**日期：** 2026-07-15  
**类型：** 小版本（文档澄清）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.16.2/apinexus3.16.2.zip

## 说明

- 开发规范《查询串转路径样式》已恢复为以 **PATH_INFO**（`/脚本.php/标识`、无需伪静态）为主体，用于接口详情等场景。  
- 代理公开地址去 `.php` 仍使用 `nginx伪静态配置.md`，与上述规范分开，避免混谈。  
- 无数据库变更；业务代理逻辑不变。

## 库变更

无。
