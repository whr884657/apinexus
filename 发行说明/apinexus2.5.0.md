# ApiNexus 2.5.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（青绿用户中心重构）

---

## 变更摘要

### 青绿用户中心（st-uc）

- 完全重构 `user/layout.php` + `assets/user.css`，与默认主题 admin 风格彻底区分
- 浅绿侧边栏、圆角导航、用户头像芯片、内容区卡片化统计
- **修复**顶栏「退出」hover 出现绿色实心色块（错误地将 `.vs-topbar__logout:hover` 与按钮同色）

### 认证页验证码行

- 注册/忘记密码：验证码输入与「获取验证码」**保持同一行**（手机端不再上下堆叠）
- 按钮固定宽度、字号自适应，避免撑破布局

### 双主题前台登录态入口

- 抽屉底部 / 顶栏：未登录 →「登录 / 注册」实心按钮
- 已登录 → **用户真实头像** +「用户中心」描边卡片式入口（hover 轻微上浮）

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v2.5.0/apinexus2.5.0.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

*ApiNexus 2.5.0*
