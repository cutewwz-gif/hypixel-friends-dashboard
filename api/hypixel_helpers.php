<?php
/**
 * Hypixel 好友模块共享工具库
 * 供 friends.php / friend.php / tracker.php 复用
 */

require_once __DIR__ . '/config.php';

// ==================== BedWars 经验等级计算 ====================

const BW_EASY_LEVELS = 4;
const BW_EASY_LEVELS_XP = 7000;
const BW_XP_PER_PRESTIGE = 96 * 5000 + BW_EASY_LEVELS_XP;
const BW_LEVELS_PER_PRESTIGE = 100;
const BW_HIGHEST_PRESTIGE = 10;

function bwGetExpForLevel(int $level): int
{
    if ($level === 0) {
        return 0;
    }

    $respectedLevel = bwGetLevelRespectingPrestige($level);

    if ($respectedLevel > BW_EASY_LEVELS) {
        return 5000;
    }

    switch ($respectedLevel) {
        case 1: return 500;
        case 2: return 1000;
        case 3: return 2000;
        case 4: return 3500;
        default: return 5000;
    }
}

function bwGetLevelRespectingPrestige(int $level): int
{
    if ($level > BW_HIGHEST_PRESTIGE * BW_LEVELS_PER_PRESTIGE) {
        return $level - BW_HIGHEST_PRESTIGE * BW_LEVELS_PER_PRESTIGE;
    }

    return $level % BW_LEVELS_PER_PRESTIGE;
}

function bwGetLevelForExp(int $exp): float
{
    $prestiges = (int) floor($exp / BW_XP_PER_PRESTIGE);
    $level = $prestiges * BW_LEVELS_PER_PRESTIGE;
    $expWithoutPrestiges = $exp - ($prestiges * BW_XP_PER_PRESTIGE);

    for ($i = 1; $i <= BW_EASY_LEVELS; $i++) {
        $expForEasyLevel = bwGetExpForLevel($i);
        if ($expWithoutPrestiges < $expForEasyLevel) {
            return $level + ($expForEasyLevel > 0 ? $expWithoutPrestiges / $expForEasyLevel : 0);
        }
        $level++;
        $expWithoutPrestiges -= $expForEasyLevel;
    }

    return $level + ($expWithoutPrestiges / 5000);
}

function bwGetTotalExpForLevel(int $targetLevel): int
{
    $totalExp = 0;
    for ($lvl = 1; $lvl <= $targetLevel; $lvl++) {
        $totalExp += bwGetExpForLevel($lvl);
    }
    return $totalExp;
}

function bwGetStarProgress(int $exp): array
{
    $currentLevel = bwGetLevelForExp($exp);
    $currentStar = (int) floor($currentLevel);
    $nextStar = $currentStar + 1;
    $expAtCurrentStar = bwGetTotalExpForLevel($currentStar);
    $expAtNextStar = bwGetTotalExpForLevel($nextStar);
    $expInCurrentLevel = $exp - $expAtCurrentStar;
    $expNeededForNext = max(1, $expAtNextStar - $expAtCurrentStar);
    $progressPercent = min(100, max(0, ($expInCurrentLevel / $expNeededForNext) * 100));

    return [
        'level' => round($currentLevel, 2),
        'currentStar' => $currentStar,
        'nextStar' => $nextStar,
        'currentExp' => $expInCurrentLevel,
        'requiredExp' => $expNeededForNext,
        'totalExp' => $exp,
        'progressPercent' => round($progressPercent, 2),
    ];
}

// ==================== 网络等级与 Rank ====================

function getNetworkLevel(int $networkExp): float
{
    if ($networkExp < 0) {
        return 1.0;
    }
    return floor(1 + (-3.5) + sqrt(12.25 + 0.0008 * $networkExp));
}

function statInt(array $stats, string $key): int
{
    return isset($stats[$key]) ? (int) $stats[$key] : 0;
}

function calcRatio(int $numerator, int $denominator): float
{
    if ($denominator <= 0) {
        return $numerator > 0 ? (float) $numerator : 0.0;
    }
    return round($numerator / $denominator, 2);
}

