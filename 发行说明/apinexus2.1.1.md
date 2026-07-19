# ApiNexus 2.1.1 发行说明

**发布日期：** 2026-07-11  
**类型：** 小版本（青绿平台主题优化）

---

## 变更摘要

### 主题命名

- 主题二（slate）由「云启风格」更名为 **「青绿平台」**
- 风格特征：白底 + 青绿主色（`#24a66a`）+ API 接口平台布局

### 青绿平台首页

- 移除「快速入口」列表
- 新增：收录接口数 / 今日调用 / 累计调用统计条
- 新增：搜索框 + 接口分类标签（全部、生活服务、图片相关等）
- 接口列表区域预留，模块完善后接入

### 页脚

- 四列布局：品牌、资源、支持、关于（与参考 HTML 一致）

### 预览图规范

- 各主题预览图路径：`core/theme/{id}/preview.png`
- **由站长自行截图放置**，系统不再自动生成 SVG 预览图

### 本地参考目录

- `主题参考/` 保留在本地使用，已在 `.gitignore` 排除，不上传 Gitee

---

## 下载

| 类型 | 链接 |
|------|------|
| 源码 ZIP | https://gitee.com/xunjinlu/apinexus/releases/download/v2.1.1/apinexus2.1.1.zip |
| 仓库 | https://gitee.com/xunjinlu/apinexus |

---

## 升级说明

- 无需数据库迁移
- 升级后请自行将主题预览截图保存为：
  - `core/theme/default/preview.png`
  - `core/theme/slate/preview.png`

---

*ApiNexus 2.1.1*
