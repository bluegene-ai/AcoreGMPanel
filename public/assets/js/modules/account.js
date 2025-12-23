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
				const actionButtons = [
					{ action: 'chars', className: 'btn-sm btn info action', label: actionLabels.chars },
					{ action: 'gm', className: 'btn-sm btn warn action', label: actionLabels.gm },
					{ action: 'ban', className: 'btn-sm btn danger action', label: actionLabels.ban },
					{ action: 'unban', className: 'btn-sm btn success action', label: actionLabels.unban },
					{ action: 'pass', className: 'btn-sm btn info outline action', label: actionLabels.password },
					{ action: 'email', className: 'btn-sm btn neutral action', label: actionLabels.email },
					{ action: 'rename', className: 'btn-sm btn neutral outline action', label: actionLabels.rename },
					{ action: 'ip-accounts', className: 'btn-sm btn neutral action', label: actionLabels.sameIp, disabled: privateIp, title: privateIp ? privateIpTitle : '' },
					{ action: 'kick', className: 'btn-sm btn outline danger action', label: actionLabels.kick },
					{ action: 'delete', className: 'btn-sm btn danger action', label: actionLabels.delete }
				];
				const buttonsHtml = actionButtons.map(btn => {
					const attrs = [
						`class="${btn.className}"`,
						`data-action="${btn.action}"`
					];
					if(btn.disabled) attrs.push('disabled');
					if(btn.title) attrs.push(`title="${esc(btn.title)}"`);
					return `<button ${attrs.join(' ')}>${esc(btn.label)}</button>`;
				}).join('');
				const idValue = row.id ?? '';
				const usernameValue = row.username || '';
				const gmValue = row.gmlevel != null ? row.gmlevel : '-';
				const gmData = row.gmlevel != null ? row.gmlevel : 0;
				const lastLogin = row.last_login || '-';
				const lastIpCell = lastIp || '-';
				return `<tr data-id="${esc(idValue)}" data-username="${esc(usernameValue)}" data-gm="${esc(gmData)}" data-last-ip="${esc(lastIp)}">`
					+ `<td><input type="checkbox" class="js-account-select" value="${esc(idValue)}" aria-label="select"></td>`
					+ `<td>${esc(idValue)}</td>`
					+ `<td>${esc(usernameValue)}</td>`
					+ `<td>${esc(gmValue)}</td>`
					+ `<td>${statusHtml}</td>`
					+ `<td>${esc(lastLogin)}</td>`
					+ `<td>${esc(lastIpCell)}</td>`
					+ `<td class="ip-location" data-ip="${esc(lastIp)}">-</td>`
					+ `<td class="nowrap">${buttonsHtml}</td>`
					+ `</tr>`;
			}).join('');
			const emptyText = translate('feedback.empty', 'No results');
			tbody.innerHTML = rows || `<tr><td colspan="9" style="text-align:center;">${esc(emptyText)}</td></tr>`;
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
				const rawCharacterUrl = urlWithServer(`/character?guid=${encodeURIComponent(guid)}`);
				const toCharacter = (window.Panel && typeof window.Panel.url === 'function')
					? window.Panel.url(rawCharacterUrl)
					: rawCharacterUrl;
				const statusTag = online
					? `<span class="tag status-online-alt">${esc(onlineLabel)}</span>`
					: `<span class="tag status-offline">${esc(offlineLabel)}</span>`;
				const buttonAttrs = [
					'class="btn-sm btn outline danger action-kick-char"',
					`data-char="${esc(character.name)}"`
				];
				if(!online){
					buttonAttrs.push('disabled');
					buttonAttrs.push(`title="${esc(offlineTooltip)}"`);
				}
				const kickButton = `<button ${buttonAttrs.join(' ')}>${esc(kickLabel)}</button>`;
				return `<tr data-guid="${esc(character.guid)}" data-name="${esc(character.name)}" data-online="${online ? 1 : 0}">`
					+ `<td>${esc(character.guid)}</td>`
					+ `<td><a href="${esc(toCharacter)}"><span data-class-id="${esc(character.class)}">${esc(character.name)}</span></a></td>`
					+ `<td>${esc(character.level)}</td>`
					+ `<td>${statusTag} ${kickButton}</td>`
					+ `</tr>`;
			}).join('');
			const emptyRow = `<tr><td colspan="4" style="text-align:center">${esc(translate('characters.empty', 'No characters'))}</td></tr>`;
			modal.querySelector('.modal-body').innerHTML = `<table class="table modal-table-left"><thead><tr>`
				+ `<th>${esc(tableLabels.guid)}</th>`
				+ `<th>${esc(tableLabels.name)}</th>`
				+ `<th>${esc(tableLabels.level)}</th>`
				+ `<th>${esc(tableLabels.status)}</th>`
				+ `</tr></thead><tbody>${rows || emptyRow}</tbody></table>`;
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
					badge.style.marginLeft = '12px';
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
				<div class="form-error" style="display:none;color:#c53030;margin-top:8px;"></div>
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
				errorBox.style.display = 'block';
			}else{
				errorBox.textContent = '';
				errorBox.style.display = 'none';
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
				<div class="form-error" style="display:none;color:#c53030;margin-top:8px;"></div>
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
				errorBox.style.display = 'block';
			}else{
				errorBox.textContent = '';
				errorBox.style.display = 'none';
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
				<div class="form-error" style="display:none;color:#c53030;margin-top:8px;"></div>
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
				errorBox.style.display = 'block';
			}else{
				errorBox.textContent = '';
				errorBox.style.display = 'none';
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
					+ `<td>${esc(item.username || '')}</td>`
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

	console.log('[account] module ready');
	fillIpLocations(document);
})();