function formatPackageRank(string $rank): string
{
    $map = [
        'VIP' => 'VIP',
        'VIP_PLUS' => 'VIP+',
        'MVP' => 'MVP',
        'MVP_PLUS' => 'MVP+',
        'NONE' => 'Default',
    ];
    return $map[$rank] ?? str_replace('_', ' ', $rank);
}

function formatPlayerRank(array $player): string
{
    if (!empty($player['rank']) && $player['rank'] !== 'NORMAL') {
        return str_replace('_', ' ', $player['rank']);
    }

    if (!empty($player['monthlyPackageRank']) && $player['monthlyPackageRank'] === 'SUPERSTAR') {
        $baseRank = !empty($player['newPackageRank'])
            ? $player['newPackageRank']
            : ($player['packageRank'] ?? 'NONE');
        return formatPackageRank($baseRank) . '++';
    }

    if (!empty($player['newPackageRank'])) {
        return formatPackageRank($player['newPackageRank']);
    }

    if (!empty($player['packageRank'])) {
        return formatPackageRank($player['packageRank']);
    }

    return 'Default';
}

function getRankColorClass(array $player): string
{
    if (!empty($player['rank']) && $player['rank'] !== 'NORMAL') {
        $special = strtolower($player['rank']);
        if (strpos($special, 'admin') !== false) return 'rank-admin';
        if (strpos($special, 'helper') !== false) return 'rank-helper';
        if (strpos($special, 'youtube') !== false) return 'rank-youtube';
        return 'rank-special';
    }

    if (!empty($player['monthlyPackageRank']) && $player['monthlyPackageRank'] === 'SUPERSTAR') {
        return 'rank-mvpplusplus';
    }

    $rank = $player['newPackageRank'] ?? ($player['packageRank'] ?? 'NONE');

    switch ($rank) {
        case 'MVP_PLUS': return 'rank-mvpplus';
        case 'MVP': return 'rank-mvp';
        case 'VIP_PLUS': return 'rank-vipplus';
        case 'VIP': return 'rank-vip';
        default: return 'rank-default';
    }
}

function getBedwarsStarColorClass(int $star): string
{
    if ($star >= 5000) return 'star-black';
    if ($star >= 4000) return 'star-dark-red';
    if ($star >= 3000) return 'star-red';
    if ($star >= 2000) return 'star-gold';
    if ($star >= 1000) return 'star-white';
    if ($star >= 500) return 'star-emerald';
    if ($star >= 400) return 'star-diamond';
    if ($star >= 300) return 'star-sapphire';
    if ($star >= 200) return 'star-ruby';
    if ($star >= 100) return 'star-gold-prestige';
    if ($star >= 10) return 'star-green';
    if ($star >= 5) return 'star-blue';
    return 'star-gray';
}

// ==================== JSON 响应 ====================

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function respondError(string $message, int $statusCode = 500): void
{
    respondJson([
        'success' => false,
        'error' => $message,
        'cached' => false,
        'timestamp' => time(),
    ], $statusCode);
}

// ==================== HTTP 请求 ====================

function httpGet(string $url, array $headers = [], int $timeout = 12): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'body' => null,
            'httpCode' => 0,
            'error' => 'PHP 未安装 curl 扩展',
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'HypixelFriendsDashboard/1.0',
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'body' => null, 'httpCode' => $httpCode, 'error' => $error ?: '网络连接失败'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'body' => $response, 'httpCode' => $httpCode, 'error' => 'HTTP ' . $httpCode];
    }

    return ['ok' => true, 'body' => $response, 'httpCode' => $httpCode, 'error' => ''];
}

function ensureApiKey(): void
{
    if (HYPIXEL_API_KEY === 'YOUR_API_KEY_HERE' || HYPIXEL_API_KEY === '') {
        respondError('请先在 api/config.php 中配置 Hypixel API Key', 500);
    }
}

// ==================== Hypixel API ====================

function extractUuidFromPlayer(array $player): string
{
    $uuid = $player['uuid'] ?? '';
    if (strlen($uuid) === 32) {
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($uuid, 0, 8),
            substr($uuid, 8, 4),
            substr($uuid, 12, 4),
            substr($uuid, 16, 4),
            substr($uuid, 20, 12)
        );
    }
    return $uuid;
}

function normalizeUuid(string $uuid): string
{
    return str_replace('-', '', strtolower($uuid));
}

