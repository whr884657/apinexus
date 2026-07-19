# ApiNexus 4.7.0

**发布日期：** 2026-07-20  
**下载：** [apinexus4.7.0.zip](https://gitee.com/xunjinlu/apinexus/releases/download/v4.7.0/apinexus4.7.0.zip)

## 变更说明

- **在线测试同源中继**：`PlaygroundRelay` + 入口 `core/playground/relay.php`（各主题共用；带 `.php` 直访），修复代理 302 与外链导致的 `Failed to fetch`
- **首页终端**：请求方式徽章着色；切换 Method 同步更新选择框；登录用户自动填入可用 KEY
- **详情页**：测试区请求地址始终显示公开地址（不拼接参数/不跳上游）；信息区分类/KEY/计费/时间配色；复制提示改顶部
- **导航**：登录后顶栏与侧栏显示头像 +「用户中心」

## 升级说明

- 无数据库结构变更
- 中继地址为 `/core/playground/relay.php`（勿再使用已废弃的根目录 `/play.php`；在线更新会按 `obsolete-files.json` 清理）
