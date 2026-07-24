# ApiNexus 8.0.1 发行说明

**版本：** 8.0.1  
**日期：** 2026-07-24  
**数据库：** 无结构变更

## 变更摘要

- **修复搜索放大镜飞出输入框**：搜索栏在标题行 `headerActions` 内，相关定位样式改挂 `.vs-api-list-toolbar`（不再只写在 `#apiListPage`）
- **手机标题行对齐参考设计**：标题 +「添加接口」同行，搜索框独占下一行满宽
- **消除手机整页左右滑动 / 右侧大片空白**：收紧 `min-width`、主区 `overflow-x: clip`、筛选与卡片 `max-width: 100%`
- **手机卡片标签**：分类/类型/收费/KEY 独立成行自动换行；状态徽章单独靠右，缓解拥挤
- 补充易错点 **E95** 与《列表底部分页规范》§六

**升级说明：** 覆盖更新即可；无需执行数据库结构更新。

下载：https://gitee.com/xunjinlu/apinexus/releases/download/v8.0.1/apinexus8.0.1.zip
