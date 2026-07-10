# misc-api · Gitee 推送与发行流程（固定版）

> **用途：** 以后每一次版本发布，**只按本文档顺序执行**，不要临时换命令、不要换上传方式。

速查打勾见 **[发布检查清单.md](发布检查清单.md)**。

---

## 零、仓库信息（发版前确认）

| 项目 | 内容 |
|------|------|
| **仓库主页** | https://gitee.com/xunjinlu/misc-api |
| **克隆地址** | `https://gitee.com/xunjinlu/misc-api.git` |
| **默认分支** | `main` |
| **私人令牌** | `739a0969faa619470e04bf0ed12f59d5` |
| **本地项目路径** | `D:\api.xunjinlu.fun\api系统源码\api系统源码全新框架` |
| **更新包命名** | `misc-api{版本号}.zip`（如 `misc-api1.0.0.zip`） |
| **更新包下载路径** | `https://gitee.com/xunjinlu/misc-api/releases/download/v{版本号}/misc-api{版本号}.zip` |

> 下文命令中的 `$token` 可直接使用上表私人令牌；推送 URL 格式：`https://xunjinlu:739a0969faa619470e04bf0ed12f59d5@gitee.com/xunjinlu/misc-api.git`

### 仓库简介（对外描述，随版本定期优化）

misc-api 是基于 PHP + MySQL 的轻量级 Web 管理系统，提供安装向导、后台管理、安全认证、系统设置与云端在线更新。源码完全开源，可个人/商业免费使用，详见 LICENSE。

**维护要求：** 功能模块、目录结构或对外能力有变动时，除更新 README 外，应同步检查并优化 Gitee 仓库简介，保持与当前系统一致。

---

## 一、固定流程总览（九步，顺序不可乱）

| 步骤 | 做什么 | 完成标志 |
|------|--------|----------|
| **1** | 开发完成，本地自测 | 功能无阻塞 bug |
| **2** | 发版前清理（见第二节） | 无测试垃圾、无敏感文件待提交 |
| **3** | 修改版本相关文件 + 文档（见第三节） | 版本号一致，README 已针对本版更新 |
| **4** | `git commit` + `push main` | 远程 main 已更新 |
| **5** | 打标签 `v1.0.NN` 并 push | 远程可见对应 tag |
| **6** | 按固定命令打包 ZIP（见第五节） | `release/misc-api1.0.NN.zip` 生成 |
| **7** | 本地校验 ZIP（见第六节） | ZipArchive 能打开 |
| **8** | 创建 Release + **curl 上传**（见第七节） | 远程 `size` = 本地字节数 |
| **9** | 远程下载再校验 + 可选在线升级测试 | 下载包能解压 |

```
① 自测 → ② 清理 → ③ 改版本与文档 → ④ push main
    → ⑤ push tag → ⑥ 打包 → ⑦ 本地验 ZIP
    → ⑧ Release + curl 上传 → ⑨ 远程验 ZIP
```

---

## 二、发版前清理

### 2.1 原则

| 类型 | 是否打进发行 ZIP | 是否提交 Git | 说明 |
|------|------------------|--------------|------|
| 正式业务代码（admin、core、assets、install…） | **是** | **是** | 用户站点需要的文件 |
| `config/database.php` | **否** | **否** | 各站点独立，已在 `.gitignore` |
| `data/`、`upload/` | **否** | **否** | 运行时目录，服务器自动生成 |
| `release/*.zip` | **否** | **否** | 本地打包产物 |
| `.git/` | **否** | — | 打包命令已排除 |
| **临时测试目录**（如 `test/`、`tmp/`、`debug/`） | **否** | **否** | 发版前**删除** |
| **临时测试脚本**（如 `verify-zip.php`） | **否** | **否** | 验完即删，勿提交 |
| 根目录临时 ZIP | **否** | **否** | 只放在 `release/` 下 |

### 2.2 每次发版必做清理检查

```powershell
cd "D:\api.xunjinlu.fun\api系统源码\api系统源码全新框架"
git status
```

- [ ] 无 `config/database.php` 变更
- [ ] 无 `data/`、`upload/` 下文件
- [ ] 无 `release/*.zip` 被 add
- [ ] 无临时测试目录
- [ ] 无 `*_test.php`、`*verify*.php` 等待提交区
- [ ] 无根目录 `.zip` 文件

### 2.3 PHP 语法抽查

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

---

## 三、版本相关文件与文档（必改、保持一致）

### 版本号递增规则

| 类型 | 示例 | 适用 |
|------|------|------|
| **小版本** | `1.0.0` → `1.0.1` | 单点修复、UI 微调、文档、少量文件 |
| **大版本** | `1.0.x` → `1.1.0` 或 `2.0.0` | 多模块重构、大功能改版、库表大迁移 |

发版前先判断「小改 / 大改」，再选对应段位递增。

| # | 文件 | 操作 |
|---|------|------|
| 1 | `core/version.php` | `define('VS_VERSION', '1.0.NN');` |
| 2 | `update.json` | `version`、`release_date`、`title`、`changes` |
| 3 | `update-log.json` | 在 `versions` 中插入新版本对象 |
| 4 | `发行说明/misc-api1.0.NN.md` | 新建，含变更说明与下载链接 |
| 5 | **`README.md`** | **必须**：徽章版本、功能列表、目录结构、版本记录；规范见 `README编写要点.md` |
| 6 | **仓库简介** | **建议**：系统能力变动时同步优化 Gitee 仓库描述 |

