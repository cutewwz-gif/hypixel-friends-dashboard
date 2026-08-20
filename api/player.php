<?php
/**
 * Hypixel 玩家数据 API
 * 负责获取、解析、缓存并返回整理后的玩家 BedWars 数据
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/config.php';

// ==================== BedWars 经验等级计算（Plancke 算法） ====================

const BW_EASY_LEVELS = 4;
const BW_EASY_LEVELS_XP = 7000;
const BW_XP_PER_PRESTIGE = 96 * 5000 + BW_EASY_LEVELS_XP; // 487000
const BW_LEVELS_PER_PRESTIGE = 100;
const BW_HIGHEST_PRESTIGE = 10;

/**
 * 获取指定等级升级所需经验
 */
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
        case 1:
            return 500;
        case 2:
            return 1000;
        case 3:
            return 2000;
        case 4:
            return 3500;
        default:
            return 5000;
    }
}

/**
 * 获取当前 prestige 周期内的相对等级
 */
function bwGetLevelRespectingPrestige(int $level): int
{
    if ($level > BW_HIGHEST_PRESTIGE * BW_LEVELS_PER_PRESTIGE) {
        return $level - BW_HIGHEST_PRESTIGE * BW_LEVELS_PER_PRESTIGE;
    }

    return $level % BW_LEVELS_PER_PRESTIGE;
}

/**
 * 根据经验计算 BedWars 星级（含小数）
 */
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

/**
 * 计算到达指定等级所需的累计经验
 */
function bwGetTotalExpForLevel(int $targetLevel): int
{
    $totalExp = 0;

    for ($lvl = 1; $lvl <= $targetLevel; $lvl++) {
        $totalExp += bwGetExpForLevel($lvl);
    }

    return $totalExp;
}

/**
 * 获取当前星级进度信息
 */
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

// ==================== 网络等级计算 ====================

/**
 * 根据 networkExp 计算 Hypixel 网络等级
 */
function getNetworkLevel(int $networkExp): float
{
    if ($networkExp < 0) {
        return 1.0;
    }

    return floor(1 + (-3.5) + sqrt(12.25 + 0.0008 * $networkExp));
}

// ==================== 工具函数 ====================

/**
 * 输出 JSON 并退出
 */
function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * 输出错误 JSON
 */
function respondError(string $message, int $statusCode = 500): void
{
    respondJson([
        'success' => false,
        'error' => $message,
        'cached' => false,
        'timestamp' => time(),
    ], $statusCode);
}

/**
 * 安全读取整数统计
 */
function statInt(array $stats, string $key): int
{
    return isset($stats[$key]) ? (int) $stats[$key] : 0;
}

/**
 * 计算比率（保留两位小数）
 */
function calcRatio(int $numerator, int $denominator): float
{
    if ($denominator <= 0) {
        return $numerator > 0 ? (float) $numerator : 0.0;
    }

    return round($numerator / $denominator, 2);
}

/**
 * 计算胜率百分比
 */
function calcWinRate(int $wins, int $losses): float
{
    $total = $wins + $losses;
    if ($total <= 0) {
        return 0.0;
    }

    return round(($wins / $total) * 100, 2);
}

/**
 * 格式化 Hypixel 玩家 Rank 显示
 */
function formatPlayerRank(array $player): string
{
    // 特殊职级（管理员、YouTuber 等）
    if (!empty($player['rank']) && $player['rank'] !== 'NORMAL') {
        return str_replace('_', ' ', $player['rank']);
    }

    // MVP++ (Superstar)
    if (!empty($player['monthlyPackageRank']) && $player['monthlyPackageRank'] === 'SUPERSTAR') {
        $baseRank = !empty($player['newPackageRank'])
            ? $player['newPackageRank']
            : ($player['packageRank'] ?? 'NONE');

        return formatPackageRank($baseRank) . '++';
    }

    // 普通会员等级
    if (!empty($player['newPackageRank'])) {
        return formatPackageRank($player['newPackageRank']);
    }

    if (!empty($player['packageRank'])) {
        return formatPackageRank($player['packageRank']);
    }

    return 'Default';
}

/**
 * 格式化会员等级名称
 */
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

/**
 * 获取 Rank 对应 CSS 颜色类名
 */
