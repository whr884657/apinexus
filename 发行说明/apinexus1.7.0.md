# ApiNexus 1.7.0 发行说明

**发布日期：** 2026-07-11  
**类型：** 大版本（安全 + 用户管理搜索 + 内容占位）

---

## 变更摘要

### 安全

- 新增 **`404.php`** 全站错误页，含《网络安全法》《刑法》相关法律提示
- Apache 配置 `ErrorDocument 404`；PHP 可调用 `vs_render_404_page()`
- 延续全站 POST 的 CSRF + 同源校验

### 用户管理 · 列表搜索

| 端 | 行为 |
|----|------|
| 电脑端 | 搜索框在「用户列表」标题右侧，默认常显 |
| 手机端 | 标题靠右；左侧放大镜点击后搜索框向左展开 |
| 过滤 | 前端静态匹配 ID / 用户名 / 邮箱，无整页刷新 |

### 内容运营

- 新增 **公告管理** 占位：`/admin/content/announcements.php`

### 文档（本地维护）

- 新增 **`开发规范与功能优化.md`**：页面删旧建新、安全、功能优化约定

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v1.7.0/apinexus1.7.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

- 无需数据库迁移
- Nginx 用户请在 server 块配置：`error_page 404 /404.php;`

---

*ApiNexus 1.7.0*
