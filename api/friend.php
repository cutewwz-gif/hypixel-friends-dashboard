<?php
/**
 * 好友详情 API
 * 返回单个好友的完整数据、30 天在线热力图与活跃分析
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/hypixel_helpers.php';

$username = isset($_GET['name']) ? trim($_GET['name']) : '';

if ($username === '') {
    respondError('缺少参数 name', 400);
}

try {
    $friendsList = readFriendsList();
    $isFriend = false;

    foreach ($friendsList as $friend) {
        if (strcasecmp($friend, $username) === 0) {
            $isFriend = true;
            $username = $friend;
            break;
        }
    }

    if (!$isFriend && !empty($friendsList)) {
        respondError('该玩家不在好友列表中', 403);
    }

    $friendData = getFriendData($username, true);

    if ($friendData === null) {
        respondError('无法获取玩家 "' . $username . '" 的数据，请确认玩家名称正确', 404);
    }

    $history = readHistory();
    $historyKey = getPlayerHistoryKey($username);
    $playerHistory = $history['players'][$historyKey] ?? initPlayerHistoryEntry($username);

    if ($playerHistory['uuid'] === '' && !empty($friendData['uuid'])) {
        $playerHistory['uuid'] = $friendData['uuid'];
        $playerHistory['username'] = $friendData['username'];
    }

    $heatmap = buildHeatmapData($playerHistory, HISTORY_RETENTION_DAYS);
    $analytics = computeActivityAnalytics($playerHistory, HISTORY_RETENTION_DAYS);

    respondJson([
        'success' => true,
        'cached' => $friendData['cached'] ?? false,
        'timestamp' => time(),
        'player' => [
            'username' => $friendData['username'],
            'uuid' => $friendData['uuid'],
            'skinUrl' => $friendData['skinUrl'],
            'avatarUrl' => $friendData['avatarUrl'],
            'rank' => $friendData['rank'],
            'rankColorClass' => $friendData['rankColorClass'],
            'networkLevel' => $friendData['networkLevel'],
            'bedwarsStar' => $friendData['bedwarsStar'],
            'bedwarsStarLevel' => $friendData['bedwarsStarLevel'],
            'starColorClass' => $friendData['starColorClass'],
            'fkdr' => $friendData['fkdr'],
            'online' => $friendData['online'],
            'game' => $friendData['game'],
            'lastSeen' => $friendData['lastSeen'],
            'lastOnline' => $friendData['lastOnline'],
            'lastOffline' => $friendData['lastOffline'],
            'lastSeenRelative' => formatRelativeTime($friendData['lastSeen']),
            'updatedAt' => $friendData['updatedAt'],
            'updatedAtFormatted' => formatTimestamp($friendData['updatedAt']),
        ],
        'heatmap' => $heatmap,
        'analytics' => $analytics,
    ]);
} catch (Throwable $e) {
    error_log('Friend API 异常: ' . $e->getMessage());
    respondError('服务器内部错误', 500);
}
