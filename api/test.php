<?php
/**
 * 环境检测接口
 * 用于确认 1Panel / PHP 是否配置正确
 * 访问: http://你的域名/api/test.php
 */

header('Content-Type: application/json; charset=utf-8');

$cacheDir = __DIR__ . '/cache/';

$result = [
    'success' => true,
    'message' => 'PHP 运行正常',
    'phpVersion' => PHP_VERSION,
    'extensions' => [
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
    ],
    'cacheDir' => $cacheDir,
    'cacheWritable' => is_dir($cacheDir) && is_writable($cacheDir),
    'configExists' => file_exists(__DIR__ . '/config.php'),
    'apiKeyConfigured' => false,
    'timestamp' => time(),
];

// 检查 API Key 是否已配置（不暴露 Key 内容）
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $result['apiKeyConfigured'] = defined('HYPIXEL_API_KEY')
        && HYPIXEL_API_KEY !== ''
        && HYPIXEL_API_KEY !== 'YOUR_API_KEY_HERE';
    $result['playerName'] = defined('PLAYER_USERNAME') ? PLAYER_USERNAME : '未配置';
    $result['friendsFileExists'] = defined('FRIENDS_FILE') && file_exists(FRIENDS_FILE);
    $result['historyFileExists'] = defined('HISTORY_FILE') && file_exists(HISTORY_FILE);
    $result['historyWritable'] = defined('HISTORY_FILE') && file_exists(HISTORY_FILE) && is_writable(HISTORY_FILE);
}

// 检查 curl 扩展
if (!extension_loaded('curl')) {
    $result['success'] = false;
    $result['message'] = '缺少 curl 扩展，请在 php.ini 中启用 extension=curl';
}

// 检查缓存目录
if (!$result['cacheWritable']) {
    $result['success'] = false;
    $result['message'] = 'api/cache/ 目录不可写，请设置写入权限';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
