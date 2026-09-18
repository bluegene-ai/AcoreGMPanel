/**
 * File: public/assets/js/modules/account.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - esc()
 *   - el()
 *   - urlWithServer()
 *   - bodyWithServer()
 *   - request()
 *   - flash()
 *   - showModal()
 *   - closeModal()
 *   - isPrivateIp()
 *   - fetchIpLocation()
 *   - fillIpLocations()
 *   - formatDateTime()
 *   - formatBanDuration()
 *   - reloadAccountTable()
 *   - doCharacters()
 *   - doSetGm()
 *   - doBan()
 *   - doUnban()
 *   - doChangePass()
 *   - doCreateAccount()
 *   - doSameIpAccounts()
 *   - promise()
 *   - startPolling()
 *   - showError()
 */

(function(){
	const root = document.body;
	if(!root || root.getAttribute('data-module') !== 'account') return;

	const panel = window.Panel || {};
	const api = typeof panel.api === 'function' ? panel.api.bind(panel) : null;
	const moduleLocaleFn = typeof panel.moduleLocale === 'function' ? panel.moduleLocale.bind(panel) : null;
	const moduleTranslator = typeof panel.createModuleTranslator === 'function'
		? panel.createModuleTranslator('account')
		: null;
	const capabilities = window.PANEL_CAPABILITIES || {};
	const hasCap = key => capabilities[key] !== false;

	function translate(path, fallback, replacements){
		const defaultValue = fallback ?? `modules.account.${path}`;
		let text;
		if(moduleLocaleFn){
			text = moduleLocaleFn('account', path, defaultValue);
		} else if(moduleTranslator){
			text = moduleTranslator(path, defaultValue);
		} else {
			text = defaultValue;
		}
		const sentinel = `modules.account.${path}`;
		if(typeof text === 'string' && text === sentinel && fallback){
			text = fallback;
		}
		if(typeof text === 'string' && replacements && typeof replacements === 'object'){
			Object.entries(replacements).forEach(([key, value]) => {
				const pattern = new RegExp(`:${key}(?![A-Za-z0-9_])`, 'g');
				text = text.replace(pattern, String(value ?? ''));
			});
		}
		return text;
	}

	function esc(value){
		return String(value ?? '').replace(/[&<>"']/g, ch => ({
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#39;'
		})[ch]);
	}

	function el(html){
		const host = document.createElement('div');
		host.innerHTML = html.trim();
		return host.firstElementChild;
	}

	const searchParams = new URLSearchParams(location.search);
	const currentServer = searchParams.get('server') || '';

	function urlWithServer(path){
		if(!currentServer) return path;
		return path + (path.includes('?') ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
	}

		function toCharacterUrl(guid){
			return urlWithServer(`/character/view?guid=${encodeURIComponent(guid)}`);
		}

		function toAccountUrl(id){
			return urlWithServer(`/account/view?id=${encodeURIComponent(id)}`);
		}

		function linkHtml(url, label){
			return `<a href="${esc(url)}">${esc(label)}</a>`;
		}

	function bodyWithServer(payload){
		if(!currentServer) return payload || {};
		const body = payload ? { ...payload } : {};
		if(body.server === undefined){
			body.server = currentServer;
		}
		return body;
	}

	async function request(path, options){
		const opts = options ? { ...options } : {};
		const method = (opts.method || 'GET').toUpperCase();
		const requestUrl = urlWithServer(path);
		if(api){
			if(method !== 'GET' && opts.body){
				opts.body = bodyWithServer(opts.body);
			}
			return api(requestUrl, opts);
		}
		const fetchOptions = { method };
		if(method !== 'GET'){
			fetchOptions.headers = { 'Content-Type': 'application/json' };
			fetchOptions.body = JSON.stringify(bodyWithServer(opts.body || {}));
		}
		try{
			const res = await fetch(requestUrl, fetchOptions);
			return await res.json();
		}catch(err){
			const hasMessage = err && err.message;
			return {
				success: false,
				message: hasMessage
					? translate('errors.request_failed_message', 'Request failed: :message', { message: err.message })
					: translate('errors.request_failed', 'Request failed')
			};
		}
	}

	const feedbackManager = panel.feedback && typeof panel.feedback.show === 'function' ? panel.feedback : null;
	const feedbackTarget = document.querySelector('#account-feedback');

	function flash(message, type = 'info', timeout = 3000){
		if(feedbackManager && feedbackTarget){
			const severity = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
			feedbackManager.show(feedbackTarget, severity, message, { duration: timeout });
			return;
		}
		let zone = document.querySelector('.flash-zone');
		if(!zone){
			zone = document.createElement('div');
			zone.className = 'flash-zone';
			document.body.appendChild(zone);
		}
		const node = document.createElement('div');
		node.className = `flash flash-${type}`;
		node.textContent = message;
		zone.appendChild(node);
		if(timeout){
			setTimeout(() => {
				if(node.parentNode === zone){
					node.remove();
				}
			}, timeout);
		}
	}

	function showModal(title, contentHtml){
		closeModal();
		const markup = `<div class="modal-backdrop"><div class="modal-panel"><header><h3>${esc(title)}</h3><button class="modal-close" aria-label="close">&times;</button></header><div class="modal-body"></div><div class="modal-footer modal-footer-right"></div></div></div>`;
		const wrapper = el(markup);
		wrapper.querySelector('.modal-body').innerHTML = contentHtml;
		// Only close via the explicit close button; do not close on backdrop clicks.
		// This prevents accidental closes while typing (e.g. IME / stray clicks).
		wrapper.addEventListener('click', event => {
			const target = event.target;
			if(target && target.classList && target.classList.contains('modal-close')){
				closeModal();
			}
		});
		wrapper.classList.add('active');
		document.body.appendChild(wrapper);
		return wrapper;
	}

	function closeModal(){
		const modal = document.querySelector('.modal-backdrop');
		if(modal){
			modal.remove();
		}
	}

	/**
	 * 在弹窗底部追加一个跳转链接（跨模块导航用，例如"到角色管理看该账号的角色"）。
	 * 已存在时只更新 href/label，避免重复叠加。
	 */
	function appendModalFooterLink(modal, path, label){
		if(!modal) return null;
		const footer = modal.querySelector('.modal-footer');
		if(!footer) return null;

		// window.APP_BASE is never published by the server; Panel.url() prepends the
		// real base path (data-app-base on <body>).
		const base = (window.Panel && typeof window.Panel.basePath === 'function')
			? window.Panel.basePath()
			: ((document.body && document.body.dataset ? document.body.dataset.appBase : '') || '').replace(/\/$/, '');
		const href = (window.Panel && typeof window.Panel.url === 'function')
			? window.Panel.url(urlWithServer(path))
			: (base + urlWithServer(path));

		let link = footer.querySelector('.js-modal-nav-link');
		if(!link){
			link = document.createElement('a');
			link.className = 'btn outline btn-sm js-modal-nav-link';
			footer.appendChild(link);
		}
		link.setAttribute('href', href);
		link.textContent = label;

		return link;
	}

	function isPrivateIp(ip){
		if(!ip) return false;
		const lower = ip.toLowerCase();
		if(ip.startsWith('10.') || ip.startsWith('192.168.') || ip.startsWith('127.')) return true;
		if(/^172\.(1[6-9]|2\d|3[01])\./.test(ip)) return true;
		if(lower === '::1' || lower.startsWith('fc') || lower.startsWith('fd')) return true;
		return false;
	}

	const ipGeoCache = new Map();

	async function fetchIpLocation(ip){
		if(!ip) return '-';
		if(ipGeoCache.has(ip)){
			return ipGeoCache.get(ip);
		}
		const promise = (async () => {
			if(isPrivateIp(ip)){
				return translate('ip_lookup.private', 'Private IP');
			}
			try{
				const res = await request(`/account/api/ip-location?ip=${encodeURIComponent(ip)}`);
				if(!res || !res.success){
					throw new Error(res && res.message ? res.message : translate('ip_lookup.failed', 'Lookup failed'));
				}
				return res.location || translate('ip_lookup.unknown', 'Unknown location');
			}catch(err){
				console.warn('[account] ip location lookup failed', ip, err);
				return translate('ip_lookup.failed', 'Lookup failed');
			}
		})();
		ipGeoCache.set(ip, promise.then(text => {
			ipGeoCache.set(ip, Promise.resolve(text));
			return text;
		}));
		return promise;
	}

	async function fillIpLocations(scope){
		const container = scope || document;
		const cells = Array.from(container.querySelectorAll('.ip-location[data-ip]'));
		if(!cells.length) return;
		const ipBuckets = new Map();
		cells.forEach(cell => {
			if(cell.dataset.locLoaded === '1') return;
			const ip = cell.getAttribute('data-ip') || '';
			if(!ip){
				cell.textContent = '-';
				cell.dataset.locLoaded = '1';
				return;
			}
			cell.textContent = translate('ip_lookup.loading', 'Looking up...');
			if(!ipBuckets.has(ip)){
				ipBuckets.set(ip, []);
			}
			ipBuckets.get(ip).push(cell);
		});
		for(const [ip, targets] of ipBuckets.entries()){
			try{
				const location = await fetchIpLocation(ip);
				targets.forEach(cell => {
					cell.textContent = location;
					cell.dataset.locLoaded = '1';
				});
			}catch(err){
				const failedText = translate('ip_lookup.failed', 'Lookup failed');
				targets.forEach(cell => {
					cell.textContent = failedText;
					cell.dataset.locLoaded = '1';
				});
			}
		}
	}

	function formatDateTime(timestamp){
		if(timestamp == null) return '';
		try{
			return new Date(timestamp * 1000).toLocaleString();
		}catch(_err){
			return '';
		}
	}

	function formatBanDuration(seconds){
		if(seconds == null || seconds < 0){
			return translate('ban.permanent', 'Permanent');
		}
		if(seconds === 0){
			return translate('ban.soon', 'Ends soon');
		}
		let remaining = seconds;
		const days = Math.floor(remaining / 86400);
		remaining %= 86400;
		const hours = Math.floor(remaining / 3600);
		remaining %= 3600;
		const minutes = Math.floor(remaining / 60);
		const parts = [];
		if(days > 0){
			parts.push(translate('ban.duration.day', ':value day', { value: days }));
		}
		if(hours > 0){
			parts.push(translate('ban.duration.hour', ':value hr', { value: hours }));
		}
		if(minutes > 0 && days === 0){
			parts.push(translate('ban.duration.minute', ':value min', { value: minutes }));
		}
		if(parts.length === 0){
			return translate('ban.under_minute', 'Under 1 minute');
		}
		const separator = translate('ban.separator', ' ');
		return parts.slice(0, 2).join(separator || ' ');
	}

	async function reloadAccountTable(){
		const table = document.querySelector('table.table');
		if(!table) return;
		const tbody = table.querySelector('tbody');
		if(!tbody) return;
		const params = new URLSearchParams(location.search);
		const type = params.get('search_type') || 'username';
		const value = params.get('search_value') || '';
		const online = params.get('online') || 'any';
		const ban = params.get('ban') || 'any';
		const loadAll = params.get('load_all') === '1';
		const excludeUsername = params.get('exclude_username') || '';
		const sort = params.get('sort') || '';
		const hasCriteria = loadAll || value || online !== 'any' || ban !== 'any' || excludeUsername;
		if(!hasCriteria){
			return;
		}
		try{
			const page = params.get('page') || '1';
			const query = new URLSearchParams({
				search_type: type,
				search_value: value,
				page
			});
			query.set('online', online || 'any');
			query.set('ban', ban || 'any');
			if(loadAll) query.set('load_all', '1');
			if(excludeUsername) query.set('exclude_username', excludeUsername);
			if(sort) query.set('sort', sort);
			const listUrl = `/account/api/list?${query.toString()}`;
			const res = await request(listUrl);
			if(!res || !res.success){
				console.warn('[account] failed to refresh account list');
				return;
			}
			const statusOnline = translate('status.online', 'Online');
			const statusOffline = translate('status.offline', 'Offline');
			const privateIpTitle = translate('feedback.private_ip_disabled', 'LAN IP lookup disabled');
			const canBulk = hasCap('ban') || hasCap('delete');
			const actionLabels = {
				chars: translate('actions.chars', 'Characters'),
				gm: translate('actions.gm', 'GM'),
				ban: translate('actions.ban', 'Ban'),
				unban: translate('actions.unban', 'Unban'),
				password: translate('actions.password', 'Reset password'),
				email: translate('actions.email', 'Email'),
				rename: translate('actions.rename', 'Rename'),
				sameIp: translate('actions.same_ip', 'Accounts on IP'),
				kick: translate('actions.kick', 'Kick'),
				delete: translate('actions.delete', 'Delete')
			};

			/**
			 * 行内操作条：与 account/index.php 的服务端渲染保持完全一致的归组规则 ——
			 * 每行只平铺"角色 / 封禁"两个高频操作，其余低频与危险操作收进"更多"菜单。
			 * 两边都输出 button.action[data-action]，由同一个 document 级委托处理。
			 */
			const rowActionBarHtml = (inline, menu) => {
				const button = (item) => {
					const attrs = [
						`class="${item.className}"`,
						`data-action="${item.action}"`
					];
					if(item.disabled) attrs.push('disabled');
					if(item.title) attrs.push(`title="${esc(item.title)}"`);
					return `<button ${attrs.join(' ')}>${esc(item.label)}</button>`;
				};

				const inlineHtml = inline.map(button).join('');
				const moreLabel = translate('actions.more', 'More');
				const menuHtml = menu.length
					? `<details class="row-menu">`
						+ `<summary class="btn-sm btn neutral outline" title="${esc(moreLabel)}">${esc(moreLabel)}</summary>`
						+ `<div class="row-menu__list">${menu.map(button).join('')}</div>`
						+ `</details>`
					: '';

				if(!inlineHtml && !menuHtml){
					return `<span class="muted small">${esc(translate('readonly.no_actions', 'No actions available'))}</span>`;
				}

				return `<div class="row-action-bar">${inlineHtml}${menuHtml}</div>`;
			};

			const rows = (res.items || []).map(row => {
				const lastIp = row.last_ip || '';
				const privateIp = isPrivateIp(lastIp);
				let statusHtml;
				if(row.ban){
					const remainingLabel = formatBanDuration(row.ban.remaining_seconds);
					const tooltip = translate('ban.tooltip', 'Reason: :reason\nStart: :start\nEnd: :end', {
						reason: row.ban.banreason || '-',
						start: formatDateTime(row.ban.bandate),
						end: row.ban.permanent ? translate('ban.no_end', 'Permanent') : formatDateTime(row.ban.unbandate)
					});
					const badgeLabel = translate('ban.badge', 'Banned (:duration)', { duration: remainingLabel });
					statusHtml = `<span class="badge status-banned" title="${esc(tooltip)}">${esc(badgeLabel)}</span>`;
				} else if(row.online){
					statusHtml = `<span class="badge status-online">${esc(statusOnline)}</span>`;
				} else {
					statusHtml = `<span class="badge status-offline">${esc(statusOffline)}</span>`;
				}
				const actionButtons = {
					inline: [
						hasCap('characters') ? { action: 'chars', className: 'btn-sm btn info action', label: actionLabels.chars } : null
					].filter(Boolean),
					menu: [
						hasCap('gm') ? { action: 'gm', className: 'btn-sm btn warn action', label: actionLabels.gm } : null,
						hasCap('ban') ? { action: 'ban', className: 'btn-sm btn danger action', label: actionLabels.ban } : null,
						hasCap('ban') ? { action: 'unban', className: 'btn-sm btn success action', label: actionLabels.unban } : null,
						hasCap('password') ? { action: 'pass', className: 'btn-sm btn info outline action', label: actionLabels.password } : null,
						hasCap('update') ? { action: 'email', className: 'btn-sm btn neutral action', label: actionLabels.email } : null,
						hasCap('update') ? { action: 'rename', className: 'btn-sm btn neutral outline action', label: actionLabels.rename } : null,
						hasCap('ip') ? { action: 'ip-accounts', className: 'btn-sm btn neutral action', label: actionLabels.sameIp, disabled: privateIp, title: privateIp ? privateIpTitle : '' } : null,
						hasCap('kick') ? { action: 'kick', className: 'btn-sm btn outline danger action', label: actionLabels.kick } : null,
						hasCap('delete') ? { action: 'delete', className: 'btn-sm btn danger action', label: actionLabels.delete } : null
					].filter(Boolean)
				};
				const idValue = row.id ?? '';
				const usernameValue = row.username || '';
				const gmValue = row.gmlevel != null ? row.gmlevel : '-';
				const gmData = row.gmlevel != null ? row.gmlevel : 0;
				const lastLogin = row.last_login || '-';
				const lastIpCell = lastIp || '-';
				const selectCell = canBulk
					? `<td><input type="checkbox" class="js-account-select" value="${esc(idValue)}" aria-label="select"></td>`
					: '';
				const actionsHtml = rowActionBarHtml(actionButtons.inline, actionButtons.menu);
				return `<tr data-id="${esc(idValue)}" data-username="${esc(usernameValue)}" data-gm="${esc(gmData)}" data-last-ip="${esc(lastIp)}">`
					+ selectCell
					+ `<td>${esc(idValue)}</td>`
					+ `<td>${linkHtml(toAccountUrl(idValue), usernameValue || ('#' + idValue))}</td>`
					+ `<td>${esc(gmValue)}</td>`
					+ `<td>${statusHtml}</td>`
					+ `<td>${esc(lastLogin)}</td>`
					+ `<td>${esc(lastIpCell)}</td>`
					+ `<td class="ip-location" data-ip="${esc(lastIp)}">-</td>`
					+ `<td class="account-table__actions-cell">${actionsHtml}</td>`
					+ `</tr>`;
			}).join('');
			const emptyText = translate('feedback.empty', 'No results');
			tbody.innerHTML = rows || `<tr><td colspan="${canBulk ? 9 : 8}" class="account-table__empty-cell">${esc(emptyText)}</td></tr>`;
			fillIpLocations(tbody);
		}catch(err){
			console.error('[account] failed to reload account table', err);
		}
	}

	function selectedAccountIds(){
		return Array.from(document.querySelectorAll('input.js-account-select:checked'))
			.map(el => parseInt(el.value, 10))
			.filter(v => Number.isFinite(v) && v > 0);
	}
	async function doDeleteAccount(id, username){
		const confirmMsg = translate('delete.confirm', 'Delete this account?');
		if(!confirm(confirmMsg)) return;
		const res = await request('/account/api/delete', { method: 'POST', body: { id } });
		if(res && res.success){
			flash(translate('delete.success', 'Deleted'), 'success');
			reloadAccountTable();
			return;
		}
		flash((res && res.message) ? res.message : translate('errors.request_failed', 'Request failed'), 'error');
	}

	async function doBulk(action){
		const ids = selectedAccountIds();
		if(!ids.length){
			flash(translate('bulk.no_selection', 'Please select at least one item'), 'error');
			return;
		}
		if(action === 'delete'){
			const confirmMsg = translate('delete.confirm', 'Delete selected accounts?');
			if(!confirm(confirmMsg)) return;
		}
		let hours = 0;
		let reason = '';
		if(action === 'ban'){
			hours = parseInt(prompt(translate('ban.prompt_hours', 'Ban hours (0=permanent):'), '0') || '0', 10);
			if(!Number.isFinite(hours) || hours < 0){
				flash(translate('ban.error_hours', 'Invalid hours'), 'error');
				return;
			}
			reason = prompt(translate('ban.prompt_reason', 'Reason:'), translate('ban.default_reason', 'Panel ban')) || '';
		}
		if(action === 'unban'){
			if(!confirm(translate('ban.confirm_unban', 'Unban selected accounts?'))) return;
		}
		const res = await request('/account/api/bulk', { method: 'POST', body: { action, ids, hours, reason } });
		if(res && res.success){
			flash(`OK: ${res.ok}/${res.requested}`, 'success');
			reloadAccountTable();
			return;
		}
		const failed = res && typeof res.failed === 'number' ? res.failed : null;
		flash(failed ? `Failed: ${failed}` : ((res && res.message) ? res.message : 'Failed'), 'error');
	}

	async function doCharacters(id, username){
		const title = translate('characters.title', 'Character list - :name', { name: username });
		const loadingText = translate('characters.loading', 'Loading...');
		const modal = showModal(title, `<div class="text-muted">${esc(loadingText)}</div>`);
		modal.__pollTimer = null;
		modal.__pollStop = false;
		try{
			const res = await request(`/account/api/characters?id=${encodeURIComponent(id)}`);
			if(!res || !res.success){
				throw new Error(res && res.message ? res.message : translate('characters.fetch_error', 'Failed to load characters'));
			}
			const tableLabels = {
				guid: translate('characters.table.guid', 'GUID'),
				name: translate('characters.table.name', 'Name'),
				level: translate('characters.table.level', 'Level'),
				status: translate('characters.table.status', 'Status')
			};
			const kickLabel = translate('characters.kick_button', 'Kick offline');
			const offlineTooltip = translate('characters.offline_tooltip', 'Character offline, cannot kick');
			const onlineLabel = translate('status.online', 'Online');
			const offlineLabel = translate('status.offline', 'Offline');
			const rows = (res.items || []).map(character => {
				const online = !!character.online;
				const guid = character.guid;
				const rawCharacterUrl = toCharacterUrl(guid);
				const toCharacter = (window.Panel && typeof window.Panel.url === 'function')
					? window.Panel.url(rawCharacterUrl)
					: rawCharacterUrl;
				const statusTag = online
					? `<span class="tag status-online-alt">${esc(onlineLabel)}</span>`
					: `<span class="tag status-offline">${esc(offlineLabel)}</span>`;
				let kickButton = '';
				if(hasCap('kick')){
					const buttonAttrs = [
						'class="btn-sm btn outline danger action-kick-char"',
						`data-char="${esc(character.name)}"`
					];
					if(!online){
						buttonAttrs.push('disabled');
						buttonAttrs.push(`title="${esc(offlineTooltip)}"`);
					}
					kickButton = `<button ${buttonAttrs.join(' ')}>${esc(kickLabel)}</button>`;
				}
				return `<tr data-guid="${esc(character.guid)}" data-name="${esc(character.name)}" data-online="${online ? 1 : 0}">`
					+ `<td>${esc(character.guid)}</td>`
					+ `<td><a href="${esc(toCharacter)}"><span data-class-id="${esc(character.class)}">${esc(character.name)}</span></a></td>`
					+ `<td>${esc(character.level)}</td>`
					+ `<td>${statusTag}${kickButton ? ` ${kickButton}` : ''}</td>`
					+ `</tr>`;
			}).join('');
			const emptyRow = `<tr><td colspan="4" class="account-table__empty-cell">${esc(translate('characters.empty', 'No characters'))}</td></tr>`;
			modal.querySelector('.modal-body').innerHTML = `<table class="table modal-table-left"><thead><tr>`
				+ `<th>${esc(tableLabels.guid)}</th>`
				+ `<th>${esc(tableLabels.name)}</th>`
				+ `<th>${esc(tableLabels.level)}</th>`
				+ `<th>${esc(tableLabels.status)}</th>`
				+ `</tr></thead><tbody>${rows || emptyRow}</tbody></table>`;
			// 弹窗内提供"到角色管理看该账号全部角色"的入口，避免用户看完弹窗还要手动去搜
			const sameAccountLabel = translate('characters.view_all', 'View all in character management');
			appendModalFooterLink(modal, `/character?account=${encodeURIComponent(username)}&load_all=1`, sameAccountLabel);
			if(res.ban){
				const badgeLabel = translate('characters.ban_badge', 'Banned');
				const remain = res.ban.permanent
					? translate('ban.permanent', 'Permanent')
					: formatBanDuration(res.ban.remaining_seconds);
				const tooltip = translate('ban.tooltip', 'Reason: :reason\nStart: :start\nEnd: :end', {
					reason: res.ban.banreason || '-',
					start: formatDateTime(res.ban.bandate),
					end: res.ban.permanent ? translate('ban.no_end', 'Permanent') : formatDateTime(res.ban.unbandate)
				});
				const header = modal.querySelector('header h3');
				if(header){
					const badge = document.createElement('span');
					badge.className = 'account-characters__ban-meta';
					badge.innerHTML = `<span class="tag status-banned">${esc(badgeLabel)}</span> <span class="small muted" title="${esc(tooltip)}">${esc(remain)}</span>`;
					header.appendChild(badge);
				}
			}
			if(window.GameMetaColorize){
				window.GameMetaColorize();
			}
			modal.addEventListener('click', async event => {
				const btn = event.target.closest('button.action-kick-char');
				if(!btn || btn.disabled) return;
				const charName = btn.getAttribute('data-char');
				if(!charName) return;
				const confirmMsg = translate('characters.confirm_kick', 'Kick character :name?', { name: charName });
				if(!confirm(confirmMsg)) return;
				try{
					const response = await request('/account/api/kick', { method: 'POST', body: { player: charName } });
					if(response && response.success){
						flash(translate('characters.kick_success', 'Kick command dispatched: :name', { name: charName }), 'success');
					}else{
						flash(translate('characters.kick_failed', 'Kick failed: :message', { message: response && response.message ? response.message : '' }), 'error');
					}
				}catch(err){
					flash(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }), 'error');
				}
			});
			const startPolling = () => {
				if(modal.__pollTimer) return;
				const intervalMs = 5000;
				const poll = async () => {
					if(modal.__pollStop) return;
					if(!document.body.contains(modal)){
						modal.__pollStop = true;
						return;
					}
					try{
						const statusRes = await request(`/account/api/characters-status?id=${encodeURIComponent(id)}`);
						if(!statusRes || !statusRes.success) throw new Error(statusRes && statusRes.message ? statusRes.message : 'status_failed');
						const statuses = statusRes.statuses || {};
						const table = modal.querySelector('table');
						if(!table) return;
						table.querySelectorAll('tbody tr').forEach(tr => {
							const guidCell = tr.querySelector('td');
							if(!guidCell) return;
							const guid = parseInt(guidCell.textContent.trim(), 10);
							if(!guid || !(guid in statuses)) return;
							const state = statuses[guid];
							const statusCell = tr.querySelector('td:nth-child(4)');
							if(!statusCell) return;
							const kickBtn = statusCell.querySelector('button.action-kick-char');
							const currentlyOnline = statusCell.querySelector('.status-online-alt') != null;
							const nowOnline = !!state.online;
							if(currentlyOnline === nowOnline){
								if(!nowOnline && kickBtn && !kickBtn.disabled){
									kickBtn.disabled = true;
									kickBtn.setAttribute('title', offlineTooltip);
								}
								return;
							}
							let badge = statusCell.querySelector('.tag');
							if(!badge){
								badge = document.createElement('span');
								statusCell.prepend(badge);
							}
							if(nowOnline){
								badge.className = 'tag status-online-alt';
								badge.textContent = onlineLabel;
								if(kickBtn){
									kickBtn.disabled = false;
									kickBtn.removeAttribute('title');
								}
							}else{
								badge.className = 'tag status-offline';
								badge.textContent = offlineLabel;
								if(kickBtn){
									kickBtn.disabled = true;
									kickBtn.setAttribute('title', offlineTooltip);
								}
							}
						});
					}catch(err){
						if(!modal.__pollBackoff){
							modal.__pollBackoff = true;
							clearInterval(modal.__pollTimer);
							modal.__pollTimer = setInterval(poll, 10000);
						}
					}
				};
				modal.__pollTimer = setInterval(poll, intervalMs);
				poll();
			};
			startPolling();
			const observer = new MutationObserver(() => {
				if(!document.body.contains(modal)){
					modal.__pollStop = true;
					if(modal.__pollTimer) clearInterval(modal.__pollTimer);
					observer.disconnect();
				}
			});
			observer.observe(document.body, { childList: true });
		}catch(err){
			modal.querySelector('.modal-body').innerHTML = `<div class="flash">${esc(translate('characters.fetch_failed', 'Failed to load characters: :message', { message: err.message }))}</div>`;
		}
	}

	async function doSetGm(id, username, currentLevel){
		const promptText = translate('gm.prompt_level', 'Set GM level (0-6):');
		const value = prompt(promptText, currentLevel || 0);
		if(value === null) return;
		const gmLevel = parseInt(value, 10);
		if(Number.isNaN(gmLevel) || gmLevel < 0 || gmLevel > 6){
			flash(translate('gm.error_level', 'Invalid GM level'), 'error');
			return;
		}
		try{
			const res = await request('/account/api/set-gm', { method: 'POST', body: { id, gm: gmLevel } });
			if(res && res.success){
				flash(translate('gm.success', 'GM level updated'), 'success');
				location.reload();
			}else{
				flash(translate('gm.failure', 'Failed to update GM level'), 'error');
			}
		}catch(err){
			flash(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }), 'error');
		}
	}

	async function doBan(id, username){
		const hoursPrompt = translate('ban.prompt_hours', 'Ban duration in hours (0 = permanent):');
		const hoursRaw = prompt(hoursPrompt, '24');
		if(hoursRaw === null) return;
		const hours = parseInt(hoursRaw, 10);
		if(Number.isNaN(hours) || hours < 0){
			flash(translate('ban.error_hours', 'Invalid duration'), 'error');
			return;
		}
		const reasonPrompt = translate('ban.prompt_reason', 'Ban reason:');
		const defaultReason = translate('ban.default_reason', 'Panel ban');
		const reason = (prompt(reasonPrompt, defaultReason) || defaultReason).trim();
		try{
			const res = await request('/account/api/ban', { method: 'POST', body: { id, hours, reason } });
			if(res && res.success){
				flash(translate('ban.success', 'Account banned successfully'), 'success');
				reloadAccountTable();
			}else{
				flash(translate('ban.failure', 'Failed to ban account'), 'error');
			}
		}catch(err){
			flash(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }), 'error');
		}
	}

	async function doUnban(id){
		const confirmMsg = translate('ban.confirm_unban', 'Unban this account?');
		if(!confirm(confirmMsg)) return;
		try{
			const res = await request('/account/api/unban', { method: 'POST', body: { id } });
			if(res && res.success){
				flash(translate('ban.unban_success', 'Account unbanned'), 'success');
				reloadAccountTable();
			}else{
				flash(translate('ban.unban_failure', 'Failed to unban account'), 'error');
			}
		}catch(err){
			flash(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }), 'error');
		}
	}

	async function doChangePass(id, username){
		const newPrompt = translate('password.prompt_new', 'Enter new password (min 8 chars):');
		const password = prompt(newPrompt);
		if(password === null) return;
		if(password === ''){
			flash(translate('password.error_empty', 'Password cannot be empty'), 'error');
			return;
		}
		if(password.length < 8){
			flash(translate('password.error_length', 'Password must be at least 8 characters'), 'error');
			return;
		}
		const confirmPrompt = translate('password.prompt_confirm', 'Re-enter new password:');
		const repeat = prompt(confirmPrompt);
		if(repeat === null) return;
		if(password !== repeat){
			flash(translate('password.error_mismatch', 'Passwords do not match'), 'error');
			return;
		}
		try{
			const res = await request('/account/api/change-password', { method: 'POST', body: { id, username, password } });
			if(res && res.success){
				flash(translate('password.success', 'Password updated successfully (previous sessions invalidated)'), 'success');
			}else{
				const message = res && res.message ? res.message : translate('password.failure_generic', 'Unknown error');
				flash(translate('password.failure', 'Failed to change password: :message', { message }), 'error');
			}
		}catch(err){
			flash(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }), 'error');
		}
	}

	async function doUpdateEmail(id, username){
		const title = translate('email.title', 'Update email - :name', { name: username });
		const label = translate('email.labels.email', 'Email');
		const placeholder = translate('email.placeholders.email', 'example@domain.com');
		const formHtml = `
			<form class="form account-email-form">
				<div class="form-field">
					<label>${esc(label)}</label>
					<input type="email" name="email" placeholder="${esc(placeholder)}" maxlength="255" required>
				</div>
				<div class="form-error account-form-error" hidden></div>
			</form>
		`;
		const modal = showModal(title, formHtml);
		const footer = modal.querySelector('.modal-footer');
		if(footer){ footer.innerHTML = ''; }
		const cancelText = translate('email.actions.cancel', 'Cancel');
		const submitText = translate('email.actions.submit', 'Save');
		const cancelBtn = el(`<button type="button" class="btn outline">${esc(cancelText)}</button>`);
		const submitBtn = el(`<button type="button" class="btn">${esc(submitText)}</button>`);
		cancelBtn.addEventListener('click', () => closeModal());
		footer.appendChild(cancelBtn);
		footer.appendChild(submitBtn);
		const form = modal.querySelector('form');
		const errorBox = modal.querySelector('.form-error');
		setTimeout(() => {
			const input = form ? form.querySelector('input[name="email"]') : null;
			if(input) input.focus();
		}, 50);
		const showError = message => {
			if(!errorBox) return;
			if(message){
				errorBox.textContent = message;
				errorBox.hidden = false;
			}else{
				errorBox.textContent = '';
				errorBox.hidden = true;
			}
		};
		const submit = async () => {
			if(submitBtn.disabled) return;
			const data = new FormData(form);
			const email = (data.get('email') || '').toString().trim();
			if(!email || !email.includes('@')){
				showError(translate('email.invalid', 'Invalid email'));
				return;
			}
			showError('');
			submitBtn.disabled = true;
			cancelBtn.disabled = true;
			try{
				const res = await request('/account/api/update-email', { method: 'POST', body: { id, email } });
				if(!res || !res.success){
					showError(res && res.message ? res.message : translate('email.errors.failed', 'Failed to update email'));
					submitBtn.disabled = false;
					cancelBtn.disabled = false;
					return;
				}
				flash(translate('email.success', 'Email updated'), 'success');
				closeModal();
				reloadAccountTable();
			}catch(err){
				showError(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }));
				submitBtn.disabled = false;
				cancelBtn.disabled = false;
			}
		};
		submitBtn.addEventListener('click', submit);
		form.addEventListener('submit', event => { event.preventDefault(); submit(); });
	}

	async function doRenameAccount(id, username){
		const title = translate('rename.title', 'Rename account - :name', { name: username });
		const userLabel = translate('rename.labels.username', 'New username');
		const passLabel = translate('rename.labels.password', 'New password');
		const passConfirmLabel = translate('rename.labels.password_confirm', 'Confirm password');
		const formHtml = `
			<form class="form account-rename-form">
				<div class="form-field">
					<label>${esc(userLabel)}</label>
					<input type="text" name="username" maxlength="20" required>
				</div>
				<div class="form-field">
					<label>${esc(passLabel)}</label>
					<input type="password" name="password" minlength="8" required>
				</div>
				<div class="form-field">
					<label>${esc(passConfirmLabel)}</label>
					<input type="password" name="password_confirm" minlength="8" required>
				</div>
				<div class="form-error account-form-error" hidden></div>
			</form>
		`;
		const modal = showModal(title, formHtml);
		const footer = modal.querySelector('.modal-footer');
		if(footer){ footer.innerHTML = ''; }
		const cancelText = translate('rename.actions.cancel', 'Cancel');
		const submitText = translate('rename.actions.submit', 'Save');
		const cancelBtn = el(`<button type="button" class="btn outline">${esc(cancelText)}</button>`);
		const submitBtn = el(`<button type="button" class="btn">${esc(submitText)}</button>`);
		cancelBtn.addEventListener('click', () => closeModal());
		footer.appendChild(cancelBtn);
		footer.appendChild(submitBtn);
		const form = modal.querySelector('form');
		const errorBox = modal.querySelector('.form-error');
		setTimeout(() => {
			const input = form ? form.querySelector('input[name="username"]') : null;
			if(input) input.focus();
		}, 50);
		const showError = message => {
			if(!errorBox) return;
			if(message){
				errorBox.textContent = message;
				errorBox.hidden = false;
			}else{
				errorBox.textContent = '';
				errorBox.hidden = true;
			}
		};
		const submit = async () => {
			if(submitBtn.disabled) return;
			const data = new FormData(form);
			const newUsername = (data.get('username') || '').toString().trim();
			const password = (data.get('password') || '').toString();
			const confirmPassword = (data.get('password_confirm') || '').toString();
			if(!newUsername || newUsername.length > 20){
				showError(translate('rename.invalid_username', 'Invalid username'));
				return;
			}
			if(password.length < 8){
				showError(translate('rename.invalid_password', 'Password must be at least 8 characters'));
				return;
			}
			if(password !== confirmPassword){
				showError(translate('rename.password_mismatch', 'Passwords do not match'));
				return;
			}
			showError('');
			submitBtn.disabled = true;
			cancelBtn.disabled = true;
			try{
				const res = await request('/account/api/update-username', { method: 'POST', body: { id, username: newUsername, password } });
				if(!res || !res.success){
					showError(res && res.message ? res.message : translate('rename.errors.failed', 'Failed to rename account'));
					submitBtn.disabled = false;
					cancelBtn.disabled = false;
					return;
				}
				flash(translate('rename.success', 'Username updated (sessions invalidated)'), 'success');
				closeModal();
				reloadAccountTable();
			}catch(err){
				showError(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }));
				submitBtn.disabled = false;
				cancelBtn.disabled = false;
			}
		};
		submitBtn.addEventListener('click', submit);
		form.addEventListener('submit', event => { event.preventDefault(); submit(); });
	}

	function doCreateAccount(){
		const title = translate('create.title', 'Create account');
		const usernameLabel = translate('create.labels.username', 'Username');
		const passwordLabel = translate('create.labels.password', 'Password');
		const passwordConfirmLabel = translate('create.labels.password_confirm', 'Confirm password');
		const emailLabel = translate('create.labels.email', 'Email (optional)');
		const gmLabel = translate('create.labels.gmlevel', 'GM level');
		const requiredMark = '<span class="required">*</span>';
		const usernamePlaceholder = translate('create.placeholders.username', 'Case insensitive');
		const passwordPlaceholder = translate('create.placeholders.password', 'At least 8 characters');
		const passwordConfirmPlaceholder = translate('create.placeholders.password_confirm', 'Re-enter password');
		const emailPlaceholder = translate('create.placeholders.email', 'example@domain.com');
		const gmPlayer = translate('create.gm_options.player', '0 - Player');
		const gmOne = translate('create.gm_options.one', '1');
		const gmTwo = translate('create.gm_options.two', '2');
		const gmThree = translate('create.gm_options.three', '3');
		const formHtml = `
			<form class="form account-create-form">
				<div class="form-field">
					<label>${esc(usernameLabel)} ${requiredMark}</label>
					<input type="text" name="username" placeholder="${esc(usernamePlaceholder)}" maxlength="32" required>
				</div>
				<div class="form-field">
					<label>${esc(passwordLabel)} ${requiredMark}</label>
					<input type="password" name="password" placeholder="${esc(passwordPlaceholder)}" minlength="8" required>
				</div>
				<div class="form-field">
					<label>${esc(passwordConfirmLabel)} ${requiredMark}</label>
					<input type="password" name="password_confirm" placeholder="${esc(passwordConfirmPlaceholder)}" minlength="8" required>
				</div>
				<div class="form-field">
					<label>${esc(emailLabel)}</label>
					<input type="email" name="email" placeholder="${esc(emailPlaceholder)}" maxlength="64">
				</div>
				<div class="form-field">
					<label>${esc(gmLabel)}</label>
					<select name="gmlevel">
						<option value="0" selected>${esc(gmPlayer)}</option>
						<option value="1">${esc(gmOne)}</option>
						<option value="2">${esc(gmTwo)}</option>
						<option value="3">${esc(gmThree)}</option>
					</select>
				</div>
				<div class="form-error account-form-error" hidden></div>
			</form>
		`;
		const modal = showModal(title, formHtml);
		const footer = modal.querySelector('.modal-footer');
		if(footer){
			footer.innerHTML = '';
		}
		const cancelText = translate('create.actions.cancel', 'Cancel');
		const submitText = translate('create.actions.submit', 'Create');
		const cancelBtn = el(`<button type="button" class="btn outline">${esc(cancelText)}</button>`);
		const submitBtn = el(`<button type="button" class="btn">${esc(submitText)}</button>`);
		cancelBtn.addEventListener('click', () => closeModal());
		footer.appendChild(cancelBtn);
		footer.appendChild(submitBtn);

		const form = modal.querySelector('form');
		const errorBox = modal.querySelector('.form-error');
		setTimeout(() => {
			const input = form ? form.querySelector('input[name="username"]') : null;
			if(input) input.focus();
		}, 50);

		const showError = message => {
			if(!errorBox) return;
			if(message){
				errorBox.textContent = message;
				errorBox.hidden = false;
			}else{
				errorBox.textContent = '';
				errorBox.hidden = true;
			}
		};

		const submit = async event => {
			if(event) event.preventDefault();
			if(submitBtn.disabled) return;
			const data = new FormData(form);
			const username = (data.get('username') || '').toString().trim();
			const password = (data.get('password') || '').toString();
			const confirmPassword = (data.get('password_confirm') || '').toString();
			const email = (data.get('email') || '').toString().trim();
			const gmlevel = parseInt((data.get('gmlevel') || '0').toString(), 10) || 0;
			if(username === ''){
				showError(translate('create.errors.username_required', 'Please enter a username'));
				return;
			}
			if(password.length < 8){
				showError(translate('create.errors.password_length', 'Password must be at least 8 characters'));
				return;
			}
			if(password !== confirmPassword){
				showError(translate('create.errors.password_mismatch', 'Passwords do not match'));
				return;
			}
			showError('');
			submitBtn.disabled = true;
			cancelBtn.disabled = true;
			const pendingText = translate('create.status.submitting', 'Creating...');
			const originalText = submitBtn.textContent;
			submitBtn.textContent = pendingText;
			try{
				const payload = { username, password, password_confirm: confirmPassword, email, gmlevel };
				const res = await request('/account/api/create', { method: 'POST', body: payload });
				if(!res || !res.success){
					showError(res && res.message ? res.message : translate('create.errors.request_generic', 'Creation failed'));
					submitBtn.disabled = false;
					cancelBtn.disabled = false;
					submitBtn.textContent = originalText;
					return;
				}
				flash(translate('create.success', 'Account created: :name', { name: username }), 'success');
				closeModal();
				const searchPath = urlWithServer(`/account?search_type=username&search_value=${encodeURIComponent(username)}`);
				if(window.Panel && typeof window.Panel.url === 'function'){
					location.href = window.Panel.url(searchPath);
				}else{
					location.href = searchPath;
				}
			}catch(err){
				showError(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }));
				submitBtn.disabled = false;
				cancelBtn.disabled = false;
				submitBtn.textContent = originalText;
			}
		};

		submitBtn.addEventListener('click', submit);
		form.addEventListener('submit', submit);
	}

	async function doSameIpAccounts(accountId, username, ip){
		const cleanIp = (ip || '').trim();
		if(!cleanIp){
			flash(translate('same_ip.missing_ip', 'No last IP available for this account'), 'info');
			return;
		}
		const title = translate('same_ip.title', 'Accounts on IP - :ip', { ip: cleanIp });
		const modal = showModal(title, `<div class="text-muted">${esc(translate('same_ip.loading', 'Loading...'))}</div>`);
		try{
			const res = await request(`/account/api/ip-accounts?ip=${encodeURIComponent(cleanIp)}`);
			if(!res || !res.success){
				throw new Error(res && res.message ? res.message : translate('same_ip.error_generic', 'Failed to query accounts'));
			}
			const items = Array.isArray(res.items) ? res.items : [];
			if(!items.length){
				modal.querySelector('.modal-body').innerHTML = `<div class="empty">${esc(translate('same_ip.empty', 'No other accounts found for this IP'))}</div>`;
				return;
			}
			const columns = {
				id: translate('same_ip.table.id', 'ID'),
				username: translate('same_ip.table.username', 'Username'),
				gm: translate('same_ip.table.gm', 'GM'),
				status: translate('same_ip.table.status', 'Status'),
				lastLogin: translate('same_ip.table.last_login', 'Last login'),
				ipLocation: translate('same_ip.table.ip_location', 'IP location')
			};
			const onlineLabel = translate('status.online', 'Online');
			const offlineLabel = translate('status.offline', 'Offline');
			const bannedLabel = translate('same_ip.status.banned', 'Banned');
			const rows = items.map(item => {
				let statusHtml;
				if(item.ban){
					const remaining = item.ban.remaining_seconds < 0
						? translate('ban.permanent', 'Permanent')
						: formatBanDuration(item.ban.remaining_seconds);
					const remainText = translate('same_ip.status.remaining', 'Remaining: :value', { value: remaining });
					statusHtml = `<span class="tag status-banned">${esc(bannedLabel)}</span> <span class="small muted">${esc(remainText)}</span>`;
				}else{
					statusHtml = item.online
						? `<span class="tag status-online-alt">${esc(onlineLabel)}</span>`
						: `<span class="tag status-offline">${esc(offlineLabel)}</span>`;
				}
				const gm = item.gmlevel != null ? item.gmlevel : '-';
				const lastLogin = item.last_login || '-';
				const ipText = item.last_ip || cleanIp;
				return `<tr data-id="${esc(item.id)}">`
					+ `<td>${esc(item.id)}</td>`
					+ `<td>${linkHtml(toAccountUrl(item.id), item.username || ('#' + item.id))}</td>`
					+ `<td>${esc(gm)}</td>`
					+ `<td>${statusHtml}</td>`
					+ `<td>${esc(lastLogin)}</td>`
					+ `<td class="ip-location" data-ip="${esc(ipText)}">-</td>`
					+ `</tr>`;
			}).join('');
			modal.querySelector('.modal-body').innerHTML = `<table class="table modal-table-left">`
				+ `<thead><tr>`
				+ `<th>${esc(columns.id)}</th>`
				+ `<th>${esc(columns.username)}</th>`
				+ `<th>${esc(columns.gm)}</th>`
				+ `<th>${esc(columns.status)}</th>`
				+ `<th>${esc(columns.lastLogin)}</th>`
				+ `<th>${esc(columns.ipLocation)}</th>`
				+ `</tr></thead>`
				+ `<tbody>${rows}</tbody>`
				+ `</table>`;
			fillIpLocations(modal);
		}catch(err){
			modal.querySelector('.modal-body').innerHTML = `<div class="flash">${esc(translate('same_ip.error', 'Failed to query accounts: :message', { message: err.message }))}</div>`;
		}
	}

	document.addEventListener('click', event => {
		const button = event.target.closest('button.action');
		if(!button || button.disabled) return;
		const action = button.dataset.action;
		if(action === 'create-account'){
			doCreateAccount();
			return;
		}
		const row = button.closest('tr');
		if(!row) return;
		const id = parseInt(row.dataset.id, 10);
		const username = row.dataset.username;
		const gmLevel = row.dataset.gm;
		const lastIp = row.dataset.lastIp || '';
		switch(action){
			case 'chars':
				doCharacters(id, username);
				break;
			case 'gm':
				doSetGm(id, username, gmLevel);
				break;
			case 'ban':
				doBan(id, username);
				break;
			case 'unban':
				doUnban(id);
				break;
			case 'pass':
				doChangePass(id, username);
				break;
			case 'email':
				doUpdateEmail(id, username);
				break;
			case 'rename':
				doRenameAccount(id, username);
				break;
			case 'ip-accounts':
				doSameIpAccounts(id, username, lastIp);
				break;
			case 'delete':
				doDeleteAccount(id, username);
				break;
			default:
				break;
		}
	});

	document.addEventListener('click', event => {
		const btn = event.target.closest('button.js-account-bulk');
		if(!btn || btn.disabled) return;
		const action = btn.getAttribute('data-bulk') || '';
		if(!action) return;
		doBulk(action);
	});

	document.addEventListener('change', event => {
		const target = event.target;
		if(!(target instanceof HTMLInputElement)) return;
		if(target.classList.contains('js-account-select-all')){
			const checked = target.checked;
			document.querySelectorAll('input.js-account-select-all').forEach(el => { el.checked = checked; });
			document.querySelectorAll('input.js-account-select').forEach(el => { el.checked = checked; });
			return;
		}
		if(target.classList.contains('js-account-select')){
			const all = Array.from(document.querySelectorAll('input.js-account-select'));
			const checked = all.filter(el => el.checked);
			const allChecked = all.length > 0 && checked.length === all.length;
			document.querySelectorAll('input.js-account-select-all').forEach(el => { el.checked = allChecked; });
		}
	});

	/**
	 * 行内"更多"菜单的浮层定位。
	 *
	 * 原因：.row-menu__list 是 position:absolute，而外层容器链上存在
	 * .app-shell-panel{overflow:hidden}（还有 .app-shell-panel > *{z-index:1}），
	 * 因此菜单会被页面框架直接裁掉——表格越靠下越明显。
	 *
	 * 解法：打开时把菜单临时挂到 <body> 下并改用 position:fixed，按触发按钮的
	 * 位置计算坐标；关闭（toggle / 点击别处 / Esc）时放回 <details> 内。这样不需要
	 * 改动 overflow，面板的装饰性光晕仍按原样裁剪。
	 *
	 * 目前只有账号页使用 .row-menu（服务端渲染 + 本模块渲染各一处），所以先放在
	 * 本模块内；若后续其他模块也用，再提取到 panel.js 作为公共能力。
	 */
	function isRowMenuListVisible(list){
		return !!list && !list.hidden && list.style.display !== 'none';
	}

	function placeRowMenu(details, list, measured){
		const summary = details.querySelector('summary');
		// The list may already have been moved to <body> (that is the whole point
		// of the portal), so accept it as an argument instead of re-querying the
		// <details> — otherwise this silently returns without positioning.
		if(!list) list = details.querySelector('.row-menu__list');
		if(!summary || !list) return;

		const rect = summary.getBoundingClientRect();
		const gap = 6;
		const margin = 8;

		const menuWidth = (measured && measured.width) || list.offsetWidth || 160;
		const menuHeight = (measured && measured.height) || list.offsetHeight || 0;

		// 右对齐触发按钮；横向越界时贴边
		let left = rect.right - menuWidth;
		left = Math.max(margin, Math.min(left, window.innerWidth - menuWidth - margin));

		// 下方空间不足则翻到按钮上方
		const spaceBelow = window.innerHeight - rect.bottom;
		const openUp = spaceBelow < menuHeight + gap + margin && rect.top > spaceBelow;
		let top = openUp ? rect.top - menuHeight - gap : rect.bottom + gap;
		top = Math.max(margin, top);

		list.style.top = top + 'px';
		list.style.left = left + 'px';
	}

	/**
	 * 把浮层态还原回 <details> 内的默认绝对定位。
	 *
	 * 必须接收 list 引用：浮层打开时它挂在 <body> 下，此时
	 * details.querySelector('.row-menu__list') 已经找不到它（与 placeRowMenu 同一坑）。
	 */
	function restoreRowMenuList(details, list){
		if(!list) list = details.querySelector('.row-menu__list');
		if(!list) return;
		if(list.parentElement !== details){
			details.appendChild(list);
		}
		delete list.__rowMenuOwner;
		list.classList.remove('row-menu__list--portal');
		list.style.position = '';
		list.style.top = '';
		list.style.left = '';
		list.style.right = '';
		list.style.zIndex = '';
		list.style.display = '';
	}

	/** 关闭所有浮层菜单；except 为需要保留的那一个（可为 null）。 */
	function closeAllRowMenus(except){
		document.querySelectorAll('.row-menu__list--portal').forEach(list => {
			const owner = list.__rowMenuOwner;
			if(!owner || owner === except) return;
			restoreRowMenuList(owner, list);
			owner.open = false;
		});
	}

	function openRowMenu(details){
		const list = details.querySelector('.row-menu__list');
		if(!list) return;

		// Measure first: once the list is under <body> it is no longer reachable
		// through the <details>, and its size must be known to place it.
		list.style.position = 'fixed';
		list.style.top = '0px';
		list.style.left = '0px';
		const rect = list.getBoundingClientRect();
		const size = {
			width: rect.width || list.offsetWidth || 160,
			height: rect.height || list.offsetHeight || 0
		};

		list.classList.add('row-menu__list--portal');
		list.style.right = 'auto';
		list.style.zIndex = '6000';
		list.style.display = 'flex';
		// 记住归属，浮层期间 details.querySelector() 已找不到它
		list.__rowMenuOwner = details;
		details.__openRowMenuList = list;
		document.body.appendChild(list);
		placeRowMenu(details, list, size);
	}

	function closeRowMenu(details){
		restoreRowMenuList(details, details.__openRowMenuList || null);
		details.__openRowMenuList = null;
		details.open = false;
	}

	function initRowMenus(){
		// 打开/关闭：details 的 toggle 事件会冒泡到 document
		document.addEventListener('toggle', event => {
			const details = event.target;
			if(!details || !details.classList || !details.classList.contains('row-menu')) return;
			if(details.open){
				closeAllRowMenus(details);
				openRowMenu(details);
			} else {
				closeRowMenu(details);
			}
		}, true);

		// 点击菜单以外区域关闭
		document.addEventListener('click', event => {
			const insideMenu = event.target.closest && event.target.closest('.row-menu');
			const insideList = event.target.closest && event.target.closest('.row-menu__list');
			if(insideMenu || insideList) return;
			closeAllRowMenus(null);
		});

		// Esc 关闭
		document.addEventListener('keydown', event => {
			if(event.key === 'Escape') closeAllRowMenus(null);
		});

		// 浮层脱离文档流后需要跟随滚动/缩放重新定位。
		// 注意：不能再用 details.querySelector('.row-menu__list') 取回列表——浮层期间
		// 它挂在 <body> 下，details 内已查不到（会拿到 null 而抛错）。用打开时记录的引用。
		const reposition = () => {
			document.querySelectorAll('details.row-menu[open]').forEach(details => {
				const list = details.__openRowMenuList || details.querySelector('.row-menu__list');
				if(!list) return;
				if(isRowMenuListVisible(list)) placeRowMenu(details, list);
			});
		};
		window.addEventListener('scroll', reposition, true);
		window.addEventListener('resize', reposition);
	}

	console.log('[account] module ready');
	initRowMenus();
	fillIpLocations(document);
})();

