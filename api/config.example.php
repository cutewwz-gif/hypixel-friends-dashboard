<?php
/**
 * Hypixel Dashboard 配置模板
 * 复制此文件为 config.php 并填入你的真实配置
 *
 *   cp api/config.example.php api/config.php
 */

// Hypixel API Key（在 Hypixel 游戏内执行 /api new 获取）
define('HYPIXEL_API_KEY', 'YOUR_API_KEY_HERE');

// 要展示的玩家名称（主页 Dashboard）
define('PLAYER_USERNAME', 'YourUsername');

// 缓存有效期（秒）
define('CACHE_TTL', 60);

// 缓存目录
define('CACHE_DIR', __DIR__ . '/cache/');

// Hypixel API 基础地址
define('HYPIXEL_API_URL', 'https://api.hypixel.net/v2/player');
define('HYPIXEL_STATUS_URL', 'https://api.hypixel.net/v2/status');

// Mojang UUID 查询地址
define('MOJANG_API_URL', 'https://api.mojang.com/users/profiles/minecraft/');

// 好友模块配置
define('FRIENDS_FILE', __DIR__ . '/friends.json');
define('HISTORY_FILE', __DIR__ . '/history.json');

// 在线状态检测间隔（秒），tracker.php 建议每 5 分钟执行
define('TRACKER_INTERVAL', 300);

// 历史记录保留天数
define('HISTORY_RETENTION_DAYS', 30);

// tracker.php HTTP 访问密钥（留空则允许无密钥访问，建议部署后设置）
define('TRACKER_SECRET', '');

// 好友名单编辑密码（留空则无需密码，建议部署后设置）
define('FRIENDS_EDIT_PASSWORD', '');

// 好友数量上限
define('FRIENDS_MAX_COUNT', 50);
