# core/data · 主题本地配置目录

> **版本：** 自 ApiNexus **13.26.45** 起生效。  
> **权威存储：** 各主题展示配置（原 MySQL `config.themesettings` 分桶）改存本目录下的 SQLite 文件。

## 一、用途

| 路径 | 说明 |
|------|------|
| `core/data/{themeId}/theme.db` | 该主题后台「主题设置」整桶 JSON（SQLite 单行 `theme_settings.payload`） |
| `core/data/{themeId}/.htaccess` | 禁止 HTTP 直链（双保险） |
| 本目录 `.htaccess` | 禁止直链本目录及子路径 |

**仍留在 MySQL 的：** `frontend_theme`（当前启用主题 ID）、站点名 / SMTP 等系统配置。  
**不放这里的：** 根目录 `data/`（调用日志冷归档、更新缓存等运行时数据），二者职责不同，勿混用。

内置主题目录（仓库预建骨架）：`default` / `slate` / `three` / `docs` / `muming`。  
用户安装的自定义主题包：首次保存主题设置或升级迁移时，按主题目录名自动创建对应子目录与 `theme.db`。

## 二、禁止通过链接访问（强制）

主题配置可能含站点文案、外链、开关等，**禁止**被浏览器或爬虫直接下载。

已配置的多层防护：

1. 本目录及各主题子目录 `.htaccess`：`Require all denied` / `Deny from all`
2. 站点根 `.htaccess`：`RewriteRule ^core/data/ - [F,L]`
3. Nginx：`location ~ ^/(config|data|core/data)/ { deny all; return 403; }`（须写在其它 `location ~` **之前**；见根目录《nginx伪静态配置.md》与安装向导片段）

**禁止：** 把 `theme.db` 放到 `core/theme/{id}/` 可下载资源路径；禁止主题模板直连 PDO/SQLite；禁止用公开 URL 暴露本目录。

自测：访问 `https://你的域名/core/data/default/theme.db` 应得到 **403 Forbidden**（或等价拒绝）。

## 三、升级迁移（库 → 本地）

升到 **13.26.45**（或已装站点跑 `DatabaseMigrator` ensure）时：

1. 读取 MySQL `config.themesettings` 各主题桶；
2. **强制覆盖**写入对应 `core/data/{id}/theme.db`（本地已有文件也会被库内数据覆盖一次）；
3. 将 `themesettings` 置为 `{}`，并标记 `themesettings_storage=local`；
4. 之后保存主题设置**只写本地库**，不再写回 MySQL 分桶。

幂等：已标记 `themesettings_storage=local` 后不再重复覆盖。

## 四、Git / 发行包

- 提交：本 README、各层 `.htaccess`、内置主题空目录与 `.gitkeep`
- **不提交：** `theme.db`、`theme.db-wal`、`theme.db-shm`（见根 `.gitignore`）

## 五、运维注意

- 需要 PHP 扩展 **pdo_sqlite**；未启用时前台回落 schema 默认值，后台保存会明确报错（不会静默写回 MySQL）。
- 备份主题配置：备份 `core/data/**/theme.db`（与数据库备份同等重要）。
- 删除主题包后，系统会在 sync 时清理对应 `core/data/{id}/` 孤儿目录（E360 本地化语义）。