**若有数据库结构变更：**

1. 新增 `install/migrations/1.0.NN.sql`
2. `update.json` 与 `update-log.json` 中该版本 `db_changes` 设为 `true`

---

## 四、推送代码

### 4.1 提交

```powershell
cd "D:\api.xunjinlu.fun\api系统源码\api系统源码全新框架"

git add core/version.php update.json update-log.json README.md LICENSE "发行说明/misc-api1.0.NN.md"
# 以及本次改动的业务文件

git commit -m "简短标题（v1.0.NN）" -m "第二段：变更摘要。"
```

### 4.2 推送 main

```powershell
git push https://xunjinlu:739a0969faa619470e04bf0ed12f59d5@gitee.com/xunjinlu/misc-api.git main
```

### 4.3 打标签并推送

```powershell
git tag v1.0.NN
git push https://xunjinlu:739a0969faa619470e04bf0ed12f59d5@gitee.com/xunjinlu/misc-api.git v1.0.NN
```

---

## 五、打包发行 ZIP（固定命令）

**输出路径固定：** `release/misc-api1.0.NN.zip`  
**临时目录固定：** 系统 `%TEMP%`

```powershell
$ver    = "1.0.NN"
$src    = "D:\api.xunjinlu.fun\api系统源码\api系统源码全新框架"
$tmp    = "$env:TEMP\misc-api$ver`_build"
$zipDir = "$src\release"
$zip    = "$zipDir\misc-api$ver.zip"

if (-not (Test-Path $zipDir)) { New-Item -ItemType Directory -Path $zipDir -Force | Out-Null }
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
New-Item -ItemType Directory -Path $tmp -Force | Out-Null

robocopy $src $tmp /E /XD .git release /XF *.zip /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null

if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path "$tmp\*" -DestinationPath $zip -Force
Remove-Item $tmp -Recurse -Force

(Get-Item $zip).Length
```

---

## 六、本地校验 ZIP（上传前必做）

在 `release/` 下**临时**建 `verify-zip.php`（**不要 commit**）：

```php
<?php
$path = __DIR__ . '/misc-api1.0.NN.zip';
$z = new ZipArchive();
$r = $z->open($path);
echo 'size=' . filesize($path) . ' open=' . var_export($r, true);
if ($r === true) {
    echo ' numFiles=' . $z->numFiles . PHP_EOL;
    $z->close();
}
```

验完删除 `verify-zip.php`。

---

## 七、创建 Release 并上传 ZIP

### 7.1 命名约定（与在线更新绑定，不可改）

| 项目 | 格式 | 示例 |
|------|------|------|
| Git 标签 | `v1.0.NN` | `v1.0.0` |
| ZIP 文件名 | `misc-api1.0.NN.zip` | `misc-api1.0.0.zip` |
| 下载 URL | `https://gitee.com/xunjinlu/misc-api/releases/download/v1.0.NN/misc-api1.0.NN.zip` | v1.0.0 示例 |

### 7.2 创建 Release

```powershell
$token = "739a0969faa619470e04bf0ed12f59d5"
$ver   = "1.0.NN"
$tag   = "v$ver"

$body = @"
## 本版摘要

- 变更点 1
- 变更点 2

详见 发行说明/misc-api$ver.md
"@

$release = Invoke-RestMethod -Method Post `
  -Uri "https://gitee.com/api/v5/repos/xunjinlu/misc-api/releases?access_token=$token" `
  -Body @{ tag_name = $tag; name = "misc-api $ver"; body = $body; target_commitish = "main" }

Write-Output "Release ID: $($release.id)"
```

### 7.3 上传 ZIP（必须用 curl）

```powershell
$zip = "D:\api.xunjinlu.fun\api系统源码\api系统源码全新框架\release\misc-api$ver.zip"

curl.exe -s -X POST `
  "https://gitee.com/api/v5/repos/xunjinlu/misc-api/releases/$($release.id)/attach_files?access_token=$token" `
  -F "file=@$zip" `
  -F "name=misc-api$ver.zip"
```

**成功标志：** 返回 JSON 里 `"size"` 数值 = 本地 `(Get-Item $zip).Length`。

### 7.4 远程下载再校验

```powershell
curl.exe -sL -o "$env:TEMP\misc-api$ver-dl.zip" `
  "https://gitee.com/xunjinlu/misc-api/releases/download/$tag/misc-api$ver.zip"

(Get-Item "$env:TEMP\misc-api$ver-dl.zip").Length
(Get-Item $zip).Length
```

---

## 八、在线更新说明（对内维护）

- 版本检测：读取云端 `update.json`
- 更新包下载：按 `core/Updater.php` 中 `misc-api{版本}.zip` 规则从云端拉取
- 保护逻辑：不覆盖 `config/database.php`、`config/install.lock`、`data/`

---

## 九、相关文件索引

| 路径 | 说明 |
|------|------|
| `Gitee推送与发行流程.md` | 本文：完整固定流程 |
| `发布检查清单.md` | 发版打勾速查 |
| `README编写要点.md` | README 结构与写法规范 |
| `README.md` | 项目说明（每版须针对性更新） |
| `LICENSE` | 开源协议 |
| `core/Updater.php` | 在线更新 |
| `update.json` / `update-log.json` | 版本清单与历史 |
| `发行说明/` | 各版本说明 |
| `release/` | 本地 ZIP 输出（gitignore） |

---

**最后更新：** 2026-07-10  
**维护要求：** 发版流程若有变更，只改本文档与检查清单，不要另起一套命令。
