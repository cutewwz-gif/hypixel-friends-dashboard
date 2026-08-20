# Hypixel Friends Dashboard

Hypixel BedWars 个人 Dashboard + 私人好友管理系统。

- 主页：玩家 BedWars 数据展示
- 好友列表：在线状态、排序、自动刷新
- 好友详情：30 天在线热力图、活跃分析
- 界面内编辑好友名单

## 技术栈

- 前端：HTML + CSS + JavaScript（原生）
- 后端：PHP
- 部署：OpenResty / Nginx + PHP（1Panel 等）

## 快速开始

### 1. 配置

```bash
cp api/config.example.php api/config.php
cp api/friends.example.json api/friends.json
cp api/history.example.json api/history.json
```

编辑 `api/config.php`，填入 Hypixel API Key 和玩家名。

### 2. 本地测试（Windows）

```bat
setup-local.bat
start.bat
```

访问 http://localhost:8080

### 3. 服务器部署

- 上传项目到网站根目录（**不要**上传 `php/` 目录）
- 确保 `api/cache/`、`api/friends.json`、`api/history.json` 可写
- 配置计划任务，每 5 分钟执行：

```bash
curl -s "https://你的域名/api/tracker.php"
```

### 4. 环境检测

访问 `/api/test.php` 检查 PHP 与 curl 是否正常。

## 安全说明

- `api/config.php` 已在 `.gitignore` 中，请勿提交 API Key
- 建议在 `config.php` 中设置 `TRACKER_SECRET` 和 `FRIENDS_EDIT_PASSWORD`
- 若 API Key 曾泄露，请在 Hypixel 游戏内执行 `/api new` 重新生成
