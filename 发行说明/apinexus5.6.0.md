# ApiNexus 5.6.0 发行说明

**版本：** 5.6.0  
**日期：** 2026-07-21  
**类型：** 中版本（URL / 伪静态路由）  
**数据库：** 无结构变更

## 变更

- 接口详情对外地址由 `/detail.php/{id}` 改为 **`/detail/{id}`**
- Nginx / Apache 增加 `detail` 伪静态（`detail.php?id=`）；文档说明后续其它「页面 + 数字 ID」可同模式扩展
- `vs_api_detail_url`、双主题 JS 兜底同步；入站仍兼容旧 `/detail.php/{id}`（PATH_INFO）

## 升级注意

1. 覆盖代码后，请在宝塔伪静态中**追加** `detail` 规则（见 `nginx伪静态配置.md`），并放在 `location /` **上面**，然后重载 Nginx。  
2. Apache 用户更新后的根目录 `.htaccess` 已含对应规则。  
3. 无需数据库结构更新。
