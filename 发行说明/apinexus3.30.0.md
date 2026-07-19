# ApiNexus 3.30.0 发行说明

**日期：** 2026-07-18  
**类型：** 大版本（令牌命名体系 + 主题二用户中心体验）

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v3.30.0/apinexus3.30.0.zip

## 变更

1. **令牌管理命名纠正**  
   - 页面：`user/keys.php`、`admin/api/keys.php`（废弃 `tokens.php`）  
   - 脚本：`user-keys.js`、`admin-keys.js`  
   - 约定：**界面称「令牌」**；库/类用 `apikey` / `ApiKeyManager`；英文 `token` 不用于本功能页名  

2. **密钥一键复制**  
   用户中心与管理后台点击密钥即可复制。

3. **主题二导航设置生效于用户中心**  
   「顶部三横线 · 右侧抽屉」与「右下角圆形按钮」与前台一致；此前用户中心写死 FAB。

4. **主题二调色同步**  
   用户中心顶栏增加调色盘；与前台共用 `st_theme_tint`，临时配色前后台一致。

5. **主题设置加载优化**  
   配置页首屏已由 PHP 渲染，切换 Tab 不再强制 AJAX；用户页去掉重复 `admin.css`。

## 升级注意

- 无数据库结构变更。  
- 升级包会清理旧文件：`user/tokens.php`、`admin/api/tokens.php`、`user-tokens.js`、`admin-tokens.js`。  
- 书签若仍指向 `/user/tokens` 请改为 `/user/keys`。

## 库变更

否。
