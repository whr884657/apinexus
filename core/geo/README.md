# core/geo · 数据大屏飞线坐标库

> **版本：** v13.26.48（E484）起。由 `GeoCityCoords` 加载，**禁止** HTTP 直链。

| 文件 | 内容 |
|------|------|
| `china.php` | 国内城市/县坐标（标准短名 → `[经度, 纬度]`） |
| `world.php` | 全球城市坐标（含国内镜像，供世界地图模式） |
| `aliases.php` | 中英别名 → 标准名（长键优先） |
| `provinces.php` | 城市 → 省短名分组（对齐 `china.json`） |
| `hints.php` | 省关键词 + 短区名 → 州府/县城（全国通用，非单省特例） |
| `.htaccess` | `Require all denied` |

扩库：改 `tools/_gen_geo_expand.py` 后在仓库根执行 `python tools/_gen_geo_expand.py`，再跑 `php tools/_geo_smoke.php` 抽检。
