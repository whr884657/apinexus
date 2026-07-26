# ApiNexus 11.1.0 发行说明

**发布日期：** 2026-07-27  
**下载：** https://gitee.com/xunjinlu/apinexus/releases/download/v11.1.0/apinexus11.1.0.zip

## 本版摘要

去掉管理员昼夜更替与自定义取色；AI 代码示例改为单块逐次生成；接口文档编辑关闭右侧预览。

## 变更要点

| 项 | 说明 |
|----|------|
| 昼夜更替 | 整模块删除（按钮/CSS/`admin_color_scheme`/`vs-scheme-dark`），不影响调色盘底色功能 |
| 调色盘 | 仅系统 24 色预设；登录/注册/忘记密码/后台均不可自定义取色 |
| AI 代码示例 | 一次请求只生成一个鉴权+一种语言的 `:::qs`；再合并 |
| 文档编辑 | 接口列表 `doc`/`aidoc` 使用 `data-vs-md="off"`，无在线预览 |

## 同版复查修复

| 项 | 说明 |
|----|------|
| AI 单块提取 | 模型若一次吐多语言，只保留本次请求的 lang/auth，避免污染合并结果 |
| 预加载白名单 | `vs_theme_bg_preload_script` 仅应用固定 24 色 |
| applyPageBackground | 走预设校验，不可绕过涂任意色 |

## 升级注意

1. **无数据库结构变更**（`db_changes: false`）。
2. 若本地曾开启夜间模式，升级后会自动清理 `admin_color_scheme` 与 `vs-scheme-dark`。
3. 若曾用自定义色板保存非预设色，将回落默认白色背景。
4. AI 生成时间随鉴权数×9 增加，请保持超时 60～300 秒。

## 相关文件

- `assets/js/theme-picker.js` / `assets/css/theme-picker.css`
- `core/AiApiDoc.php` / `admin/api/list.php`
- `core/helpers.php`（预加载清理）
