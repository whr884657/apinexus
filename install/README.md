# install/ · 安装向导与重装说明

本目录是 **ApiNexus Web 安装向导**（`/install/`）及相关库结构脚本所在位置。

| 路径 | 作用 |
|------|------|
| `index.php` | 六步安装向导入口 |
| `database.sql` | 全新建表结构模板（表前缀固定 `vs_`） |
| `migrations/*.sql` | 版本结构迁移（已装站点走「系统升级 / 数据库维护」，一般**不要**用手跑来「重装」） |
| `obsolete-files.json` | 在线升级后待清理的过时文件清单 |

---

## 一、安装完成后会留下什么？（双保险）

安装成功后，系统用 **两道标记** 判定「已安装」。**只清其中一道不够**，向导仍会认为已装并拒绝进入（或库探测失败时 **fail-closed**：视为已装，禁止重装）。

### 1. 本地文件锁

| 项 | 说明 |
|----|------|
| 路径 | `config/install.lock`（相对项目根） |
| 内容 | 安装完成时间与版本号一行文本 |
| 作用 | 文件存在 → 视为已安装的一路信号 |

### 2. 数据库配置参数

| 项 | 说明 |
|----|------|
| 表 | `{前缀}config`，默认 **`vs_config`** |
| 键名 | **`install_done`** |
| 值 | **`1`** = 已安装 |
| 作用 | 即使删掉 `install.lock`，只要库里仍是 `1`，仍视为已安装 |

对应代码：`core/InstallChecker.php`（`CONFIG_KEY_DONE = 'install_done'`）。  
后台 `Config::set` **禁止**把 `install_done` 改成非 `1`，因此**不能**指望在管理后台清掉该标记，必须用 SQL（或删库）处理。

另：安装还会生成 `config/database.php`（数据库连接）。删不删取决于你要「仅重进向导」还是「连库一起清空重来」，见下文。

---

## 二、什么时候需要「重装」？

- 本地开发环境想从头走一遍安装向导  
- 装坏了、库结构混乱，准备 **清空业务库** 后重装  
- 误删锁文件后仍进不了 `/install/`（多半是库里的 `install_done` 还在）

**生产环境请先完整备份**（数据库 + `config/` + `data/`），再操作。重装会毁掉管理员账号、站点配置、业务数据（若你选择清空表 / 删库）。

---

## 三、重装前必须处理的两道标记

下面两条 **都要做**，缺一不可。

### 步骤 A：删除文件锁

在服务器上删除（路径以你的站点根为准）：

```text
config/install.lock
```

Linux / 面板示例：

```bash
rm -f /path/to/site/config/install.lock
```

### 步骤 B：清除数据库安装标记

用 phpMyAdmin、宝塔、MySQL 客户端等执行（表前缀若改过请替换 `vs_`）：

```sql
-- 推荐：直接删掉该配置项
DELETE FROM `vs_config` WHERE `key` = 'install_done';

-- 或改成非 1（效果同「未标记」）
-- UPDATE `vs_config` SET `value` = '0' WHERE `key` = 'install_done';
```

确认：

```sql
SELECT `key`, `value` FROM `vs_config` WHERE `key` = 'install_done';
-- 应无结果，或 value 不是 1
```

### 判定规则（便于自查）

在仍存在 `config/database.php` 的前提下：

- 有 `install.lock` **或** `install_done=1` → **已安装**，`/install/` 会跳走  
- 无锁 **且** 库明确不是 `1` → 可进向导  
- 无锁但 **连库失败 / 查不出标记** → **按已安装处理**（防误开向导），此时需先修好库连接再清标记  

---

## 四、按目标选择：还要清哪些文件 / 日志 / 库？

### 场景 1：只想重新打开安装向导（库打算沿用或稍后在向导里清空）

最少只需：

1. 删除 `config/install.lock`  
2. SQL 清除 `vs_config.install_done`  

可选：

- **保留** `config/database.php`：向导里可直接填同一库；若库里已有 `vs_*` 表，第四步会提示「已有表」，需点向导里的 **清空并创建**（`clear_and_create`），或你先手工 `DROP` 相关表。  
- **同时删除** `config/database.php`：向导第三步重新填写数据库信息（适合换库名 / 换账号）。

### 场景 2：彻底干净重装（推荐开发机）

