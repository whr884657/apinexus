# ApiNexus 3.34.1

## 变更说明

- 订单管理 / 积分变动：筛选按钮与「刷新」并入布局标题行（与接口列表工具栏一致）
- 财务列表改为紧凑卡片，去掉手机端空表头卡片问题
- 码支付异步/回跳改为直访 `core/play/codeplay/notify.php`、`return.php`；删除根目录 `paynotify.php` / `payreturn.php`

## 升级说明

- 无数据库变更；覆盖升级即可
- 新下单会自动携带新的 `notify_url`；无需改伪静态

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.34.1/apinexus3.34.1.zip
