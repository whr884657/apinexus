# ApiNexus 13.3.0

**发布日期：** 2026-07-28

## 变更摘要

- **飞线身份三色：** 绿=带密钥成功、黄=游客成功、红=失败；同一地区可同时出现多种颜色粒子
- **飞线样式：** 对齐参考 UI（隐形轨迹 + 小圆粒子 + 短拖尾）
- **本地统计：** 唯一写法 `require bootstrap` + `ApiStats::hit(接口ID)`；必须填写后台接口数字 ID

## 升级注意

本地接口若仍使用旧的「向上查找三行 / hit.php / 无 ID 的 hit()」，升级后**不会记账**。请按 `api/统计代码使用说明.md` 改为带接口 ID 的两行写法。

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v13.3.0/apinexus13.3.0.zip
