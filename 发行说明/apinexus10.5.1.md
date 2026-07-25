# ApiNexus 10.5.1

## 本版要点

- **紧急修复**：默认主题前台内存耗尽（`formatForTheme` → `findProfile` → 再格式化接口的递归）
- 作者信息仅详情页轻量查询；列表不再加载作者
- `hasAuditColumn` 静态缓存，避免列表循环反复探测列

## 升级注意

- 无数据库结构变更
- 覆盖 `core/FrontendApi.php`、`core/ApiManager.php` 后即可恢复前台