/**
 * 从 Hypixel API 获取玩家数据
 */
function fetchHypixelPlayer(string $identifier, string $type = 'name'): ?array
{
    ensureApiKey();

    $param = $type === 'uuid' ? 'uuid' : 'name';
    $url = HYPIXEL_API_URL
        . '?' . $param . '=' . urlencode($identifier)
        . '&key=' . urlencode(HYPIXEL_API_KEY);

    $result = httpGet($url);

    if (!$result['ok']) {
        if ($result['httpCode'] === 429) {
            return null;
        }
        return null;
    }

    $data = json_decode($result['body'], true);
    if (!is_array($data) || empty($data['success']) || empty($data['player'])) {
        return null;
    }

    return $data;
}

/**
 * 从 Hypixel Status API 获取在线状态
 * 注意：Hypixel 不提供历史在线数据，在线状态只能通过定时检测记录
 */
function fetchHypixelStatus(string $uuid): ?array
{
    ensureApiKey();

    $uuidClean = normalizeUuid($uuid);
    $url = HYPIXEL_STATUS_URL
        . '?uuid=' . urlencode($uuidClean)
        . '&key=' . urlencode(HYPIXEL_API_KEY);

    $result = httpGet($url, [], 8);

    if (!$result['ok']) {
        return null;
    }

    $data = json_decode($result['body'], true);
    if (!is_array($data) || empty($data['success'])) {
        return null;
    }

    return $data;
}

/**
 * 格式化游戏模式显示名称
 */
function formatGameMode(?array $session): string
{
    if ($session === null || empty($session['online'])) {
        return '';
    }

    $gameType = $session['gameType'] ?? '';
    $mode = $session['mode'] ?? '';
    $map = $session['map'] ?? '';

    $gameNames = [
        'BEDWARS' => 'Bed Wars',
        'SKYBLOCK' => 'SkyBlock',
        'ARCADE' => 'Arcade',
        'DUELS' => 'Duels',
        'WALLS3' => 'Mega Walls',
        'WALLS' => 'Walls',
        'PAINTBALL' => 'Paintball',
        'QUAKE' => 'Quake',
        'VAMPIREZ' => 'VampireZ',
        'WALLS' => 'Walls',
        'TNTGAMES' => 'TNT Games',
        'ARENA' => 'Arena',
        'UHC' => 'UHC',
        'MCGO' => 'Cops and Crims',
        'BATTLEGROUND' => 'Blitz SG',
        'SUPERCRAFT' => 'Super Craft',
        'SPEEDUHC' => 'Speed UHC',
        'BUILD_BATTLE' => 'Build Battle',
        'HOUSING' => 'Housing',
        'SKYWARS' => 'SkyWars',
        'MURDER_MYSTERY' => 'Murder Mystery',
        'LEGACY' => 'Classic',
        'PIT' => 'The Pit',
        'REPLAY' => 'Replay',
        'SMP' => 'SMP',
        'PROTOTYPE' => 'Prototype',
        'BEDWARS' => 'Bed Wars',
    ];

    $gameLabel = $gameNames[$gameType] ?? ($gameType ?: 'Hypixel');

    if ($mode !== '') {
        $gameLabel .= ' · ' . str_replace('_', ' ', $mode);
    }

    if ($map !== '') {
        $gameLabel .= ' (' . $map . ')';
    }

    return $gameLabel;
}

// ==================== 缓存 ====================

function readCache(string $cacheKey): ?array
{
    $cacheFile = CACHE_DIR . $cacheKey . '.json';
    if (!file_exists($cacheFile)) {
        return null;
    }

    $content = file_get_contents($cacheFile);
    if ($content === false) {
        return null;
    }

    $cached = json_decode($content, true);
    if (!is_array($cached) || empty($cached['timestamp'])) {
        return null;
    }

    if (time() - $cached['timestamp'] > CACHE_TTL) {
        return null;
    }

    return $cached;
}

function writeCache(string $cacheKey, array $data): void
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }
    $cacheFile = CACHE_DIR . $cacheKey . '.json';
    file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// ==================== 好友列表与历史记录 ====================