function getRankColorClass(array $player): string
{
    if (!empty($player['rank']) && $player['rank'] !== 'NORMAL') {
        $special = strtolower($player['rank']);
        if (strpos($special, 'admin') !== false) {
            return 'rank-admin';
        }
        if (strpos($special, 'helper') !== false) {
            return 'rank-helper';
        }
        if (strpos($special, 'youtube') !== false) {
            return 'rank-youtube';
        }
        return 'rank-special';
    }

    if (!empty($player['monthlyPackageRank']) && $player['monthlyPackageRank'] === 'SUPERSTAR') {
        return 'rank-mvpplusplus';
    }

    $rank = $player['newPackageRank'] ?? ($player['packageRank'] ?? 'NONE');

    switch ($rank) {
        case 'MVP_PLUS':
            return 'rank-mvpplus';
        case 'MVP':
            return 'rank-mvp';
        case 'VIP_PLUS':
            return 'rank-vipplus';
        case 'VIP':
            return 'rank-vip';
        default:
            return 'rank-default';
    }
}

/**
 * 根据 BedWars 星级获取颜色类名
 */
function getBedwarsStarColorClass(int $star): string
{
    if ($star >= 5000) {
        return 'star-black';
    }
    if ($star >= 4000) {
        return 'star-dark-red';
    }
    if ($star >= 3000) {
        return 'star-red';
    }
    if ($star >= 2000) {
        return 'star-gold';
    }
    if ($star >= 1000) {
        return 'star-white';
    }
    if ($star >= 500) {
        return 'star-emerald';
    }
    if ($star >= 400) {
        return 'star-diamond';
    }
    if ($star >= 300) {
        return 'star-sapphire';
    }
    if ($star >= 200) {
        return 'star-ruby';
    }
    if ($star >= 100) {
        return 'star-gold-prestige';
    }
    if ($star >= 10) {
        return 'star-green';
    }
    if ($star >= 5) {
        return 'star-blue';
    }

    return 'star-gray';
}

/**
 * HTTP GET 请求
 * @return array{ok: bool, body: ?string, httpCode: int, error: string}
 */
function httpGet(string $url, array $headers = [], int $timeout = 12): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'body' => null,
            'httpCode' => 0,
            'error' => 'PHP 未安装 curl 扩展，请在 php.ini 中启用 extension=curl',
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
        CURLOPT_USERAGENT => 'HypixelBedWarsDashboard/1.0',
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('HTTP 请求失败: ' . $url . ' | Error: ' . $error);
        return [
            'ok' => false,
            'body' => null,
            'httpCode' => $httpCode,
            'error' => $error ?: '网络连接失败',
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('HTTP 请求失败: ' . $url . ' | Code: ' . $httpCode);
        return [
            'ok' => false,
            'body' => $response,
            'httpCode' => $httpCode,
            'error' => 'HTTP ' . $httpCode,
        ];
    }

    return [
        'ok' => true,
        'body' => $response,
        'httpCode' => $httpCode,
        'error' => '',
    ];
}

/**
 * 从 Hypixel API 获取玩家数据（优先使用玩家名，无需 Mojang API）
 */
function fetchHypixelPlayer(string $identifier, string $type = 'name'): ?array
{
    if (HYPIXEL_API_KEY === 'YOUR_API_KEY_HERE' || HYPIXEL_API_KEY === '') {
        respondError('请先在 api/config.php 中配置 Hypixel API Key', 500);
    }

    $param = $type === 'uuid' ? 'uuid' : 'name';
    $url = HYPIXEL_API_URL
        . '?' . $param . '=' . urlencode($identifier)
        . '&key=' . urlencode(HYPIXEL_API_KEY);

    $result = httpGet($url);

    if (!$result['ok']) {
        $errorMsg = $result['error'];

        if ($result['httpCode'] === 429) {
            respondError(
                'Hypixel API 请求频率超限，请等待 1-2 分钟后重试',
                429
            );
        }

        respondError(
            'Hypixel API 请求失败：' . $errorMsg . '。请检查服务器是否能访问 api.hypixel.net',
            502
        );
    }

    $data = json_decode($result['body'], true);

    if (!is_array($data)) {
        respondError('Hypixel API 返回了无效数据', 502);
    }

    if (empty($data['success'])) {
        $cause = $data['cause'] ?? '未知错误';
        respondError('Hypixel API 错误：' . $cause, 502);
    }

    if (empty($data['player'])) {
        respondError('玩家 "' . PLAYER_USERNAME . '" 不存在或从未登录过 Hypixel', 404);
    }

    return $data;
}

/**
 * 从 Hypixel 响应中提取标准 UUID
 */
function extractUuidFromPlayer(array $player): string
{
    $uuid = $player['uuid'] ?? '';

    // Hypixel 可能返回无连字符格式
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

/**
 * 读取缓存
 */
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

/**
 * 写入缓存
 */
function writeCache(string $cacheKey, array $data): void
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }

    $cacheFile = CACHE_DIR . $cacheKey . '.json';
    file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/**
 * 解析并整理玩家数据
 */
