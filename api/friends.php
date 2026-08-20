<?php
/**
 * 好友列表 API
 * 读取 friends.json，查询每位好友的 Hypixel 数据与在线状态，按规则排序返回
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/hypixel_helpers.php';

try {
    $friendsList = readFriendsList();

    if (empty($friendsList)) {
        respondJson([
            'success' => true,
            'cached' => false,
            'timestamp' => time(),
            'count' => 0,
            'friends' => [],
            'message' => '好友列表为空，请在 api/friends.json 中添加玩家名称',
        ]);
    }

    $friends = [];
    $errors = [];

    foreach ($friendsList as $username) {
        $data = getFriendData($username, true);

        if ($data === null) {
            $errors[] = $username;
            continue;
        }

        $friends[] = [
            'username' => $data['username'],
            'uuid' => $data['uuid'],
            'skinUrl' => $data['skinUrl'],
            'avatarUrl' => $data['avatarUrl'],
            'rank' => $data['rank'],
            'rankColorClass' => $data['rankColorClass'],
            'networkLevel' => $data['networkLevel'],
            'bedwarsStar' => $data['bedwarsStar'],
            'starColorClass' => $data['starColorClass'],
            'fkdr' => $data['fkdr'],
            'online' => $data['online'],
            'game' => $data['game'],
            'lastSeen' => $data['lastSeen'],
            'lastOnline' => $data['lastOnline'],
            'lastOffline' => $data['lastOffline'],
            'lastSeenRelative' => formatRelativeTime($data['lastSeen']),
            'updatedAt' => $data['updatedAt'],
            'updatedAtFormatted' => formatTimestamp($data['updatedAt']),
            'cached' => $data['cached'] ?? false,
        ];
    }

    $friends = sortFriendsList($friends);

    respondJson([
        'success' => true,
        'cached' => false,
        'timestamp' => time(),
        'count' => count($friends),
        'friends' => $friends,
        'errors' => $errors,
        'trackerNote' => '在线状态由 tracker.php 定时检测记录，列表页每 60 秒自动刷新',
    ]);
} catch (Throwable $e) {
    error_log('Friends API 异常: ' . $e->getMessage());
    respondError('服务器内部错误', 500);
}
