# misc-api 2.7.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（青绿用户中心侧边栏重构）

---

## 变更摘要

### 用户中心 st-uc（恢复侧边栏 + 对齐 default）

| 项目 | 说明 |
|------|------|
| 布局 | 恢复侧边栏结构，HTML 类名对齐 `vs-admin-shell` / `vs-sidebar` / `vs-topbar` |
| 风格 | 极浅青绿底，侧栏/顶栏/内容区白 → 青绿渐变过渡 |
| 侧栏展开 | **右下角三角横线悬浮按钮**（FAB），默认收起，点击展开 |
| 调色盘 | **已移除**，青绿风格固定 |
| 圆角 | 面板/按钮圆角由 18px 降至 6–8px |
| 账号设置 | 头像 `max-width` / `max-height` 限制，防止溢出屏幕 |

### 技术说明

- `st-uc-body` + `data-theme-picker="off"`：`theme-picker.js` 跳过初始化
- 引入 `admin.css` 作为壳层基础，`user.css` 仅做青绿覆盖

---

## 下载

https://gitee.com/xunjinlu/misc-api/releases/download/v2.7.0/misc-api2.7.0.zip

---

*misc-api 2.7.0*
