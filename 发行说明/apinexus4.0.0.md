# ApiNexus 4.0.0

## 变更说明

- **品牌更名**：产品名称由 misc-api 全面更名为 **ApiNexus**（代码注释、安装向导、文档、默认站点名等）
- **仓库迁移**：主仓库变更为 [xunjinlu/apinexus](https://gitee.com/xunjinlu/apinexus)
- **更新包**：命名 `apinexus{版本}.zip`；`Updater` 云端清单与发行包直链同步新仓库
- **默认值**：新装默认站点名 / 发信显示名为 ApiNexus；Redis 默认键前缀 `apinexus:`

## 升级说明

- 无数据库结构变更
- 已安装站点：若 `site_name` / `mail_from_name` / `redis_prefix` 已在后台改过，**不会**被覆盖；仅新装与代码默认值变更
- 在线更新将拉取新仓库 `update.json`；请确保站点可访问 `gitee.com/xunjinlu/apinexus`
- 覆盖升级后请强刷后台与前台资源（Ctrl+F5）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v4.0.0/apinexus4.0.0.zip
