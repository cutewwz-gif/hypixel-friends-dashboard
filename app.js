/**
 * Hypixel BedWars Dashboard 前端逻辑
 * 从 PHP API 获取数据并渲染页面
 */

(function () {
    'use strict';

    // API 地址（通过 PHP 代理，不直接请求 Hypixel）
    const API_URL = 'api/player.php';

    // API 请求超时（毫秒）
    const FETCH_TIMEOUT = 20000;

    // DOM 元素引用
    const elements = {
        loadingState: document.getElementById('loadingState'),
        errorState: document.getElementById('errorState'),
        errorMessage: document.getElementById('errorMessage'),
        errorHint: document.getElementById('errorHint'),
        dashboard: document.getElementById('dashboard'),
        cacheBadge: document.getElementById('cacheBadge'),
        refreshBtn: document.getElementById('refreshBtn'),
        retryBtn: document.getElementById('retryBtn'),
        starsLayer: document.getElementById('starsLayer'),

        // 玩家信息
        playerSkin: document.getElementById('playerSkin'),
        playerName: document.getElementById('playerName'),
        playerRank: document.getElementById('playerRank'),
        networkLevel: document.getElementById('networkLevel'),
        karma: document.getElementById('karma'),
        achievementPoints: document.getElementById('achievementPoints'),

        // BedWars 星级
        starNumber: document.getElementById('starNumber'),
        starProgressFill: document.getElementById('starProgressFill'),
        currentExp: document.getElementById('currentExp'),
        requiredExp: document.getElementById('requiredExp'),

        // 核心统计
        statFkdr: document.getElementById('statFkdr'),
        statKdr: document.getElementById('statKdr'),
        statWinRate: document.getElementById('statWinRate'),
        statBblr: document.getElementById('statBblr'),

        // 详细数据
        detailsTableBody: document.getElementById('detailsTableBody'),
    };

    // 详细数据字段映射（中文标签）
    const DETAIL_LABELS = {
        finalKills: 'Final Kills',
        finalDeaths: 'Final Deaths',
        kills: 'Kills',
        deaths: 'Deaths',
        wins: 'Wins',
        losses: 'Losses',
        bedsBroken: 'Beds Broken',
        bedsLost: 'Beds Lost',
        coins: 'Coins',
        lootChests: 'Loot Chests',
    };

    /**
     * 初始化星空背景
     */
    function initStars() {
        const starCount = window.innerWidth < 640 ? 60 : 120;
        const fragment = document.createDocumentFragment();

        for (let i = 0; i < starCount; i++) {
            const star = document.createElement('div');
            star.className = 'star';

            const size = Math.random() * 2 + 1;
            star.style.cssText = `
                width: ${size}px;
                height: ${size}px;
                left: ${Math.random() * 100}%;
                top: ${Math.random() * 100}%;
                --opacity: ${Math.random() * 0.5 + 0.3};
                --duration: ${Math.random() * 3 + 2}s;
                animation-delay: ${Math.random() * 3}s;
            `;

            fragment.appendChild(star);
        }

        elements.starsLayer.appendChild(fragment);
    }

    /**
     * 格式化数字（千位分隔符）
     */
    function formatNumber(num) {
        return Number(num).toLocaleString('en-US');
    }

    /**
     * 格式化比率显示
     */
    function formatRatio(value) {
        return Number(value).toFixed(2);
    }

    /**
     * 显示加载状态
     */
    function showLoading() {
        elements.loadingState.hidden = false;
        elements.errorState.hidden = true;
        elements.dashboard.hidden = true;
        elements.refreshBtn.classList.add('loading');
    }

    /**
     * 显示错误状态
     */
    function showError(message, hint) {
        elements.loadingState.hidden = true;
        elements.errorState.hidden = false;
        elements.dashboard.hidden = true;
        elements.errorMessage.textContent = message;
        elements.refreshBtn.classList.remove('loading');

        if (hint) {
            elements.errorHint.hidden = false;
            elements.errorHint.textContent = hint;
        } else {
            elements.errorHint.hidden = true;
            elements.errorHint.textContent = '';
        }
    }

    /**
     * 检查运行环境（不能直接双击打开 HTML）
     */
    function checkEnvironment() {
        if (window.location.protocol === 'file:') {
            showError(
                '不能直接双击打开 index.html',
                '此项目需要 PHP 后端运行。\n\n' +
                '本地测试：在项目目录运行 start.bat，然后访问 http://localhost:8080\n\n' +
                '服务器部署：将项目上传到 1Panel 网站目录，通过域名访问'
            );
            return false;
        }
        return true;
    }

    /**
     * 带超时的 fetch 封装
     */
    function fetchWithTimeout(url, options, timeout) {
        return new Promise(function (resolve, reject) {
            var controller = new AbortController();
            var timer = setTimeout(function () {
                controller.abort();
                reject(new Error('请求超时（' + (timeout / 1000) + ' 秒），请检查 PHP 是否运行以及服务器网络'));
            }, timeout);

            fetch(url, Object.assign({}, options || {}, { signal: controller.signal }))
                .then(function (response) {
                    clearTimeout(timer);
                    resolve(response);
                })
                .catch(function (err) {
                    clearTimeout(timer);
                    if (err.name === 'AbortError') {
                        reject(new Error('请求超时（' + (timeout / 1000) + ' 秒），请检查 PHP 是否运行以及服务器网络'));
                    } else {
                        reject(err);
                    }
                });
        });
    }

    /**
     * 解析 API 响应（兼容 PHP 未运行的情况）
     */
    function parseApiResponse(response, rawText) {
        if (rawText.indexOf('<?php') !== -1 || rawText.indexOf('define(') !== -1) {
            throw new Error('PHP 未执行：浏览器收到了 PHP 源码而不是 JSON 数据');
        }

        try {
            return JSON.parse(rawText);
        } catch (parseError) {
            if (response.status === 404) {
                throw new Error('找不到 api/player.php，请确认项目已完整上传且 Web 服务器已配置 PHP');
            }
            throw new Error('服务器返回了无效数据，请确认 PHP 环境已正确配置');
        }
    }

    /**
     * 显示 Dashboard
     */
    function showDashboard() {
        elements.loadingState.hidden = true;
        elements.errorState.hidden = true;
        elements.dashboard.hidden = false;
        elements.refreshBtn.classList.remove('loading');
    }

    /**
     * 更新缓存状态徽章
     */
    function updateCacheBadge(data) {
        if (data.cached) {
            elements.cacheBadge.hidden = false;
            elements.cacheBadge.textContent = `缓存 ${data.cacheAge || 0}s 前`;
        } else {
            elements.cacheBadge.hidden = false;
            elements.cacheBadge.textContent = '刚刚更新';
        }
    }

    /**
     * 渲染玩家信息
     */
    function renderPlayer(player) {
        elements.playerSkin.src = player.skinUrl;
        elements.playerSkin.alt = player.username + ' 的皮肤';
        elements.playerName.textContent = player.username;
        elements.playerRank.textContent = player.rank;

        // 清除旧 rank 类名并应用新类名
        elements.playerRank.className = 'player-rank ' + (player.rankColorClass || 'rank-default');

        elements.networkLevel.textContent = formatNumber(player.networkLevel);
        elements.karma.textContent = formatNumber(player.karma);
        elements.achievementPoints.textContent = formatNumber(player.achievementPoints);
    }

    /**
     * 渲染 BedWars 星级与进度条
     */
    function renderBedwarsStar(bedwars) {
        const star = bedwars.star;

        elements.starNumber.textContent = star.level;
        elements.starNumber.className = 'star-number ' + (bedwars.starColorClass || 'star-gray');

        // 进度条动画（先重置再设置）
        elements.starProgressFill.style.width = '0%';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                elements.starProgressFill.style.width = star.progressPercent + '%';
            });
        });

        elements.currentExp.textContent = formatNumber(star.currentExp);
        elements.requiredExp.textContent = formatNumber(star.requiredExp);
    }

    /**
     * 渲染核心统计数据
     */
    function renderStats(stats) {
        elements.statFkdr.textContent = formatRatio(stats.fkdr);
        elements.statKdr.textContent = formatRatio(stats.kdr);
        elements.statWinRate.textContent = formatRatio(stats.winRate) + '%';
        elements.statBblr.textContent = formatRatio(stats.bblr);
    }

    /**
     * 渲染详细数据表格
     */
    function renderDetails(details) {
        elements.detailsTableBody.innerHTML = '';

        Object.keys(DETAIL_LABELS).forEach(function (key) {
            const row = document.createElement('tr');
            row.innerHTML =
                '<td>' + DETAIL_LABELS[key] + '</td>' +
                '<td><span class="num-highlight">' + formatNumber(details[key] || 0) + '</span></td>';
            elements.detailsTableBody.appendChild(row);
        });
    }

    /**
     * 渲染完整 Dashboard
     */
    function renderDashboard(data) {
        renderPlayer(data.player);
        renderBedwarsStar(data.bedwars);
        renderStats(data.bedwars.stats);
        renderDetails(data.bedwars.details);
        updateCacheBadge(data);
        showDashboard();
    }

    /**
     * 从 API 获取玩家数据
     * @param {boolean} forceRefresh - 是否强制刷新（绕过浏览器缓存）
     */
    async function fetchPlayerData(forceRefresh) {
        if (!checkEnvironment()) {
            return;
        }

        showLoading();

        try {
            var url = API_URL;
            if (forceRefresh) {
                url += '?t=' + Date.now();
            }

            var response = await fetchWithTimeout(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                cache: forceRefresh ? 'no-cache' : 'default',
            }, FETCH_TIMEOUT);

            var rawText = await response.text();
            var data = parseApiResponse(response, rawText);

            if (!response.ok || !data.success) {
                throw new Error(data.error || '请求失败 (HTTP ' + response.status + ')');
            }

            renderDashboard(data);
        } catch (error) {
            console.error('获取数据失败:', error);

            var hint = '';
            if (error.message.indexOf('Failed to fetch') !== -1 || error.message.indexOf('NetworkError') !== -1) {
                hint = '无法连接到 api/player.php。\n\n' +
                    '请确认：\n' +
                    '1. 已通过 Web 服务器访问（不是双击 HTML 文件）\n' +
                    '2. PHP 已安装并启用\n' +
                    '3. 网站根目录指向本项目文件夹';
            } else if (error.message.indexOf('PHP') !== -1) {
                hint = '请通过 1Panel 网站或本地 PHP 服务器访问，例如：\nhttp://你的域名 或 http://localhost:8080';
            }

            showError(error.message || '网络错误，请检查服务器连接', hint);
        }
    }

    /**
     * 绑定事件
     */
    function bindEvents() {
        elements.refreshBtn.addEventListener('click', function () {
            fetchPlayerData(true);
        });

        elements.retryBtn.addEventListener('click', function () {
            fetchPlayerData(true);
        });
    }

    /**
     * 初始化应用
     */
    function init() {
        initStars();
        bindEvents();

        if (checkEnvironment()) {
            fetchPlayerData(false);
        }
    }

    // 页面加载完成后启动
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
