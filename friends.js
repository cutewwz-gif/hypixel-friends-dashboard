/**
 * Hypixel 好友列表页前端逻辑
 * 每 60 秒自动刷新，不整页重载
 */

(function () {
    'use strict';

    const API_URL = 'api/friends.php';
    const EDIT_API_URL = 'api/friends_edit.php';
    const REFRESH_INTERVAL = 60000;
    const FETCH_TIMEOUT = 30000;
    const PASSWORD_STORAGE_KEY = 'hypf_friends_edit_password';

    const elements = {
        loadingState: document.getElementById('loadingState'),
        errorState: document.getElementById('errorState'),
        errorMessage: document.getElementById('errorMessage'),
        errorHint: document.getElementById('errorHint'),
        friendsMain: document.getElementById('friendsMain'),
        friendsGrid: document.getElementById('friendsGrid'),
        friendsEmpty: document.getElementById('friendsEmpty'),
        cacheBadge: document.getElementById('cacheBadge'),
        onlineCount: document.getElementById('onlineCount'),
        refreshBtn: document.getElementById('refreshBtn'),
        retryBtn: document.getElementById('retryBtn'),
        starsLayer: document.getElementById('starsLayer'),
        editFriendsBtn: document.getElementById('editFriendsBtn'),
        emptyEditBtn: document.getElementById('emptyEditBtn'),
        editModal: document.getElementById('editModal'),
        editModalClose: document.getElementById('editModalClose'),
        editPasswordSection: document.getElementById('editPasswordSection'),
        editPassword: document.getElementById('editPassword'),
        newFriendInput: document.getElementById('newFriendInput'),
        addFriendBtn: document.getElementById('addFriendBtn'),
        editFriendsList: document.getElementById('editFriendsList'),
        editListEmpty: document.getElementById('editListEmpty'),
        editFriendCount: document.getElementById('editFriendCount'),
        editFriendLimit: document.getElementById('editFriendLimit'),
        editStatus: document.getElementById('editStatus'),
        editCancelBtn: document.getElementById('editCancelBtn'),
        editSaveBtn: document.getElementById('editSaveBtn'),
    };

    let refreshTimer = null;
    let isFirstLoad = true;
    let editFriends = [];
    let editMaxCount = 50;
    let editNeedPassword = false;
    let isSaving = false;

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
                'animation-delay:' + (Math.random() * 3) + 's',
            ].join(';');
            fragment.appendChild(star);
        }

        elements.starsLayer.appendChild(fragment);
    }

    function checkEnvironment() {
        if (window.location.protocol === 'file:') {
            showError(
                '不能直接双击打开 HTML 文件',
                '请通过 Web 服务器访问，例如 http://localhost:8080/friends.html'
            );
            return false;
        }
        return true;
    }

    function showLoading() {
        if (isFirstLoad) {
            elements.loadingState.hidden = false;
            elements.friendsMain.hidden = true;
        }
        elements.errorState.hidden = true;
        elements.refreshBtn.classList.add('loading');
    }

    function showError(message, hint) {
        elements.loadingState.hidden = true;
        elements.errorState.hidden = false;
        elements.friendsMain.hidden = true;
        elements.errorMessage.textContent = message;
        elements.refreshBtn.classList.remove('loading');

        if (hint) {
            elements.errorHint.hidden = false;
            elements.errorHint.textContent = hint;
        } else {
            elements.errorHint.hidden = true;
        }
    }

    function showContent() {
        elements.loadingState.hidden = true;
        elements.errorState.hidden = true;
        elements.friendsMain.hidden = false;
        elements.friendsMain.classList.add('visible');
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

    function animateNumber(el, newValue, decimals) {
        const current = parseFloat(el.textContent.replace(/,/g, '')) || 0;
        const target = parseFloat(newValue);
        if (isNaN(target) || current === target) {
            el.textContent = decimals !== undefined
                ? target.toFixed(decimals)
                : formatNumber(target);
            return;
        }

        const duration = 600;
        const start = performance.now();

        function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = current + (target - current) * eased;

            el.textContent = decimals !== undefined
                ? value.toFixed(decimals)
                : formatNumber(Math.round(value));

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    function createFriendCard(friend) {
        const card = document.createElement('a');
        card.className = 'friend-card glass-card' + (friend.online ? ' is-online' : ' is-offline');
        card.href = 'friend.html?name=' + encodeURIComponent(friend.username);
        card.dataset.username = friend.username;

        const statusClass = friend.online ? 'online' : 'offline';
        const statusText = friend.online ? '在线' : '离线';
        const gameHtml = friend.online && friend.game
            ? '<div class="friend-game">' + escapeHtml(friend.game) + '</div>'
            : '';

        card.innerHTML =
            '<div class="friend-card-inner">' +
                '<div class="friend-skin-wrap">' +
                    '<div class="skin-glow"></div>' +
                    '<img src="' + escapeHtml(friend.skinUrl) + '" alt="' + escapeHtml(friend.username) + '" class="friend-skin" loading="lazy">' +
                    '<span class="status-dot ' + statusClass + '"></span>' +
                '</div>' +
                '<div class="friend-info">' +
                    '<h2 class="friend-name">' + escapeHtml(friend.username) + '</h2>' +
                    '<div class="friend-rank ' + escapeHtml(friend.rankColorClass || 'rank-default') + '">' + escapeHtml(friend.rank) + '</div>' +
                    '<div class="friend-stats">' +
                        '<div class="friend-stat"><span class="stat-label">NW Level</span><span class="stat-val" data-field="networkLevel">' + formatNumber(friend.networkLevel) + '</span></div>' +
                        '<div class="friend-stat"><span class="stat-label">BW Star</span><span class="stat-val ' + escapeHtml(friend.starColorClass || '') + '" data-field="bedwarsStar">' + friend.bedwarsStar + '</span></div>' +
                        '<div class="friend-stat"><span class="stat-label">FKDR</span><span class="stat-val" data-field="fkdr">' + Number(friend.fkdr).toFixed(2) + '</span></div>' +
                    '</div>' +
                    '<div class="friend-status-row">' +
                        '<span class="status-badge ' + statusClass + '">' + statusText + '</span>' +
                        (friend.online ? '' : '<span class="last-seen">' + escapeHtml(friend.lastSeenRelative || '未知') + '</span>') +
                    '</div>' +
                    gameHtml +
                    '<div class="friend-updated">更新于 ' + escapeHtml(friend.updatedAtFormatted || '') + '</div>' +
                '</div>' +
            '</div>';

        card.addEventListener('click', function (e) {
            document.body.classList.add('page-exit');
            e.preventDefault();
            const href = card.href;
            setTimeout(function () {
                window.location.href = href;
            }, 280);
        });

        return card;
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateFriendCard(card, friend) {
        card.classList.toggle('is-online', friend.online);
        card.classList.toggle('is-offline', !friend.online);

        const statusDot = card.querySelector('.status-dot');
        const statusBadge = card.querySelector('.status-badge');
        const lastSeen = card.querySelector('.last-seen');
        const gameEl = card.querySelector('.friend-game');
        const updatedEl = card.querySelector('.friend-updated');
        const rankEl = card.querySelector('.friend-rank');

        if (statusDot) {
            statusDot.className = 'status-dot ' + (friend.online ? 'online' : 'offline');
        }

        if (statusBadge) {
            statusBadge.className = 'status-badge ' + (friend.online ? 'online' : 'offline');
            statusBadge.textContent = friend.online ? '在线' : '离线';
        }

        if (lastSeen) {
            if (friend.online) {
                lastSeen.style.display = 'none';
            } else {
                lastSeen.style.display = '';
                lastSeen.textContent = friend.lastSeenRelative || '未知';
            }
        }

        if (friend.online && friend.game) {
            if (!gameEl) {
                const info = card.querySelector('.friend-info');
                const div = document.createElement('div');
                div.className = 'friend-game';
                div.textContent = friend.game;
                info.insertBefore(div, updatedEl);
            } else {
                gameEl.textContent = friend.game;
                gameEl.hidden = false;
            }
        } else if (gameEl) {
            gameEl.remove();
        }

        if (updatedEl) {
            updatedEl.textContent = '更新于 ' + (friend.updatedAtFormatted || '');
        }

        if (rankEl) {
            rankEl.className = 'friend-rank ' + (friend.rankColorClass || 'rank-default');
            rankEl.textContent = friend.rank;
        }

        const nwEl = card.querySelector('[data-field="networkLevel"]');
        const starEl = card.querySelector('[data-field="bedwarsStar"]');
        const fkdrEl = card.querySelector('[data-field="fkdr"]');

        if (nwEl) animateNumber(nwEl, friend.networkLevel);
        if (starEl) {
            starEl.textContent = friend.bedwarsStar;
            starEl.className = 'stat-val ' + (friend.starColorClass || '');
        }
        if (fkdrEl) animateNumber(fkdrEl, friend.fkdr, 2);
    }

    function renderFriends(data) {
        const friends = data.friends || [];

        if (friends.length === 0) {
            elements.friendsGrid.innerHTML = '';
            elements.friendsEmpty.hidden = false;
            showContent();
            return;
        }

        elements.friendsEmpty.hidden = true;

        const existingCards = {};
        elements.friendsGrid.querySelectorAll('.friend-card').forEach(function (card) {
            existingCards[card.dataset.username.toLowerCase()] = card;
        });

        const fragment = document.createDocumentFragment();
        let onlineCount = 0;

        friends.forEach(function (friend) {
            if (friend.online) onlineCount++;

            const key = friend.username.toLowerCase();
            const existing = existingCards[key];

            if (existing) {
                updateFriendCard(existing, friend);
                fragment.appendChild(existing);
                delete existingCards[key];
            } else {
                fragment.appendChild(createFriendCard(friend));
            }
        });

        Object.keys(existingCards).forEach(function (key) {
            existingCards[key].remove();
        });

        elements.friendsGrid.innerHTML = '';
        elements.friendsGrid.appendChild(fragment);

        elements.onlineCount.hidden = false;
        elements.onlineCount.textContent = onlineCount + ' 人在线';

        elements.cacheBadge.hidden = false;
        elements.cacheBadge.textContent = '更新于 ' + new Date().toLocaleTimeString('zh-CN');

        showContent();
    }

    async function fetchFriends(forceRefresh) {
        if (!checkEnvironment()) return;

        showLoading();

        try {
            let url = API_URL;
            if (forceRefresh) {
                url += '?t=' + Date.now();
            }

            const response = await fetchWithTimeout(url, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                cache: forceRefresh ? 'no-cache' : 'default',
            }, FETCH_TIMEOUT);

            const rawText = await response.text();
            let data;

            try {
                data = JSON.parse(rawText);
            } catch (e) {
                throw new Error('服务器返回了无效数据');
            }

            if (!response.ok || !data.success) {
                throw new Error(data.error || '请求失败');
            }

            renderFriends(data);
        } catch (error) {
            console.error('加载好友失败:', error);
            if (isFirstLoad) {
                showError(error.message || '网络错误', '请确认 PHP 已运行且 api/friends.json 已配置');
            }
            elements.refreshBtn.classList.remove('loading');
        }
    }

    function startAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(function () {
            fetchFriends(true);
        }, REFRESH_INTERVAL);
    }

    function bindEvents() {
        elements.refreshBtn.addEventListener('click', function () {
            fetchFriends(true);
        });

        elements.retryBtn.addEventListener('click', function () {
            fetchFriends(true);
        });

        elements.editFriendsBtn.addEventListener('click', openEditModal);
        elements.emptyEditBtn.addEventListener('click', openEditModal);
        elements.editModalClose.addEventListener('click', closeEditModal);
        elements.editCancelBtn.addEventListener('click', closeEditModal);
        elements.editModal.addEventListener('click', function (e) {
            if (e.target === elements.editModal) closeEditModal();
        });
        elements.addFriendBtn.addEventListener('click', addFriendFromInput);
        elements.newFriendInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') addFriendFromInput();
        });
        elements.editSaveBtn.addEventListener('click', saveFriendsList);
        elements.editPassword.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') loadEditFriends();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !elements.editModal.hidden) {
                closeEditModal();
            }
        });
    }

    function getStoredPassword() {
        try {
            return sessionStorage.getItem(PASSWORD_STORAGE_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    function storePassword(password) {
        try {
            if (password) {
                sessionStorage.setItem(PASSWORD_STORAGE_KEY, password);
            } else {
                sessionStorage.removeItem(PASSWORD_STORAGE_KEY);
            }
        } catch (e) {
            // 忽略
        }
    }

    function setEditStatus(message, type) {
        if (!message) {
            elements.editStatus.hidden = true;
            elements.editStatus.textContent = '';
            elements.editStatus.className = 'edit-status';
            return;
        }

        elements.editStatus.hidden = false;
        elements.editStatus.textContent = message;
        elements.editStatus.className = 'edit-status ' + (type || '');
    }

    function renderEditList() {
        elements.editFriendsList.innerHTML = '';
        elements.editFriendCount.textContent = editFriends.length;
        elements.editFriendLimit.textContent = '上限 ' + editMaxCount + ' 人';
        elements.editListEmpty.hidden = editFriends.length > 0;

        editFriends.forEach(function (name, index) {
            const li = document.createElement('li');
            li.className = 'edit-friend-item';
            li.innerHTML =
                '<span class="edit-friend-name">' + escapeHtml(name) + '</span>' +
                '<button type="button" class="edit-remove-btn" data-index="' + index + '" title="移除">删除</button>';
            elements.editFriendsList.appendChild(li);
        });

        elements.editFriendsList.querySelectorAll('.edit-remove-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const idx = parseInt(btn.dataset.index, 10);
                editFriends.splice(idx, 1);
                renderEditList();
            });
        });
    }

    function validateFriendName(name) {
        if (!name || name.length < 3 || name.length > 16) {
            return '玩家名长度需为 3-16 个字符';
        }
        if (!/^[a-zA-Z0-9_]+$/.test(name)) {
            return '仅允许字母、数字和下划线';
        }
        return null;
    }

    function addFriendFromInput() {
        const name = elements.newFriendInput.value.trim();
        const error = validateFriendName(name);

        if (error) {
            setEditStatus(error, 'error');
            return;
        }

        if (editFriends.length >= editMaxCount) {
            setEditStatus('已达好友上限 ' + editMaxCount + ' 人', 'error');
            return;
        }

        const exists = editFriends.some(function (item) {
            return item.toLowerCase() === name.toLowerCase();
        });

        if (exists) {
            setEditStatus('该玩家已在列表中', 'error');
            return;
        }

        editFriends.push(name);
        elements.newFriendInput.value = '';
        setEditStatus('');
        renderEditList();
        elements.newFriendInput.focus();
    }

    async function loadEditFriends() {
        setEditStatus('正在加载...', '');

        const password = editNeedPassword
            ? (elements.editPassword.value.trim() || getStoredPassword())
            : '';

        try {
            let url = EDIT_API_URL;
            if (password) {
                url += '?password=' + encodeURIComponent(password);
            }

            const response = await fetchWithTimeout(url, {
                method: 'GET',
                headers: { Accept: 'application/json' },
            }, FETCH_TIMEOUT);

            const data = JSON.parse(await response.text());

            if (!response.ok || !data.success) {
                if (response.status === 401) {
                    editNeedPassword = true;
                    storePassword('');
                    elements.editPasswordSection.hidden = false;
                    setEditStatus('请输入编辑密码后重试', 'error');
                    return;
                }
                throw new Error(data.error || '加载失败');
            }

            editFriends = data.friends || [];
            editMaxCount = data.maxCount || 50;
            editNeedPassword = !!data.needPassword;

            if (editNeedPassword && password) {
                storePassword(password);
                elements.editPasswordSection.hidden = true;
            } else if (!editNeedPassword) {
                elements.editPasswordSection.hidden = true;
            }

            if (!data.writable) {
                setEditStatus('警告：api/friends.json 不可写，保存可能失败', 'error');
            } else {
                setEditStatus('');
            }

            renderEditList();
        } catch (error) {
            setEditStatus(error.message || '加载失败', 'error');
        }
    }

    async function openEditModal() {
        elements.editModal.hidden = false;
        document.body.classList.add('modal-open');
        elements.newFriendInput.value = '';
        setEditStatus('');

        const stored = getStoredPassword();
        if (stored) {
            elements.editPassword.value = stored;
        }

        await loadEditFriends();

        if (!editNeedPassword || getStoredPassword()) {
            elements.newFriendInput.focus();
        } else {
            elements.editPassword.focus();
        }
    }

    function closeEditModal() {
        elements.editModal.hidden = true;
        document.body.classList.remove('modal-open');
        setEditStatus('');
    }

    async function saveFriendsList() {
        if (isSaving) return;

        const password = editNeedPassword
            ? (elements.editPassword.value.trim() || getStoredPassword())
            : '';

        if (editNeedPassword && !password) {
            setEditStatus('请先输入编辑密码', 'error');
            elements.editPasswordSection.hidden = false;
            elements.editPassword.focus();
            return;
        }

        isSaving = true;
        elements.editSaveBtn.disabled = true;
        setEditStatus('正在保存...', '');

        try {
            const response = await fetchWithTimeout(EDIT_API_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    password: password,
                    friends: editFriends,
                }),
            }, FETCH_TIMEOUT);

            const data = JSON.parse(await response.text());

            if (!response.ok || !data.success) {
                if (response.status === 401) {
                    storePassword('');
                    elements.editPasswordSection.hidden = false;
                    setEditStatus('密码错误，无法保存', 'error');
                    return;
                }
                throw new Error(data.error || '保存失败');
            }

            if (password) storePassword(password);
            setEditStatus('保存成功！', 'success');
            closeEditModal();
            fetchFriends(true);
        } catch (error) {
            setEditStatus(error.message || '保存失败', 'error');
        } finally {
            isSaving = false;
            elements.editSaveBtn.disabled = false;
        }
    }

    function init() {
        document.body.classList.add('page-enter');
        initStars();
        bindEvents();

        if (checkEnvironment()) {
            fetchFriends(false);
            startAutoRefresh();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
