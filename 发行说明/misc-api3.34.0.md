# misc-api 3.34.0

## 变更说明

- 订单管理（仅充值订单）与积分变动（已入账流水）拆分为独立页面；状态彩色标签；列表支持「每页条数」
- 支付配置：支付方式改为带图标的切换按钮；充值套餐改为添加/编辑卡片（后端仍存 JSON）
- 去掉财务/充值相关页重复 H1 与多余描述
- 收费接口保存时强制密钥为「必须」；开启必须/可选密钥时自动写入 `key` 请求参数
- 接口请求方式 GET/POST 改为切换按钮（管理员与用户后台）
- 用户充值中心、积分变动 UI 重做：自定义金额卡片弹窗、二维码中心支付图标、弹窗按钮等高
- 码支付回调迁入 `core/play/codeplay/`，公网入口为 `codepay.php/notify`（并支持 `/codepay/notify` 伪静态）

## 升级说明

- 本版无数据库结构变更；在线升级覆盖代码即可
- 若 Nginx 未走 Apache `.htaccess`，请按 `nginx伪静态配置.md` 增加 `/codepay/notify|return` 规则（也可直接使用 `/codepay.php/notify`）
- 码支付商户后台请将异步通知地址改为新路径

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v3.34.0/misc-api3.34.0.zip
