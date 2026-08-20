/**
 * Hypixel 好友详情页前端逻辑
 * 热力图、活跃分析、每 60 秒局部刷新
 */

(function () {
    'use strict';

    const API_URL = 'api/friend.php';
    const REFRESH_INTERVAL = 60000;
    const FETCH_TIMEOUT = 30000;

    const elements = {
        loadingState: document.getElementById('loadingState'),
        errorState: document.getElementById('errorState'),
        errorMessage: document.getElementById('errorMessage'),
        errorHint: document.getElementById('errorHint'),
        friendDetail: document.getElementById('friendDetail'),
        cacheBadge: document.getElementById('cacheBadge'),
        refreshBtn: document.getElementById('refreshBtn'),
        starsLayer: document.getElementById('starsLayer'),

        playerSkin: document.getElementById('playerSkin'),
        playerName: document.getElementById('playerName'),
        playerRank: document.getElementById('playerRank'),
        playerGame: document.getElementById('playerGame'),
        onlineBadge: document.getElementById('onlineBadge'),
        networkLevel: document.getElementById('networkLevel'),
        bedwarsStar: document.getElementById('bedwarsStar'),
        fkdr: document.getElementById('fkdr'),
        updateTime: document.getElementById('updateTime'),

        heatmapGrid: document.getElementById('heatmapGrid'),
        heatmapTooltip: document.getElementById('heatmapTooltip'),

        mostActiveTime: document.getElementById('mostActiveTime'),
        mostActiveWeekday: document.getElementById('mostActiveWeekday'),
        avgDailyOnline: document.getElementById('avgDailyOnline'),
        lastOnline: document.getElementById('lastOnline'),
        lastOffline: document.getElementById('lastOffline'),
        activeDays: document.getElementById('activeDays'),
    };

    let refreshTimer = null;
    let isFirstLoad = true;
    let activeTooltipCell = null;
    let playerName = '';

    function getQueryParam(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name) || '';
    }

    function initStars() {
        const starCount = window.innerWidth < 640 ? 60 : 120;
        const fragment = document.createDocumentFragment();

        for (let i = 0; i < starCount; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            const size = Math.random() * 2 + 1;
            star.style.cssText = [
                'width:' + size + 'px',
                'height:' + size + 'px',
                'left:' + (Math.random() * 100) + '%',
                'top:' + (Math.random() * 100) + '%',
                '--opacity:' + (Math.random() * 0.5 + 0.3),
                '--duration:' + (Math.random() * 3 + 2) + 's',
            ].join(';');
            fragment.appendChild(star);
        }

        elements.starsLayer.appendChild(fragment);
    }

    function checkEnvironment() {
        if (window.location.protocol === 'file:') {
            showError('不能直接双击打开 HTML 文件', '请通过 Web 服务器访问');
            return false;
        }
        return true;
    }

    function showLoading() {
        if (isFirstLoad) {
            elements.loadingState.hidden = false;
            elements.friendDetail.hidden = true;
        }
        elements.errorState.hidden = true;
        elements.refreshBtn.classList.add('loading');
    }

    function showError(message, hint) {
        elements.loadingState.hidden = true;
        elements.errorState.hidden = false;
        elements.friendDetail.hidden = true;
        elements.errorMessage.textContent = message;
        elements.refreshBtn.classList.remove('loading');

        if (hint) {
            elements.errorHint.hidden = false;
            elements.errorHint.textContent = hint;
        }
    }

    function showContent() {
        elements.loadingState.hidden = true;
        elements.errorState.hidden = true;
        elements.friendDetail.hidden = false;
        elements.friendDetail.classList.add('visible');
        elements.refreshBtn.classList.remove('loading');
        isFirstLoad = false;
    }

    function fetchWithTimeout(url, options, timeout) {
        return new Promise(function (resolve, reject) {
            const controller = new AbortController();
            const timer = setTimeout(function () {
                controller.abort();
                reject(new Error('请求超时'));
            }, timeout);

            fetch(url, Object.assign({}, options || {}, { signal: controller.signal }))
                .then(function (response) {
                    clearTimeout(timer);
                    resolve(response);
                })
                .catch(function (err) {
                    clearTimeout(timer);
                    reject(err);
                });
        });
    }

    function formatNumber(num) {
        return Number(num).toLocaleString('en-US');
    }

    function getHeatLevel(count, maxCount) {
        if (count <= 0) return 0;
        if (maxCount <= 0) return 1;
        const ratio = count / maxCount;
        if (ratio <= 0.25) return 1;
        if (ratio <= 0.5) return 2;
        if (ratio <= 0.75) return 3;
        return 4;
    }

    function formatHour(hour) {
        return String(hour).padStart(2, '0');
    }

    function renderPlayer(player) {
        elements.playerSkin.src = player.skinUrl;
        elements.playerSkin.alt = player.username + ' 的皮肤';
        elements.playerName.textContent = player.username;
        elements.playerRank.textContent = player.rank;
        elements.playerRank.className = 'player-rank ' + (player.rankColorClass || 'rank-default');

        elements.networkLevel.textContent = formatNumber(player.networkLevel);
        elements.bedwarsStar.textContent = player.bedwarsStar;
        elements.bedwarsStar.className = 'meta-value highlight ' + (player.starColorClass || '');
        elements.fkdr.textContent = Number(player.fkdr).toFixed(2);

        if (player.online) {
            elements.onlineBadge.className = 'online-badge online';
            elements.onlineBadge.textContent = '在线';
        } else {
            elements.onlineBadge.className = 'online-badge offline';
            elements.onlineBadge.textContent = '离线';
        }

        if (player.online && player.game) {
            elements.playerGame.hidden = false;
            elements.playerGame.textContent = '🎮 ' + player.game;
        } else {
            elements.playerGame.hidden = true;
        }

        elements.updateTime.textContent = '最后更新：' + (player.updatedAtFormatted || '') +
            (player.lastSeenRelative ? ' · 最近活动 ' + player.lastSeenRelative : '');

        document.title = player.username + ' · Hypixel Friends';

        elements.cacheBadge.hidden = false;
        elements.cacheBadge.textContent = player.online ? '当前在线' : '当前离线';
    }

    function renderAnalytics(analytics) {
        elements.mostActiveTime.textContent = analytics.mostActiveTime || '暂无数据';
        elements.mostActiveWeekday.textContent = analytics.mostActiveWeekday || '暂无数据';
        elements.avgDailyOnline.textContent = analytics.avgDailyOnline || '暂无数据';
        elements.lastOnline.textContent = analytics.lastOnlineFormatted || '暂无记录';
        elements.lastOffline.textContent = analytics.lastOfflineFormatted || '暂无记录';
        elements.activeDays.textContent = (analytics.activeDays || 0) + ' 天';
    }

    function hideTooltip() {
        elements.heatmapTooltip.hidden = true;
        activeTooltipCell = null;
    }

    function showTooltip(cell, date, hour, count, event) {
        const hourStr = formatHour(hour) + ':00';
        elements.heatmapTooltip.innerHTML =
            '<strong>' + date + '</strong><br>' +
            '时间：' + hourStr + '<br>' +
            '在线检测次数：' + count;

        elements.heatmapTooltip.hidden = false;
        activeTooltipCell = cell;

        positionTooltip(event);
    }

    function positionTooltip(event) {
        const tooltip = elements.heatmapTooltip;
        const x = event.clientX || (event.touches && event.touches[0].clientX) || 0;
        const y = event.clientY || (event.touches && event.touches[0].clientY) || 0;

        tooltip.style.left = Math.min(x + 12, window.innerWidth - tooltip.offsetWidth - 12) + 'px';
        tooltip.style.top = Math.max(y - tooltip.offsetHeight - 12, 8) + 'px';
    }

    function bindCellEvents(cell, date, hour, count) {
        cell.addEventListener('mouseenter', function (e) {
            showTooltip(cell, date, hour, count, e);
        });

        cell.addEventListener('mousemove', function (e) {
            if (activeTooltipCell === cell) positionTooltip(e);
        });

        cell.addEventListener('mouseleave', hideTooltip);

        cell.addEventListener('click', function (e) {
            e.stopPropagation();
            if (activeTooltipCell === cell) {
                hideTooltip();
            } else {
                showTooltip(cell, date, hour, count, e);
            }
        });
    }

    function renderHeatmap(heatmap) {
        const grid = heatmap.grid || [];
        const maxCount = heatmap.maxCount || 0;

        const container = document.createElement('div');
        container.className = 'heatmap-inner';

        const hourHeader = document.createElement('div');
        hourHeader.className = 'heatmap-hour-header';
        hourHeader.innerHTML = '<span class="heatmap-corner"></span>';

        for (let h = 0; h < 24; h++) {
            const label = document.createElement('span');
            label.className = 'heatmap-hour-label';
            label.textContent = formatHour(h);
            hourHeader.appendChild(label);
        }

        container.appendChild(hourHeader);

        const body = document.createElement('div');
        body.className = 'heatmap-body';

        grid.forEach(function (row) {
            const rowEl = document.createElement('div');
            rowEl.className = 'heatmap-row';

            const dayLabel = document.createElement('span');
            dayLabel.className = 'heatmap-day-label';
            dayLabel.textContent = row.label;
            dayLabel.title = row.date;
            rowEl.appendChild(dayLabel);

            const cellsWrap = document.createElement('div');
            cellsWrap.className = 'heatmap-cells';

            row.hours.forEach(function (cell) {
                const cellEl = document.createElement('span');
                const level = getHeatLevel(cell.count, maxCount);
                cellEl.className = 'heatmap-cell level-' + level;
                cellEl.setAttribute('role', 'gridcell');
                cellEl.setAttribute('aria-label', row.date + ' ' + formatHour(cell.hour) + ':00 检测' + cell.count + '次');
                bindCellEvents(cellEl, row.date, cell.hour, cell.count);
                cellsWrap.appendChild(cellEl);
            });

            rowEl.appendChild(cellsWrap);
            body.appendChild(rowEl);
        });

        container.appendChild(body);
        elements.heatmapGrid.innerHTML = '';
        elements.heatmapGrid.appendChild(container);
    }

    function renderDetail(data) {
        renderPlayer(data.player);
        renderHeatmap(data.heatmap);
        renderAnalytics(data.analytics);
        showContent();
    }

    async function fetchDetail(forceRefresh) {
        if (!checkEnvironment()) return;

        if (!playerName) {
            showError('缺少玩家名称', '请从好友列表点击进入，或访问 friend.html?name=玩家ID');
            return;
        }

        showLoading();

        try {
            let url = API_URL + '?name=' + encodeURIComponent(playerName);
            if (forceRefresh) {
                url += '&t=' + Date.now();
            }

            const response = await fetchWithTimeout(url, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                cache: forceRefresh ? 'no-cache' : 'default',
            }, FETCH_TIMEOUT);

            const rawText = await response.text();
            const data = JSON.parse(rawText);

            if (!response.ok || !data.success) {
                throw new Error(data.error || '请求失败');
            }

            renderDetail(data);
        } catch (error) {
            console.error('加载详情失败:', error);
            if (isFirstLoad) {
                showError(error.message || '网络错误');
            }
            elements.refreshBtn.classList.remove('loading');
        }
    }

    function startAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(function () {
            fetchDetail(true);
        }, REFRESH_INTERVAL);
    }

    function bindEvents() {
        elements.refreshBtn.addEventListener('click', function () {
            fetchDetail(true);
        });

        document.addEventListener('click', function () {
            hideTooltip();
        });

        document.querySelector('.back-link').addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.add('page-exit');
            setTimeout(function () {
                window.location.href = 'friends.html';
            }, 280);
        });
    }

    function init() {
        playerName = getQueryParam('name').trim();
        document.body.classList.add('page-enter');
        initStars();
        bindEvents();

        if (checkEnvironment()) {
            fetchDetail(false);
            startAutoRefresh();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
