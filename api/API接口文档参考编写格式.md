# 【参考模板】示例城市天气查询 API 接口文档

> 本文档为站内「接口说明」写作参考格式。文中域名、接口名、参数与返回值均为**虚构示例**，不可当作真实线上接口使用。编写正式文档时，请整段替换为真实业务内容，并删除本提示条。

## 接口描述

本 API 用于按城市名称查询当日示例天气摘要。通过传入城市名，可获取温度区间、天气现象、风力与更新时间等结构化数据；可选返回一张简易示意图（示例用途）。

## 功能特性

- 按城市名查询当日示例天气
- 支持返回 JSON 结构化数据
- 可选返回示意图片（`type=img`）
- 参数少、字段名短，便于对接演示

## 接口地址

```
https://demo.example.com/api/weather/index.php
```

## 请求参数

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| city | string | 是 | 城市名称，示例：`云城`、`星港` |
| type | string | 否 | 返回类型：`json`（默认）或 `img`（示意图片） |
| unit | string | 否 | 温度单位：`c`（摄氏，默认）或 `f`（华氏） |

## 请求参数 (JSON格式)

```json
[
  {
    "name": "city",
    "type": "string",
    "required": true,
    "description": "城市名称，示例：云城、星港"
  },
  {
    "name": "type",
    "type": "string",
    "required": false,
    "description": "返回类型：json（默认）或 img（示意图片）"
  },
  {
    "name": "unit",
    "type": "string",
    "required": false,
    "description": "温度单位：c（摄氏，默认）或 f（华氏）"
  }
]
```

## 响应格式

### JSON 格式响应

当请求为 `https://demo.example.com/api/weather/index.php?city=云城&type=json` 时，返回示例：

```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "city": "云城",
    "date": "2099-01-15",
    "weather": "多云",
    "temp_low": 18,
    "temp_high": 26,
    "unit": "c",
    "wind": "东南风 2 级",
    "humidity": 62,
    "updated_at": "2099-01-15 08:30:00",
    "tip": "示例数据，仅供文档演示"
  }
}
```

### 图片格式响应

当请求为 `https://demo.example.com/api/weather/index.php?city=云城&type=img` 时，直接返回生成的示意图片（`image/jpeg` 或 `image/png`），无 JSON 包一层。

## 示意图片内容（可选说明）

若接口支持出图，示意海报可包含：

- 标题：示例城市天气
- 城市名称与日期
- 天气现象与温度区间
- 风力、湿度等摘要
- 底部注明「示例数据，非真实预报」

## 代码示例

### 请求 JSON 数据

```bash
curl "https://demo.example.com/api/weather/index.php?city=云城&type=json"
```

### 指定华氏单位

```bash
curl "https://demo.example.com/api/weather/index.php?city=星港&unit=f"
```

### 请求示意图片

```bash
curl -o weather-demo.jpg "https://demo.example.com/api/weather/index.php?city=云城&type=img"
```

### POST 示例

```bash
curl -X POST "https://demo.example.com/api/weather/index.php" \
  -d "city=云城" \
  -d "type=json"
```

## 错误响应

| code | msg（示例） | 描述 |
|------|-------------|------|
| 0 | 请提供城市名称 | 缺少 `city` |
| 0 | 城市不存在 | 城市名无法识别 |
| 0 | 无效的 type 参数 | `type` 不是 `json` / `img` |
| 0 | 服务暂不可用 | 上游或本地处理失败 |

失败时 HTTP 仍可为 200，请以响应体 `code` 判断（本模板约定成功 `code=1`）。

失败响应示例：

```json
{
  "code": 0,
  "msg": "请提供城市名称"
}
```

## 技术实现（模板说明，可按真实接口改写）

- 本地 PHP 接口，经平台统计守卫后返回 JSON / 图片
- JSON 使用 `application/json; charset=utf-8`
- 出图可使用 GD / Imagick 等（按实际实现填写）
- 禁止未校验的 JSONP `callback`

## 注意事项

- 本文全部为虚构示例，域名、城市、数值均非真实业务
- 正式文档请替换接口地址、参数表、响应示例与错误码
- 建议注明支持的请求方法（GET / POST）及鉴权方式（如需密钥）
- 涉及密钥时，勿在文档中粘贴真实密钥，使用占位符如 `SK-你的密钥`

## 开发时间

2099-01-01
