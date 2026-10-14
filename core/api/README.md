# 系统级外部 API（`core/api/`）

> **读者：** 站长对接商城 / 计划任务、核心维护者。  
> **不是** 主题开发文档。自研主题请读根目录 [`CORE模块说明.md`](../../CORE模块说明.md) 与《主题通用规范》。  
> **权威规范：** [`开发规范/系统级外部API规范.md`](../../开发规范/系统级外部API规范.md) · [`开发规范/失败代码规范.md`](../../开发规范/失败代码规范.md)（**12xxx**）  
> **后台文档页：** 系统管理 → 系统 API（数据：`assets/js/sysapi-catalog/{短名}.json` + 公共失败码 `errcode.json`）

---

## 一、这是什么

站外机器用 **系统密钥** 调用的 HTTP 入口，物理文件在本目录，对外 URL **无 `.php`**：

| 短名 | 物理文件 | 正式 URL | 开关（`sysapion` 字段） |
|------|----------|----------|-------------------------|
| cardkey | `cardkey.php` | `/core/api/cardkey` | `cardkey` |
| checkin | `checkin.php` | `/core/api/checkin` | `checkin` |
| userinfo | `userinfo.php` | `/core/api/userinfo` | `userinfo` |
| apilogarchive | `apilogarchive.php` | `/core/api/apilogarchive` | `apilogarchive` |
| apiquery | `apiquery.php` | `/core/api/apiquery` | `apiquery` |
| apirank | `apirank.php` | `/core/api/apirank` | `apirank` |
| sitestats | `sitestats.php` | `/core/api/sitestats` | `sitestats` |

开关总闸：配置键 `sysapion`（JSON，字段=上表短名，值 `0`/`1`）。判断用 `SystemApiKey::isGateOn('{短名}')`。

限流：配置键 `sysapirq`（JSON：`apis.{短名}.qpm` / `qps`；**`0`=不限**）。后台「系统级 API」各细条可配；鉴权通过后由 `requireAuthorizedGate` → `enforceConfiguredLimit` 统一执行（先 QPS 窗 1 秒，再 QPM 窗 60 秒）。计数维度：系统密钥指纹 + IP + 短名。超限 `errcode=12005`。**禁止**在各 `core/api/*.php` 再手写分桶限流。

鉴权（三态 · E428）：Bearer / `X-Api-Key` / Query `?key=`（亦认 POST·JSON `key|apikey`）。业务参数支持 GET Query / POST 表单 / JSON。生产推荐 Header/Bearer；Query 易进日志。入口 `requireAuthorizedGate`：闸关与错钥同 `12001`。  
失败体：`{"code":0,"msg":"…","errcode":12001}`（**12xxx**，`SystemApiError`）。成功：`code=1`，无 `errcode`。HTTP 固定 **200**。

**主题禁止** 冒充调用本目录接口或直调下述内部类做前台展示。

---

## 二、配套核心类（`core/` 根下）

| 类 | 文件 | 职责 |
|----|------|------|
| `SystemApiKey` | `SystemApiKey.php` | 密钥、mergeInput、`fail`/`ok`、**`sysapion` 开关闸**、**`sysapirq` 单接口 QPM/QPS** |
| `SystemApiError` | `SystemApiError.php` | 失败码 12001～12015 |
| `SystemApiCatalog` | `SystemApiCatalog.php` | 后台文档目录（读 `{短名}.json` + 公共 `errcode.json`） |
| `SystemApiPublic` | `SystemApiPublic.php` | 接口查询 / 调用排行 / 用户信息 / 全站概况业务（脱敏） |

相关业务还可落到 `CardKeyManager`、`CheckinManager`、`ApiLogArchive`、`PointsManager` 等（主题仍走 `Frontend*`）。

---

## 三、各入口摘要

### `cardkey.php` · 卡密对接

- `action`：`generate` / `stock` / `take` / `void` / `query`
- 限流见本接口后台 QPM/QPS；作废单次最多 200 张

### `checkin.php` · 签到

- `action`：`do`（也认 `checkin`）/ `status` / `config`
- 用户：`userid` / `username` / `email` 三选一；限流在 `requireAuthorizedGate` 内统一执行
- 未注册 `errcode=12012` + `reason=not_registered`；封禁 `12013` + `banned`
- 代签到流水备注：`每日签到奖励（API）`（用户中心为 `每日签到奖励`）

### `userinfo.php` · 用户信息

- `action`：`get`（默认）；可选 `date=Y-m-d`（近 7 日内某一天）
- 用户：`userid` / `username` / `email` 三选一；限流在 `requireAuthorizedGate` 内统一执行
- 返回资料、积分、调用、签到、近 7 日排行；封禁用户仍可查（`status=0`）
- 禁止密码 / OAuth；未注册 `12012`

### `apilogarchive.php` · 日志清理

- `action`：`archive` / `purge` / `mirror`（必填）
- 限流见本接口后台 QPM/QPS（不再按 action 写死次数）

### `apiquery.php` · 接口查询

- `action`：`get` / `search` / `random`（可省略并按参数推断；纯密钥默认 random）
- 仅前台可见接口；无 upkey / 上游 URL

### `apirank.php` · 调用排行

- `period`：`today` / `7d` / `30d` / `total`（及别名）
- 可选 `limit` 1～50

### `sitestats.php` · 全站概况

- `action`：`get`（默认）
- 返回 `stats`：`calls` / `today_calls` / `pointsspent` / `users`
- 须 `sysapion.sitestats`；不含用户明细与密钥

参数表、请求/响应示例、白话文档：以后台「系统 API」页或 `assets/js/sysapi-catalog/{短名}.json` 为准。

---

## 四、新增接口时

1. 本目录新增 `{短名}.php`（`requireAuthorizedGate` → mergeInput → 业务 → `fail`/`ok`；**勿**再手写限流）  
2. `SystemApiKey::knownGateIds()` 增加短名；`database.sql` 的 `sysapion` / `sysapirq` JSON 补字段（开关默认 `0`，限流默认 `qpm/qps=0`）  
3. `assets/js/sysapi-catalog/{短名}.json`（`enabled_key=sysapion`、`enabled_gate={短名}`）+ 写入 `index.json`  
4. 系统设置：勾选走 `setGate`；QPM/QPS 走 `setGateRate`  
5. 同步《系统级外部API规范》、本 README、失败码表（若扩 12xxx）  
6. **不要**把细则写进根目录 `CORE模块说明.md`（该文只服务主题对接）；**禁止**再新增独立 `xxxon` 配置键（E403）

---

**版本：** 与 `core/version.php` 一致（当前 13.26.46）
