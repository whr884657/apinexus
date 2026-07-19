# ApiNexus 2.6.0 发行说明

**发布日期：** 2026-07-12  
**类型：** 中版本（青绿 UI 架构重构）

---

## 变更摘要

### 用户中心 st-dash（与 default 完全不同）

| default | slate（青绿） |
|---------|---------------|
| 左侧固定侧边栏 `vs-admin-shell` | **顶部青绿渐变导航栏** + 横向菜单胶囊 |
| 白底顶栏 + 侧栏退出 | 顶栏内用户头像 + 文字「退出」（无绿色色块） |
| 调色盘可能浮在页面遮挡 | **调色盘挂载顶栏**（`vs-admin-body` + `#vsThemePickerMount`） |

### 认证页（login / register / forgot / bind）

- **无卡片**：去掉白底圆角盒子、阴影
- **无站点图标**：不显示 Logo / 占位图标
- **全屏居中**：表单区域垂直水平居中
- **标题右对齐 + 呼吸动效**（`stAuthTitlePulse`）
- 输入框底线风格；验证码与按钮同行

---

## 下载

https://gitee.com/xunjinlu/apinexus/releases/download/v2.6.0/apinexus2.6.0.zip

---

*ApiNexus 2.6.0*