function readFriendsList(): array
{
    if (!file_exists(FRIENDS_FILE)) {
        return [];
    }

    $content = file_get_contents(FRIENDS_FILE);
    if ($content === false) {
        return [];
    }

    $list = json_decode($content, true);
    if (!is_array($list)) {
        return [];
    }

    $friends = [];
    foreach ($list as $item) {
        if (is_string($item) && trim($item) !== '') {
            $friends[] = trim($item);
        }
    }

    return array_values(array_unique($friends));
}

function readHistory(): array
{
    if (!file_exists(HISTORY_FILE)) {
        return ['version' => 1, 'lastTrackerRun' => null, 'players' => []];
    }

    $content = file_get_contents(HISTORY_FILE);
    if ($content === false) {
        return ['version' => 1, 'lastTrackerRun' => null, 'players' => []];
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return ['version' => 1, 'lastTrackerRun' => null, 'players' => []];
    }

    if (!isset($data['players']) || !is_array($data['players'])) {
        $data['players'] = [];
    }

    return $data;
}

function writeHistory(array $history): void
{
    $history['version'] = 1;
    file_put_contents(
        HISTORY_FILE,
        json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function getPlayerHistoryKey(string $username): string
{
    return strtolower($username);
}

function initPlayerHistoryEntry(string $username): array
{
    return [
        'username' => $username,
        'uuid' => '',
        'status' => [
            'online' => false,
            'game' => '',
            'lastSeen' => null,
            'lastOnline' => null,
            'lastOffline' => null,
        ],
        'hourly' => [],
    ];
}

/**
 * 修剪超过保留期的历史数据
 */
function pruneHistory(array &$playerHistory): void
{
    $cutoff = strtotime('-' . HISTORY_RETENTION_DAYS . ' days');
    $cutoffDate = date('Y-m-d', $cutoff);

    if (!isset($playerHistory['hourly']) || !is_array($playerHistory['hourly'])) {
        $playerHistory['hourly'] = [];
        return;
    }

    foreach (array_keys($playerHistory['hourly']) as $date) {
        if ($date < $cutoffDate) {
            unset($playerHistory['hourly'][$date]);
        }
    }
}

/**
 * 记录一次在线检测
 */
function recordOnlineCheck(array &$playerHistory, bool $online, ?array $session, int $timestamp): void
{
    $date = date('Y-m-d', $timestamp);
    $hour = (int) date('G', $timestamp);

    if (!isset($playerHistory['hourly'][$date])) {
        $playerHistory['hourly'][$date] = array_fill(0, 24, 0);
    }

    if ($online) {
        $playerHistory['hourly'][$date][$hour]++;
    }

    $wasOnline = !empty($playerHistory['status']['online']);

    $playerHistory['status']['online'] = $online;
    $playerHistory['status']['game'] = $online ? formatGameMode($session) : '';
    $playerHistory['status']['lastSeen'] = $timestamp;

    if ($online && !$wasOnline) {
        $playerHistory['status']['lastOnline'] = $timestamp;
    }

    if (!$online && $wasOnline) {
        $playerHistory['status']['lastOffline'] = $timestamp;
    }

    pruneHistory($playerHistory);
}

// ==================== 玩家数据解析 ====================

function parseFriendPlayerData(array $hypixelData): array
{
    $player = $hypixelData['player'];
    $uuid = extractUuidFromPlayer($player);
    $uuidClean = normalizeUuid($uuid);

    $bw = $player['stats']['Bedwars'] ?? [];
    $achievements = $player['achievements'] ?? [];

    $networkExp = statInt($player, 'networkExp');
    if ($networkExp === 0 && isset($player['networkLevel'])) {
        $networkExp = (int) ($player['networkLevel'] * 10000);
    }

    $bedwarsExp = statInt($bw, 'Experience');
    $starProgress = bwGetStarProgress($bedwarsExp);
    $currentStarInt = $starProgress['currentStar'];

    $finalKills = statInt($bw, 'final_kills_bedwars');
    $finalDeaths = statInt($bw, 'final_deaths_bedwars');

    return [
        'username' => $player['displayname'] ?? '',
        'uuid' => $uuid,
        'uuidClean' => $uuidClean,
        'skinUrl' => 'https://mc-heads.net/body/' . $uuidClean . '/128',
        'avatarUrl' => 'https://mc-heads.net/avatar/' . $uuidClean . '/64',
        'rank' => formatPlayerRank($player),
        'rankColorClass' => getRankColorClass($player),
        'networkLevel' => round(getNetworkLevel($networkExp), 2),
        'achievementPoints' => statInt($player, 'achievementPoints') ?: statInt($achievements, 'achievementpoints'),
        'bedwarsStar' => $currentStarInt,
        'bedwarsStarLevel' => $starProgress['level'],
        'starColorClass' => getBedwarsStarColorClass($currentStarInt),
        'fkdr' => calcRatio($finalKills, $finalDeaths),
    ];
}

/**
 * 获取单个好友的完整数据（含缓存）
 */
function getFriendData(string $username, bool $refreshStatus = true): ?array
{
    $cacheKey = 'friend_' . strtolower($username);
    $cached = readCache($cacheKey);

    $history = readHistory();
    $historyKey = getPlayerHistoryKey($username);
    $playerHistory = $history['players'][$historyKey] ?? initPlayerHistoryEntry($username);

    if ($cached !== null) {
        $result = $cached;
        $result['cached'] = true;
        $result['cacheAge'] = time() - ($cached['timestamp'] ?? time());
    } else {
        $hypixelData = fetchHypixelPlayer($username, 'name');
        if ($hypixelData === null) {
            return null;
        }

        $parsed = parseFriendPlayerData($hypixelData);
        $result = array_merge($parsed, [
            'success' => true,
            'cached' => false,
            'timestamp' => time(),
        ]);

        writeCache($cacheKey, $result);

        if ($playerHistory['uuid'] === '') {
            $playerHistory['uuid'] = $parsed['uuid'];
            $playerHistory['username'] = $parsed['username'];
            $history['players'][$historyKey] = $playerHistory;
            writeHistory($history);
        }
    }

    $status = $playerHistory['status'] ?? [];

    if ($refreshStatus && !empty($result['uuid'])) {
        $statusAge = isset($status['lastSeen']) ? (time() - (int) $status['lastSeen']) : PHP_INT_MAX;
        if ($statusAge > CACHE_TTL) {
            $statusData = fetchHypixelStatus($result['uuid']);
            if ($statusData !== null) {
                $session = $statusData['session'] ?? null;
                $online = !empty($session['online']);
                recordOnlineCheck($playerHistory, $online, $session, time());
                $playerHistory['uuid'] = $result['uuid'];
                $playerHistory['username'] = $result['username'];
                $history['players'][$historyKey] = $playerHistory;
                writeHistory($history);
                $status = $playerHistory['status'];
            }
        }
    }

    $result['online'] = !empty($status['online']);
    $result['game'] = $status['game'] ?? '';
    $result['lastSeen'] = $status['lastSeen'] ?? null;
    $result['lastOnline'] = $status['lastOnline'] ?? null;
    $result['lastOffline'] = $status['lastOffline'] ?? null;
    $result['updatedAt'] = time();

    return $result;
}

/**
 * 好友列表排序：在线 > 刚离线 > 离线较久
 */
function sortFriendsList(array $friends): array
{
    usort($friends, function (array $a, array $b): int {
        $aOnline = !empty($a['online']);
        $bOnline = !empty($b['online']);

        if ($aOnline !== $bOnline) {
            return $bOnline <=> $aOnline;
        }

        $aLastSeen = (int) ($a['lastSeen'] ?? 0);
        $bLastSeen = (int) ($b['lastSeen'] ?? 0);

        return $bLastSeen <=> $aLastSeen;
    });

    return $friends;
}

// ==================== 热力图与活跃分析 ====================

function getDateRange(int $days): array
{
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime('-' . $i . ' days'));
    }
    return $dates;
}

