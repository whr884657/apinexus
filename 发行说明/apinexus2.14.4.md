# ApiNexus 2.14.4 发行说明

**发布日期：** 2026-07-12  
**类型：** 小版本（补丁）

## 下载

- ZIP：https://gitee.com/xunjinlu/apinexus/releases/download/v2.14.4/apinexus2.14.4.zip
- 标签：`v2.14.4`

## 变更摘要

1. **累计调用次数**  
   首页统计区第三项标签改为「累计调用次数」，数值来自系统配置 `api_total_calls`（默认 0）。可在数据库 `vs_config` 表写入该键展示对外统计。

2. **移除昼夜切换**  
   主题一不再提供深色模式切换，固定浅色 UI，避免分类标签等元素在切换后样式异常。

3. **分类标签样式**  
   选中态统一为深色底 + 白字，悬停态适配浅色背景。

## 配置示例

```sql
INSERT INTO `vs_config` (`key`, `value`) VALUES ('api_total_calls', '2000')
ON DUPLICATE KEY UPDATE `value` = '2000';
```

## 升级说明

无数据库结构变更，可直接在线更新或 ZIP 覆盖（保留 `config/database.php`）。
