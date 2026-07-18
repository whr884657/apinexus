# misc-api 3.34.0

## 变更说明

- 订单管理（仅充值订单）与积分变动（已入账流水）拆分为独立页面；状态彩色标签；列表支持「每页条数」
- 支付配置：支付方式改为带图标的切换按钮；充值套餐改为添加/编辑卡片（后端仍存 JSON）
- 去掉财务/充值相关页重复 H1 与多余描述
- 收费接口保存时强制密钥为「必须」；开启必须/可选密钥时自动写入 `key` 请求参数
- 接口请求方式 GET/POST 改为切换按钮（管理员与用户后台）
- 用户充值中心、积分变动 UI 重做：自定义金额卡片弹窗、二维码中心支付图标、弹窗按钮等高
- 码支付回调逻辑在 `core/play/codeplay/`；公网入口为带 `.php` 的 `paynotify.php` / `payreturn.php`（下单时作为 `notify_url`/`return_url` 传入，**不改伪静态**）
- **说明（3.34.1）**：根目录入口已移除，请直访 `core/play/codeplay/notify.php` / `return.php`

## 升级说明

- 本版无数据库结构变更；在线升级覆盖代码即可
- 无需为支付回调调整 Nginx / `.htaccess`；异步通知由本系统下单参数自动携带，不必在码支付商户后台单独配置回调为主路径

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v3.34.0/misc-api3.34.0.zip