function parsePlayerData(array $hypixelData, string $uuid): array
{
    $player = $hypixelData['player'] ?? null;

    if ($player === null) {
        respondError('玩家不存在或数据为空', 404);
    }

    $bw = $player['stats']['Bedwars'] ?? [];
    $achievements = $player['achievements'] ?? [];

    // 网络经验（兼容旧字段）
    $networkExp = statInt($player, 'networkExp');
    if ($networkExp === 0 && isset($player['networkLevel'])) {
        // 旧版 networkLevel 字段备用
        $networkExp = (int) ($player['networkLevel'] * 10000);
    }

    // BedWars 经验与星级
    $bedwarsExp = statInt($bw, 'Experience');
    $starProgress = bwGetStarProgress($bedwarsExp);
    $currentStarInt = $starProgress['currentStar'];

    // 总体 BedWars 统计
    $finalKills = statInt($bw, 'final_kills_bedwars');
    $finalDeaths = statInt($bw, 'final_deaths_bedwars');
    $kills = statInt($bw, 'kills_bedwars');
    $deaths = statInt($bw, 'deaths_bedwars');
    $wins = statInt($bw, 'wins_bedwars');
    $losses = statInt($bw, 'losses_bedwars');
    $bedsBroken = statInt($bw, 'beds_broken_bedwars');
    $bedsLost = statInt($bw, 'beds_lost_bedwars');
    $coins = statInt($bw, 'coins');

    // 战利品箱（兼容多种字段名）
    $lootChests = statInt($bw, 'bedwars_loot_bags');
    if ($lootChests === 0) {
        $lootChests = statInt($bw, 'bedwars_loot_chests');
    }

    // 格式化 UUID（无连字符，用于皮肤 API）
    $uuidClean = str_replace('-', '', $uuid);

    return [
        'success' => true,
        'cached' => false,
        'timestamp' => time(),
        'player' => [
            'username' => $player['displayname'] ?? PLAYER_USERNAME,
            'uuid' => $uuid,
            'uuidClean' => $uuidClean,
            'skinUrl' => 'https://mc-heads.net/body/' . $uuidClean . '/128',
            'avatarUrl' => 'https://mc-heads.net/avatar/' . $uuidClean . '/128',
            'rank' => formatPlayerRank($player),
            'rankColorClass' => getRankColorClass($player),
            'networkLevel' => round(getNetworkLevel($networkExp), 2),
            'karma' => statInt($player, 'karma'),
            'achievementPoints' => statInt($player, 'achievementPoints') ?: statInt($achievements, 'achievementpoints'),
        ],
        'bedwars' => [
            'star' => $starProgress,
            'starColorClass' => getBedwarsStarColorClass($currentStarInt),
            'stats' => [
                'fkdr' => calcRatio($finalKills, $finalDeaths),
                'kdr' => calcRatio($kills, $deaths),
                'winRate' => calcWinRate($wins, $losses),
                'bblr' => calcRatio($bedsBroken, $bedsLost),
            ],
            'details' => [
                'finalKills' => $finalKills,
                'finalDeaths' => $finalDeaths,
                'kills' => $kills,
                'deaths' => $deaths,
                'wins' => $wins,
                'losses' => $losses,
                'bedsBroken' => $bedsBroken,
                'bedsLost' => $bedsLost,
                'coins' => $coins,
                'lootChests' => $lootChests,
            ],
        ],
    ];
}

// ==================== 主流程 ====================

try {
    $cacheKey = 'player_' . strtolower(PLAYER_USERNAME);
    $cached = readCache($cacheKey);

    if ($cached !== null) {
        $cached['cached'] = true;
        $cached['cacheAge'] = time() - ($cached['timestamp'] ?? time());
        respondJson($cached);
    }

    // 直接用玩家名请求 Hypixel（无需 Mojang UUID 查询，避免国内网络卡住）
    $hypixelData = fetchHypixelPlayer(PLAYER_USERNAME, 'name');
    $uuid = extractUuidFromPlayer($hypixelData['player']);

    if ($uuid === '') {
        respondError('无法获取玩家 UUID', 500);
    }

    // 解析数据
    $result = parsePlayerData($hypixelData, $uuid);

    // 写入缓存
    writeCache($cacheKey, $result);

    respondJson($result);
} catch (Throwable $e) {
    error_log('Player API 异常: ' . $e->getMessage());
    respondError('服务器内部错误', 500);
}