function buildHeatmapData(array $playerHistory, int $days = 30): array
{
    $dates = getDateRange($days);
    $hourly = $playerHistory['hourly'] ?? [];
    $grid = [];
    $maxCount = 0;

    foreach ($dates as $date) {
        $row = [];
        $dayData = $hourly[$date] ?? array_fill(0, 24, 0);

        for ($h = 0; $h < 24; $h++) {
            $count = (int) ($dayData[$h] ?? 0);
            if ($count > $maxCount) {
                $maxCount = $count;
            }
            $row[] = [
                'hour' => $h,
                'count' => $count,
            ];
        }

        $grid[] = [
            'date' => $date,
            'label' => date('n/j', strtotime($date)),
            'hours' => $row,
        ];
    }

    return [
        'days' => $days,
        'maxCount' => $maxCount,
        'grid' => $grid,
    ];
}

function computeActivityAnalytics(array $playerHistory, int $days = 30): array
{
    $dates = getDateRange($days);
    $hourly = $playerHistory['hourly'] ?? [];
    $status = $playerHistory['status'] ?? [];

    $hourTotals = array_fill(0, 24, 0);
    $weekdayTotals = array_fill(0, 7, 0);
    $totalOnlineChecks = 0;
    $activeDays = 0;

    foreach ($dates as $date) {
        $dayData = $hourly[$date] ?? array_fill(0, 24, 0);
        $daySum = 0;

        for ($h = 0; $h < 24; $h++) {
            $count = (int) ($dayData[$h] ?? 0);
            $hourTotals[$h] += $count;
            $daySum += $count;
        }

        if ($daySum > 0) {
            $activeDays++;
            $weekday = (int) date('w', strtotime($date));
            $weekdayTotals[$weekday] += $daySum;
        }

        $totalOnlineChecks += $daySum;
    }

    $peakStart = 0;
    $peakSum = 0;
    $windowSize = 3;

    for ($h = 0; $h <= 24 - $windowSize; $h++) {
        $sum = 0;
        for ($w = 0; $w < $windowSize; $w++) {
            $sum += $hourTotals[$h + $w];
        }
        if ($sum > $peakSum) {
            $peakSum = $sum;
            $peakStart = $h;
        }
    }

    $peakEnd = min(23, $peakStart + $windowSize - 1);
    $mostActiveTime = sprintf('%02d:00~%02d:00', $peakStart, $peakEnd);

    if ($peakSum === 0) {
        $mostActiveTime = '暂无数据';
    }

    $weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $weekdayNamesZh = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];

    $maxWeekday = 0;
    $maxWeekdayCount = 0;
    foreach ($weekdayTotals as $wd => $count) {
        if ($count > $maxWeekdayCount) {
            $maxWeekdayCount = $count;
            $maxWeekday = $wd;
        }
    }

    $mostActiveWeekday = $maxWeekdayCount > 0
        ? $weekdayNames[$maxWeekday] . '（' . $weekdayNamesZh[$maxWeekday] . '）'
        : '暂无数据';

    $estimatedMinutes = $totalOnlineChecks * (TRACKER_INTERVAL / 60);
    $avgDailyMinutes = $days > 0 ? round($estimatedMinutes / $days, 1) : 0;

    $avgHours = floor($avgDailyMinutes / 60);
    $avgMins = round($avgDailyMinutes % 60);
    $avgDailyOnline = $totalOnlineChecks > 0
        ? ($avgHours > 0 ? $avgHours . ' 小时 ' : '') . $avgMins . ' 分钟'
        : '暂无数据';

    return [
        'mostActiveTime' => $mostActiveTime,
        'mostActiveWeekday' => $mostActiveWeekday,
        'avgDailyOnline' => $avgDailyOnline,
        'avgDailyMinutes' => $avgDailyMinutes,
        'activeDays' => $activeDays,
        'totalChecks' => $totalOnlineChecks,
        'lastOnline' => $status['lastOnline'] ?? null,
        'lastOffline' => $status['lastOffline'] ?? null,
        'lastOnlineFormatted' => formatTimestamp($status['lastOnline'] ?? null),
        'lastOfflineFormatted' => formatTimestamp($status['lastOffline'] ?? null),
        'trackerNote' => '在线数据由服务器每 ' . (TRACKER_INTERVAL / 60) . ' 分钟检测记录，非 Hypixel API 直接提供',
    ];
}

function formatTimestamp(?int $timestamp): string
{
    if ($timestamp === null || $timestamp <= 0) {
        return '暂无记录';
    }
    return date('Y-m-d H:i:s', $timestamp);
}

function formatRelativeTime(?int $timestamp): string
{
    if ($timestamp === null || $timestamp <= 0) {
        return '未知';
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' 分钟前';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' 小时前';
    }
    return floor($diff / 86400) . ' 天前';
}