在完成 **步骤 A + B** 之外，建议一并清理：

| 类别 | 路径 / 对象 | 说明 |
|------|-------------|------|
| 库连接配置 | `config/database.php` | 删除后向导会重新生成 |
| 安全相关目录 | `config/.security/`（若存在） | 本地安全材料；可整目录删，装完再生成 |
| 运行时数据 | `data/` 下内容（保留目录与 `.htaccess` / `.gitkeep`） | 见下「日志与 data」 |
| 业务库 | 整库 `DROP DATABASE` 或清空所有 `vs_*` 表 | 与「向导内清空并创建」二选一即可 |
| Redis（若启用） | 按你的 `redis` 前缀清键，或整库 `FLUSHDB`（慎用） | 避免旧缓存干扰；非强制 |

装完后访问：`https://你的域名/install/`（或站点子目录下的 `/install/`）。

### 场景 3：只清调用日志、不重装系统

**不要**删 `install.lock` / `install_done`。只处理日志相关即可，例如：

- MySQL 热日志表：`vs_apilog`（按需 `TRUNCATE` 或按条件删除；生产先备份）  
- 本机冷归档（若开启过日志归档）：整个目录  
  `data/apilog/`  
  （内含 `catalog/`、`days/`、`shards/` 等 SQLite 分片，见 `ApiLogArchive`）

---

## 五、日志与其它可清理文件（重装时常用）

| 位置 | 建议 |
|------|------|
| `data/apilog/` | 调用日志冷库；**重装且不要旧日志**时可整目录清空 |
| `data/playground/` | 在线测试中继临时文件；可清空目录内容，保留 `.htaccess` |
| `data/` 其它运行时文件 | 主题缓存、上传残留等凡在 `data/` 下、非必须保留的，重装前可清；**保留** `data/.htaccess`、空目录占位文件 |
| 项目内 `*.log` / `*.tmp` | 若运维或面板产生过，可删 |
| PHP / Web 服务器错误日志 | 在面板或系统路径（如 Nginx/Apache/PHP-FPM 日志），**不在本仓库内**；按需轮转或清空，与安装锁无关 |

> 在线升级**不会**删掉 `config/install.lock`、`config/database.php`、`data/`（受保护）。重装是人工运维动作，与「点升级」不是一回事。

---

## 六、推荐操作清单（清单打勾）

**最小重进向导**

- [ ] 备份（至少 `config/` + 数据库）  
- [ ] 删除 `config/install.lock`  
- [ ] `DELETE FROM vs_config WHERE \`key\` = 'install_done';`  
- [ ] 浏览器打开 `/install/`  

**干净重装**

- [ ] 上面三条  
- [ ] 删除 `config/database.php`（可选但常用）  
- [ ] 清空或删除业务库中全部 `vs_*` 表（或向导第四步「清空并创建」）  
- [ ] 清空 `data/apilog/` 及不需要的 `data/` 内容  
- [ ] （可选）清 Redis 对应前缀  
- [ ] 打开 `/install/` 走完六步  

---

## 七、常见问题

**Q：只删了 `install.lock`，还是跳转到首页？**  
A：库里 `install_done=1` 还在。按 **步骤 B** 用 SQL 清除。

**Q：锁和标记都清了，仍进不了安装？**  
A：检查 `config/database.php` 是否还能连上库；连不上时判定会 **fail-closed**（当已装）。修好连接或先删掉 `database.php` 再试。

**Q：能在后台设置里改 `install_done` 吗？**  
A：**不能**。程序禁止经通用配置接口把该键改为非 `1`，防止误开重装入口。

**Q：清空表会不会动 `migrations` 账本？**  
A：若你用向导「清空并创建」或 `DROP` 了 `vs_config` / `vs_schema_migrations` 等表，等于新库；之后应以新装 + 当前代码版本为准，不要再混用旧站的升级账本。

---

## 八、相关代码与规范

- `core/InstallChecker.php` — 双保险判定  
- `core/Config.php` — 禁止清除 `install_done`  
- `install/index.php` — 写入 `install.lock` 并调用 `markInstalledInConfig()`  
- 易错点 **E284**（安装双保险）  

有疑问时优先核对：`config/install.lock` 是否存在、`vs_config` 里 `install_done` 是否为 `1`。
