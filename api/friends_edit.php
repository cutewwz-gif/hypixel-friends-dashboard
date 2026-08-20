<?php
/**
 * 好友名单编辑 API（私人使用）
 * GET  - 读取当前名单（需密码）
 * POST - 保存名单（需密码）
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once __DIR__ . '/hypixel_helpers.php';

/**
 * 验证编辑密码
 */
function verifyEditPassword(?string $password): bool
{
    if (FRIENDS_EDIT_PASSWORD === '') {
        return true;
    }

    return is_string($password) && hash_equals(FRIENDS_EDIT_PASSWORD, $password);
}

/**
 * 从请求中获取密码
 */
function getRequestPassword(): ?string
{
    if (!empty($_GET['password'])) {
        return (string) $_GET['password'];
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && isset($input['password'])) {
        return (string) $input['password'];
    }

    return null;
}

/**
 * 校验并规范化好友名单
 */
function normalizeFriendsInput($friends): array
{
    if (!is_array($friends)) {
        respondError('friends 必须是数组', 400);
    }

    if (count($friends) > FRIENDS_MAX_COUNT) {
        respondError('好友数量不能超过 ' . FRIENDS_MAX_COUNT . ' 人', 400);
    }

    $result = [];
    $seen = [];

    foreach ($friends as $name) {
        if (!is_string($name)) {
            continue;
        }

        $name = trim($name);
        if ($name === '') {
            continue;
        }

        if (strlen($name) < 3 || strlen($name) > 16) {
            respondError('玩家名 "' . $name . '" 长度无效（3-16 字符）', 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            respondError('玩家名 "' . $name . '" 含非法字符（仅允许字母、数字、下划线）', 400);
        }

        $key = strtolower($name);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $result[] = $name;
    }

    return $result;
}

/**
 * 写入好友名单
 */
function saveFriendsList(array $friends): void
{
    if (!file_exists(FRIENDS_FILE)) {
        $dir = dirname(FRIENDS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    if (file_exists(FRIENDS_FILE) && !is_writable(FRIENDS_FILE)) {
        respondError('api/friends.json 不可写，请在服务器上设置写入权限', 500);
    }

    $dir = dirname(FRIENDS_FILE);
    if (!is_writable($dir)) {
        respondError('api/ 目录不可写，请设置写入权限', 500);
    }

    $json = json_encode($friends, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        respondError('JSON 编码失败', 500);
    }

    $written = file_put_contents(FRIENDS_FILE, $json . "\n", LOCK_EX);
    if ($written === false) {
        respondError('保存失败，请检查文件权限', 500);
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $password = getRequestPassword();

        if (!verifyEditPassword($password)) {
            respondJson([
                'success' => false,
                'error' => '密码错误或缺少密码',
                'needPassword' => FRIENDS_EDIT_PASSWORD !== '',
            ], 401);
        }

        respondJson([
            'success' => true,
            'friends' => readFriendsList(),
            'maxCount' => FRIENDS_MAX_COUNT,
            'needPassword' => FRIENDS_EDIT_PASSWORD !== '',
            'writable' => !file_exists(FRIENDS_FILE) || is_writable(FRIENDS_FILE),
            'timestamp' => time(),
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            respondError('请求体必须是 JSON', 400);
        }

        $password = isset($input['password']) ? (string) $input['password'] : '';

        if (!verifyEditPassword($password)) {
            respondJson([
                'success' => false,
                'error' => '密码错误',
                'needPassword' => FRIENDS_EDIT_PASSWORD !== '',
            ], 401);
        }

        if (!isset($input['friends'])) {
            respondError('缺少 friends 字段', 400);
        }

        $friends = normalizeFriendsInput($input['friends']);
        saveFriendsList($friends);

        respondJson([
            'success' => true,
            'message' => '好友名单已保存',
            'friends' => $friends,
            'count' => count($friends),
            'timestamp' => time(),
        ]);
    }

    respondError('不支持的请求方法', 405);
} catch (Throwable $e) {
    error_log('Friends Edit API 异常: ' . $e->getMessage());
    respondError('服务器内部错误', 500);
}
