# 个人调用数据查询 API 接口文档

## 接口描述

本 API 用于查询当前调用密钥所属用户的个人调用与积分数据，以及本人接口调用排行。通过传入有效密钥，可获取累计 / 今日 / 近 7 日的调用与消耗，也可获取今日排行榜、近 7 日排行榜（含接口名称与调用次数）。

## 功能特性

- 凭调用密钥识别本人，仅返回该用户自己的数据
- **请求参数很少**：只要 `key`；需要少返回时再加一个 `q`
- `q` 支持**字母版与数字版**，权值相同：`a`=`1`、`b`=`2`…`i`=`9`；全部用 `all` 或 `0`（或不传）；可混写，中间**不用逗号**
- 支持 GET / POST；密钥支持 Query、Header、Bearer（推荐 Header）

## 接口地址

```
https://你的域名/api/index.php
```

（须先在后台「接口管理」挂载本本地接口，并将站点文件 `api/index.php` 中的 `$vsUserStatsApiId` 改为真实数字 ID。）

## 请求参数

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| key | string | 是* | 调用密钥（也可用 `api_key` / `apikey`，或请求头 `X-API-Key` / `Authorization: Bearer …`） |
| q | string | 否 | 选返回项。不传 / `all` / `0` = 全部。字母与数字等价，见下表 |

\* 必须提供有效且已启用的调用密钥。

### `q` 对照表（字母 = 数字，二选一或混写）

| 字母 | 数字 | 返回键 | 含义 |
|------|------|--------|------|
| a | 1 | calls | 累计调用量 |
| b | 2 | spent | 累计积分消耗 |
| c | 3 | points | 积分余额 |
| d | 4 | today | 今日调用量 |
| e | 5 | tspent | 今日积分消耗 |
| f | 6 | week | 近 7 日调用量 |
| g | 7 | wspent | 近 7 日积分消耗 |
| h | 8 | rank | 今日接口调用排行 |
| i | 9 | rank7 | 近 7 日接口调用排行 |
| all | 0 | （全部） | 与不传 `q` 相同 |

### 写法示例

| 传入 | 返回 |
|------|------|
| （不传）或 `all` 或 `0` | 全部 |
| `a` 或 `1` | 仅累计调用 |
| `c` 或 `3` | 仅积分余额 |
| `ac` 或 `13` | 累计调用 + 积分余额 |
| `de` 或 `45` | 今日调用 + 今日消耗 |
| `hi` 或 `89` | 今日排行 + 近 7 日排行 |
| `abcdefg` 或 `1234567` | 全部数字统计（不含排行） |
| `a3` 或 `1c` | 累计调用 + 积分余额（混写） |

重复码只算一次。不要用逗号。

## 请求参数 (JSON格式)

```json
[
  {
    "name": "key",
    "type": "string",
    "required": true,
    "description": "调用密钥；也可用 api_key / apikey，或请求头 X-API-Key / Authorization: Bearer"
  },
  {
    "name": "q",
    "type": "string",
    "required": false,
    "description": "选返回项：字母 a～i 与数字 1～9 等价，如 ac、13、hi、89；全部用 all 或 0；不传也=全部；不要用逗号"
  }
]
```

## 返回字段说明

| 返回键 | 含义 | 类型 |
|--------|------|------|
| calls | 累计调用量 | 数字 |
| spent | 累计积分消耗 | 数字 |
| points | 积分余额 | 数字 |
| today | 今日调用量 | 数字 |
| tspent | 今日积分消耗 | 数字 |
| week | 近 7 日调用量 | 数字 |
| wspent | 近 7 日积分消耗 | 数字 |
| rank | 今日接口调用排行 | 数组：`[{name, calls}, …]`，最多 10 条 |
| rank7 | 近 7 日接口调用排行 | 数组：`[{name, calls}, …]`，最多 10 条 |

排行中的 `name` 为接口名称，`calls` 为对应周期内的调用次数。

## 响应格式

### 全部（只传 key）

