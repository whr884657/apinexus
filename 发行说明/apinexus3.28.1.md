# ApiNexus 3.28.1 发行说明

**日期：** 2026-07-17  
**类型：** 小版本（页脚体验微调）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.28.1/apinexus3.28.1.zip

## 变更

1. **二维码**  
   尺寸缩小（约 64px）；默认主题与主题二均靠右展示。

2. **自定义底栏徽章**  
   对插入的 `<img>` / SVG 统一 `max-height: 24px`，避免腾讯云/雨云/IPv6 等徽章忽大忽小。

3. **默认主题首页**  
   `$siteName` 等改为直接读取 `SiteContext` / `UserAuth`，消除 IDE 缺变量告警。

## 升级注意

- 无数据库结构变更。  
- 强刷 `site-footer.css` 与主题页脚静态资源。

## 库变更

否。
