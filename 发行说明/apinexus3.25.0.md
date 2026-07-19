# ApiNexus 3.25.0 发行说明

**日期：** 2026-07-17  
**类型：** 中版本（详情 PATH_INFO 纠偏、去除外链代理、命名精简）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.25.0/apinexus3.25.0.zip

## 变更

1. **接口详情**  
   入口改为 `detail.php`；公开地址 `/detail.php/{id}`（PATH_INFO）。**不需要** Nginx 伪静态。

2. **伪静态**  
   仅保留代理中转 `/apis/{短码}`；已去掉 3.24.0 引入的 `api-detail` 规则。请还原为升级前可用的伪静态片段。

3. **外链图片**  
   删除 `media-proxy` / `ExternalMedia`；头像、图标按用户填写的原 URL 直链加载。页面侧可用 `loading="lazy"` 等做加载优化，不做服务端代理。

4. **在线更新清理**  
   `install/obsolete-files.json` 增加 `api-detail.php`、`media-proxy.php`、`ExternalMedia.php` 及旧主题详情页路径。

## 升级注意

- 无数据库结构变更。  
- 若曾按 3.24.0 加过 `/api-detail/` 伪静态，请**删掉**该段。  
- 强刷静态资源缓存。

## 库变更

否。