```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "calls": 1280,
    "spent": 256.5,
    "points": 36.5,
    "today": 12,
    "tspent": 1.5,
    "week": 86,
    "wspent": 18.25,
    "rank": [
      {"name": "一言接口", "calls": 8},
      {"name": "天气查询", "calls": 4}
    ],
    "rank7": [
      {"name": "一言接口", "calls": 52},
      {"name": "天气查询", "calls": 31}
    ]
  }
}
```

### 传入 `q=ac`（累计调用 + 积分余额）

```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "calls": 1280,
    "points": 36.5
  }
}
```

### 传入 `q=hi`（双排行）

```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "rank": [
      {"name": "一言接口", "calls": 8}
    ],
    "rank7": [
      {"name": "一言接口", "calls": 52},
      {"name": "天气查询", "calls": 31}
    ]
  }
}
```

### 失败响应

```json
{
  "code": 0,
  "msg": "请提供调用密钥",
  "errcode": 11001
}
```

## 代码示例

### Header 传密钥（推荐）+ 全部

```bash
curl -H "X-API-Key: SK-你的密钥" "https://你的域名/api/index.php"
```

### 只要累计调用 + 余额（q=ac 或 q=13）

```bash
curl -H "X-API-Key: SK-你的密钥" "https://你的域名/api/index.php?q=ac"
```

### 只要双排行（q=hi 或 q=89）

```bash
curl -H "X-API-Key: SK-你的密钥" "https://你的域名/api/index.php?q=89"
```

### 只要数字统计不要排行（q=abcdefg 或 q=1234567）

```bash
curl -H "X-API-Key: SK-你的密钥" "https://你的域名/api/index.php?q=1234567"
```

### POST

```bash
curl -X POST "https://你的域名/api/index.php" \
  -H "X-API-Key: SK-你的密钥" \
  -d "q=hi"
```

## 错误响应

| errcode | 消息（示例） | 描述 |
|---------|--------------|------|
| 11008 | 接口未配置 | 站点文件未填写真实接口 ID（`$vsUserStatsApiId`） |
| 11001 | 请提供调用密钥 | 未提供调用密钥 |
| 11002 | 密钥错误 | 密钥不正确 |
| 11003 | 密钥已禁用 | 密钥已禁用 |
| 11005 | 请求过于频繁，请稍后再试 | 触发接口 QPM 限制（若后台已配置） |
| 11006 | 该接口维护中 | 接口处于维护状态 |
| 11007 | 该接口已经被禁用 | 接口已禁用 |
| — | 不支持的查询字段 | `q` 含非法字符（无 errcode） |

说明：业务失败时 HTTP 仍为 200，请以响应体 `code` / `errcode` 判断。成功时 `code` 为 `1`。

## 技术实现

- 本地 PHP 接口，经平台 `ApiStats::hit` 完成调用守卫与统计记账
- 只读查询逻辑集中在 `core/UserCallStats.php`（与用户控制台同源的近 7 日日桶 / 密钥累计数据），主题与其它页面禁止直查库
- 排行数据来自本人 `stat7` 日桶聚合，返回项仅含 `name`、`calls`
- 响应为 `application/json`；不做 JSONP

## 注意事项

- 须携带有效调用密钥；只返回密钥所属用户本人的数据与排行
- 请求侧只有 `key` + 可选 `q`；`q` 字母与数字等价（`a`=`1`…`i`=`9`），全部用 `all` 或 `0`，可混写，**不要用逗号**
- 排行最多约 10 条；无调用时对应排行为空数组 `[]`
- 后台须将该接口配置为「密钥 = 必须」，并建议配置 QPM
- 站长部署：新建本地接口，endpoint 为 `/api/index.php`，并把 `$vsUserStatsApiId = 0` 改为真实数字 ID（未改则直接返回「接口未配置」）
- 推荐用请求头传密钥（`X-API-Key` / Bearer）；Query 传密钥易进日志与 Referer
- 成功调用本接口会计入调用统计
- 支持 GET、POST；禁止未校验的 JSONP `callback`
- 含积分余额，跨域 `Access-Control-Allow-Origin: *` 有泄露面；浏览器场景建议同源代理或改白名单
- 更细说明见：`api/统计代码使用说明.md`、`api/接口开发安全须知.md`

## 开发时间

2026-08-16
