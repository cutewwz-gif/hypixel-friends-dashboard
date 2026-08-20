<?php
/**
 * 在线状态定时检测脚本
 * 建议每 5 分钟通过 cron 或 1Panel 计划任务执行一次
 *
 * HTTP 调用示例：
 *   curl -s "https://你的域名/api/tracker.php"
 *   curl -s "https://你的域名/api/tracker.php?key=你的密钥"
 *
 * CLI 调用示例：
 *   php api/tracker.php
 *
 * 1Panel 计划任务（每 5 分钟）：
 *   curl -s http://127.0.0.1/api/tracker.php
 */

require_once __DIR__ . '/hypixel_helpers.php';

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');

    if (TRACKER_SECRET !== '' && (!isset($_GET['key']) || $_GET['key'] !== TRACKER_SECRET)) {
        respondError('未授权访问 tracker', 403);
    }
}

function trackerLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (php_sapi_name() === 'cli') {
        echo $line . PHP_EOL;
    }
}

try {
    ensureApiKey();

    $friendsList = readFriendsList();

    if (empty($friendsList)) {
        $result = [
            'success' => true,
            'message' => '好友列表为空，跳过检测',
            'checked' => 0,
            'timestamp' => time(),
        ];

        if ($isCli) {
            trackerLog('好友列表为空');
            exit(0);
        }

        respondJson($result);
    }

    $history = readHistory();
    $now = time();
    $checked = 0;
    $onlineCount = 0;
    $errors = [];

    foreach ($friendsList as $username) {
        $historyKey = getPlayerHistoryKey($username);

        if (!isset($history['players'][$historyKey])) {
            $history['players'][$historyKey] = initPlayerHistoryEntry($username);
        }

        $playerHistory = &$history['players'][$historyKey];
        $uuid = $playerHistory['uuid'];

        if ($uuid === '') {
            $hypixelData = fetchHypixelPlayer($username, 'name');
            if ($hypixelData !== null) {
                $parsed = parseFriendPlayerData($hypixelData);
                $uuid = $parsed['uuid'];
                $playerHistory['uuid'] = $uuid;
                $playerHistory['username'] = $parsed['username'];
            } else {
                $errors[] = $username;
                unset($playerHistory);
                continue;
            }
        }

        $statusData = fetchHypixelStatus($uuid);

        if ($statusData === null) {
            $errors[] = $username;
            unset($playerHistory);
            continue;
        }

        $session = $statusData['session'] ?? null;
        $online = !empty($session['online']);

        recordOnlineCheck($playerHistory, $online, $session, $now);

        if ($online) {
            $onlineCount++;
        }

        $checked++;
        unset($playerHistory);

        usleep(200000);
    }

    $history['lastTrackerRun'] = $now;
    writeHistory($history);

    $result = [
        'success' => true,
        'message' => '检测完成',
        'checked' => $checked,
        'online' => $onlineCount,
        'errors' => $errors,
        'timestamp' => $now,
        'nextRunHint' => '建议 ' . (TRACKER_INTERVAL / 60) . ' 分钟后再次执行',
    ];

    if ($isCli) {
        trackerLog('检测完成: ' . $checked . ' 人, 在线 ' . $onlineCount . ' 人');
        if (!empty($errors)) {
            trackerLog('失败: ' . implode(', ', $errors));
        }
        exit(0);
    }

    respondJson($result);
} catch (Throwable $e) {
    error_log('Tracker 异常: ' . $e->getMessage());

    if ($isCli) {
        trackerLog('错误: ' . $e->getMessage());
        exit(1);
    }

    respondError('Tracker 执行失败: ' . $e->getMessage(), 500);
}
