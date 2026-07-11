# misc-api 2.4.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（青绿平台 UI 重设计）

---

## 变更摘要

### 认证页（login / register / forgot / bind）

- **修复 Logo 尺寸异常**：`st-auth__logo` 强制 36～48px，`object-fit: cover`
- **双栏自适应布局**：桌面端左侧青绿渐变品牌区 + 右侧表单；手机端单栏居中
- **原生动画交互**：卡片入场、输入聚焦光晕、提交 loading、错误抖动、按钮光泽扫过

### 首页统计

- 改为参考主题 **单行胶囊条**：`收录 0 个接口 | 今日调用 0 次 | 累计调用 0 次`
- 取消三块独立卡片堆叠（避免手机上「上中下 / 上二下一」）

### 返回顶部

- 按钮移至 `layout/footer.php`，**全站前台页面**可用
- 滚动超过 400px 淡入显示，底部居中，平滑回顶

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/misc-api/releases/download/v2.4.0/misc-api2.4.0.zip |
| 仓库 | https://gitee.com/xunjinlu/misc-api |

---

## 升级说明

- 无需数据库迁移
- 切换青绿平台后刷新浏览器缓存（`auth.css` / `theme.css` 有更新）

---

*misc-api 2.4.0*
