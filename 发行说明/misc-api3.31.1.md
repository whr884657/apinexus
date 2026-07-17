# misc-api 3.31.1 发行说明

**日期：** 2026-07-18  
**类型：** 小版本（列表底栏 / 卡片边框 / 参数类型 vs-pick）

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v3.31.1/misc-api3.31.1.zip

## 变更

1. **用户中心底栏外置**  
   API 管理、令牌管理的分页与「共 N 个」移到 `.vs-panel` 外，与管理员接口列表一致。

2. **末张卡片底边**  
   修复多卡片时最后一张底边被 `last-child` 规则吃掉的问题。

3. **参数类型**  
   选项展示为 `string · 字符串` 等形式；下拉改用 `vs-pick`（非浏览器原生观感）。

4. **规范**  
   全量开发规范补充：回复须称「老板」；已有规范必须遵守（E26/E27）。

## 升级注意

- 无数据库结构变更。  
- 强刷 `admin.css`、`api-params-editor.js`、`vs-pick.js`。

## 库变更

否。
