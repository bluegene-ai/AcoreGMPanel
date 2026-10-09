/**
 * File: public/assets/js/modules/account.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
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

	/** 公共层缺失时的兜底：至少退回原生 confirm，而不是让危险操作静默失败。 */
	const confirmDialog = typeof panel.confirm === 'function'
		? panel.confirm.bind(panel)
		: (options)=> Promise.resolve(window.confirm(String((options && options.message) || '')));
	const panelPoll = typeof panel.poll === 'function' ? panel.poll.bind(panel) : null;
	const renderFieldErrors = typeof panel.formErrors === 'function' ? panel.formErrors.bind(panel) : null;

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

	const ACCOUNT_MODAL_ID = 'account-modal';
	let accountModalRef = null;

	/**
	 * 弹窗统一走 Panel.Modal：焦点陷阱、Esc 关闭、关闭后焦点归还、点遮罩不误关（表单类）
	 * 都由公共层负责。本模块只用 account-modal 一个槽位，重复调用不会叠出多层遮罩。
	 */
	function showModal(title, contentHtml, options){
		const opts = options || {};
		if(!window.Modal){
			closeModal();
			const markup = `<div class="modal-backdrop active"><div class="modal-panel"><header><h3>${esc(title)}</h3><button class="modal-close" aria-label="close">&times;</button></header><div class="modal-body"></div><div class="modal-footer modal-footer-right"></div></div></div>`;
			const wrapper = el(markup);
			wrapper.querySelector('.modal-body').innerHTML = contentHtml;
			wrapper.addEventListener('click', event => {
				const target = event.target;
				if(target && target.classList && target.classList.contains('modal-close')) closeModal();
			});
			document.body.appendChild(wrapper);
			wrapper.__footerEl = wrapper.querySelector('.modal-footer');
			return wrapper;
		}
		accountModalRef = window.Modal.show({
			id: ACCOUNT_MODAL_ID,
			title: title,
			content: contentHtml,
			footer: opts.footer === undefined ? '' : opts.footer,
			width: opts.width || '',
			closeOnBackdrop: false
		});
		const wrapper = accountModalRef.el;
		accountModalRef.footerEl.classList.add('modal-footer', 'modal-footer-right');
		wrapper.__footerEl = accountModalRef.footerEl;
		return wrapper;
	}

	function closeModal(){
		const open = accountModalRef ? accountModalRef.el : document.querySelector('.modal-backdrop.active');
		if(open && open.__poll && typeof open.__poll.stop === 'function') open.__poll.stop();
		if(accountModalRef){
			window.Modal.hide(accountModalRef.id);
			accountModalRef = null;
			return;
		}
		if(open && !window.Modal) open.remove();
	}

	/** 弹窗是否还开着：Panel.Modal 的元素不会从 DOM 移除，只能看 active 类。 */
	function modalIsOpen(modal){
		return !!(modal && modal.classList && modal.classList.contains('active'));
	}

	/**
	 * 表单弹窗：公共 Panel.formModal 在 account-modal 槽位里渲染。
	 * 目标实体在标题与只读回显里都写出来 —— 批量操作时"打错对象"是最贵的错误。
	 */
	function openFormModal(options){
		const opts = options || {};
		if(typeof panel.formModal !== 'function'){
			// 公共层缺失时退回本模块的极简弹窗，至少不要让操作无声失败
			const modal = showModal(opts.title, opts.body, {});
			return {
				modal,
				form: modal.querySelector('form'),
				showError(){},
				showFieldErrors(){},
				setBusy(){},
				close: closeModal,
				submit: async ()=>{ if(typeof opts.onSubmit === 'function') await opts.onSubmit(this); }
			};
		}
		return panel.formModal({
			id: ACCOUNT_MODAL_ID,
			title: opts.title,
			body: opts.body,
			submitLabel: opts.submitLabel || translate('form.submit', 'Save'),
			cancelLabel: opts.cancelLabel || translate('form.cancel', 'Cancel'),
			danger: !!opts.danger,
			width: opts.width || '',
			onSubmit: opts.onSubmit
		});
	}

	/**
	 * 在弹窗底部追加跳转链接（跨模块导航，例如"到角色管理看该账号的角色"）；已存在时只更新 href/label，避免叠加。
	 */
	function appendModalFooterLink(modal, path, label){
		if(!modal) return null;
		const footer = modal.querySelector('.modal-footer');
		if(!footer) return null;

		// window.APP_BASE 不存在；基路径由 Panel.url() 补（来自 <body data-app-base>）
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
			 * 行内操作条：归组规则与 account/index.php 的服务端渲染保持一致 —— 只平铺"角色 / 封禁"，
			 * 其余收进"更多"菜单；两边都输出 button.action[data-action]，由同一个 document 级委托处理。
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
	/** 目标账号的只读回显：批量或长列表里最容易搞错的就是"对谁下手"。 */
	function targetEchoHtml(username, id, extra){
		const label = translate('form.target', 'Target account');
		const name = username ? String(username) : '';
		const idLabel = id ? ` #${esc(id)}` : '';
		const suffix = extra ? ` <span class="muted small">${esc(extra)}</span>` : '';
		return `<div class="form-field"><label>${esc(label)}</label>`
			+ `<div class="account-form-target" data-readonly="1"><strong>${esc(name)}</strong>${idLabel}${suffix}</div></div>`;
	}

	async function doDeleteAccount(id, username){
		const requireText = String(username || ('#' + id));
		const ok = await confirmDialog({
			title: translate('delete.title', 'Delete account'),
			message: translate('delete.confirm', 'Delete this account? This also deletes every character on it and cannot be undone.'),
			requireText,
			requireLabel: translate('delete.require_label', 'Type the account name to confirm'),
			confirmLabel: translate('actions.delete', 'Delete'),
			danger: true
		});
		if(!ok) return;
		const res = await request('/account/api/delete', { method: 'POST', body: { id } });
		if(res && res.success){
			flash(translate('delete.success', 'Deleted'), 'success');
			reloadAccountTable();
			return;
		}
		flash((res && res.message) ? res.message : translate('errors.request_failed', 'Request failed'), 'error');
	}

	/**
	 * 封禁表单：时长（预设 + 自定义）+ 理由，目标账号只读回显。
   * 单账号与批量共用同一确认流程。
	 */
	function openBanModal(options){
		const opts = options || {};
		const ids = opts.ids || [];
		const single = ids.length === 1;
		const targetText = single
			? (opts.label || ('#' + ids[0]))
			: translate('ban.bulk_target', ':count selected accounts', { count: ids.length });
		const durationLabel = translate('ban.duration_label', 'Duration');
		const customLabel = translate('ban.custom_hours', 'Custom hours (overrides the choice above)');
		const reasonLabel = translate('ban.reason_label', 'Reason');
		const defaultReason = translate('ban.default_reason', 'Panel ban');
		const permanentLabel = translate('ban.permanent', 'Permanent');
		const presets = [24, 72, 168, 720];
		const optionsHtml = [`<option value="0">${esc(permanentLabel)}</option>`]
			.concat(presets.map((hours)=> `<option value="${hours}"${hours === 24 ? ' selected' : ''}>${esc(formatBanDuration(hours * 3600))}</option>`))
			.join('');

		const body = `<form class="form account-ban-form">`
			+ targetEchoHtml(targetText, single ? ids[0] : 0, single ? '' : translate('ban.bulk_target_hint', 'The same duration and reason apply to every selected account'))
			+ `<div class="form-field">`
			+ `<label for="account-ban-duration">${esc(durationLabel)}</label>`
			+ `<select name="hours" id="account-ban-duration">${optionsHtml}</select>`
			+ `<input type="number" name="custom_hours" min="0" max="87600" step="1" placeholder="${esc(customLabel)}">`
			+ `</div>`
			+ `<div class="form-field">`
			+ `<label for="account-ban-reason">${esc(reasonLabel)}</label>`
			+ `<input type="text" name="reason" id="account-ban-reason" maxlength="255" value="${esc(defaultReason)}" required>`
			+ `</div>`
			+ `<div class="form-error account-form-error" hidden></div>`
			+ `</form>`;

		openFormModal({
			title: single ? translate('ban.title', 'Ban account') : translate('ban.bulk_title', 'Ban selected accounts'),
			body,
			submitLabel: translate('ban.actions.submit', 'Ban'),
			danger: true,
			onSubmit: async (ui)=>{
				const data = ui.form ? new FormData(ui.form) : new FormData();
				const customRaw = String(data.get('custom_hours') || '').trim();
				const raw = customRaw !== '' ? customRaw : String(data.get('hours') || '0');
				const hours = parseInt(raw, 10);
				const reason = String(data.get('reason') || '').trim() || defaultReason;
				const errors = [];
				if(!Number.isFinite(hours) || hours < 0 || hours > 87600){
					errors.push({ field: customRaw !== '' ? 'custom_hours' : 'hours', message: translate('ban.error_hours', 'Invalid duration') });
				}
				if(reason === '') errors.push({ field: 'reason', message: translate('ban.error_reason', 'Please enter a reason') });
				if(errors.length){ ui.showFieldErrors(errors); return; }
				ui.setBusy(true, translate('ban.submitting', 'Banning…'));
				try{
					const res = opts.apply
						? await opts.apply({ hours, reason })
						: await request('/account/api/ban', { method: 'POST', body: { id: ids[0], hours, reason } });
					if(res && res.success){
						closeModal();
						flash(opts.successMessage || translate('ban.success', 'Account banned successfully'), 'success');
						reloadAccountTable();
						return;
					}
					ui.showError((res && res.message) ? res.message : translate('ban.failure', 'Failed to ban account'));
				}catch(err){
					ui.showError(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }));
				}finally{
					ui.setBusy(false);
				}
			}
		});
	}

	async function doBulk(action){
		const ids = selectedAccountIds();
		if(!ids.length){
			flash(translate('bulk.no_selection', 'Please select at least one item'), 'error');
			return;
		}
		if(action === 'delete'){
			const ok = await confirmDialog({
				title: translate('bulk.delete_title', 'Delete selected accounts'),
				message: translate('bulk.delete_confirm', 'Delete the selected accounts? Their characters go with them and this cannot be undone.'),
				requireText: String(ids.length),
				requireLabel: translate('bulk.delete_require_label', 'Type the number of selected accounts (:count)', { count: ids.length }),
				confirmLabel: translate('actions.delete', 'Delete'),
				danger: true
			});
			if(!ok) return;
		}
		if(action === 'ban'){
			openBanModal({
				ids,
				apply: (payload)=> request('/account/api/bulk', { method: 'POST', body: { action: 'ban', ids, hours: payload.hours, reason: payload.reason } }),
				successMessage: translate('bulk.ban_success', 'Selected accounts banned')
			});
			return;
		}
		if(action === 'unban'){
			const ok = await confirmDialog({
				message: translate('ban.confirm_unban', 'Unban selected accounts?'),
				confirmLabel: translate('actions.unban', 'Unban')
			});
			if(!ok) return;
		}
		const res = await request('/account/api/bulk', { method: 'POST', body: { action, ids, hours: 0, reason: '' } });
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
				const proceed = await confirmDialog({ message: confirmMsg, confirmLabel: translate('actions.kick', 'Kick'), danger: true });
				if(!proceed) return;
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
			/**
			 * 在线状态轮询：公共层 Panel.poll 负责失败退避与隐藏标签页暂停，
			 * 弹窗关闭（Panel.Modal 只切 active 类，不移除节点）时由 closeModal() 停表。
			 */
			const startPolling = () => {
				if(modal.__poll || !panelPoll) return;
				modal.__poll = panelPoll(async () => {
					if(!modalIsOpen(modal)){ modal.__poll.stop(); return; }
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
				}, 5000);
			};
			startPolling();
		}catch(err){
			modal.querySelector('.modal-body').innerHTML = `<div class="flash">${esc(translate('characters.fetch_failed', 'Failed to load characters: :message', { message: err.message }))}</div>`;
		}
	}

	async function doSetGm(id, username, currentLevel){
		const current = parseInt(currentLevel, 10);
		const levelLabel = translate('gm.level_label', 'GM level');
		const levelHint = translate('gm.level_hint', '0 = player, 6 = full administrator');
		const options = [0,1,2,3,4,5,6].map((level)=>{
			const selected = (!Number.isFinite(current) ? level === 0 : level === current) ? ' selected' : '';
			return `<option value="${level}"${selected}>${level}</option>`;
		}).join('');
		const body = `<form class="form account-gm-form">`
			+ targetEchoHtml(username, id)
			+ `<div class="form-field"><label for="account-gm-level">${esc(levelLabel)}</label>`
			+ `<select name="gm" id="account-gm-level">${options}</select>`
			+ `<div class="muted small">${esc(levelHint)}</div></div>`
			+ `<div class="form-error account-form-error" hidden></div>`
			+ `</form>`;

		openFormModal({
			title: translate('gm.title', 'Set GM level'),
			body,
			submitLabel: translate('gm.actions.submit', 'Save'),
			onSubmit: async (ui)=>{
				const data = ui.form ? new FormData(ui.form) : new FormData();
				const gmLevel = parseInt(String(data.get('gm') || '0'), 10);
				if(!Number.isFinite(gmLevel) || gmLevel < 0 || gmLevel > 6){
					ui.showFieldErrors([{ field: 'gm', message: translate('gm.error_level', 'Invalid GM level') }]);
					return;
				}
				ui.setBusy(true);
				try{
					const res = await request('/account/api/set-gm', { method: 'POST', body: { id, gm: gmLevel } });
					if(res && res.success){
						closeModal();
						flash(translate('gm.success', 'GM level updated'), 'success');
						reloadAccountTable();
						return;
					}
					ui.showError(translate('gm.failure', 'Failed to update GM level'));
				}catch(err){
					ui.showError(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }));
				}finally{
					ui.setBusy(false);
				}
			}
		});
	}

	async function doBan(id, username){
		openBanModal({ ids: [id], label: username || ('#' + id) });
	}

	async function doUnban(id){
		const ok = await confirmDialog({
			message: translate('ban.confirm_unban', 'Unban this account?'),
			confirmLabel: translate('actions.unban', 'Unban')
		});
		if(!ok) return;
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
		const newLabel = translate('password.new_label', 'New password');
		const confirmLabel = translate('password.confirm_label', 'Repeat new password');
		const hint = translate('password.hint', 'At least 8 characters. Existing sessions are invalidated.');
		const body = `<form class="form account-password-form">`
			+ targetEchoHtml(username, id)
			+ `<div class="form-field"><label for="account-new-password">${esc(newLabel)}</label>`
			+ `<input type="password" name="password" id="account-new-password" minlength="8" autocomplete="new-password" required></div>`
			+ `<div class="form-field"><label for="account-new-password-confirm">${esc(confirmLabel)}</label>`
			+ `<input type="password" name="password_confirm" id="account-new-password-confirm" minlength="8" autocomplete="new-password" required></div>`
			+ `<div class="muted small">${esc(hint)}</div>`
			+ `<div class="form-error account-form-error" hidden></div>`
			+ `</form>`;

		openFormModal({
			title: translate('password.title', 'Change password'),
			body,
			submitLabel: translate('password.actions.submit', 'Update password'),
			onSubmit: async (ui)=>{
				const data = ui.form ? new FormData(ui.form) : new FormData();
				const password = String(data.get('password') || '');
				const repeat = String(data.get('password_confirm') || '');
				const errors = [];
				if(password === '') errors.push({ field: 'password', message: translate('password.error_empty', 'Password cannot be empty') });
				else if(password.length < 8) errors.push({ field: 'password', message: translate('password.error_length', 'Password must be at least 8 characters') });
				if(repeat !== password) errors.push({ field: 'password_confirm', message: translate('password.error_mismatch', 'Passwords do not match') });
				if(errors.length){ ui.showFieldErrors(errors); return; }
				ui.setBusy(true, translate('password.submitting', 'Updating…'));
				try{
					const res = await request('/account/api/change-password', { method: 'POST', body: { id, username, password } });
					if(res && res.success){
						closeModal();
						flash(translate('password.success', 'Password updated successfully (previous sessions invalidated)'), 'success');
						return;
					}
					const message = res && res.message ? res.message : translate('password.failure_generic', 'Unknown error');
					ui.showError(translate('password.failure', 'Failed to change password: :message', { message }));
				}catch(err){
					ui.showError(translate('errors.request_failed_message', 'Request failed: :message', { message: err.message }));
				}finally{
					ui.setBusy(false);
				}
			}
		});
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
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'email', message: translate('email.invalid', 'Invalid email') }]);
				else showError(translate('email.invalid', 'Invalid email'));
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
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'username', message: translate('rename.invalid_username', 'Invalid username') }]);
				else showError(translate('rename.invalid_username', 'Invalid username'));
				return;
			}
			if(password.length < 8){
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'password', message: translate('rename.invalid_password', 'Password must be at least 8 characters') }]);
				else showError(translate('rename.invalid_password', 'Password must be at least 8 characters'));
				return;
			}
			if(password !== confirmPassword){
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'password_confirm', message: translate('rename.password_mismatch', 'Passwords do not match') }]);
				else showError(translate('rename.password_mismatch', 'Passwords do not match'));
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
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'username', message: translate('create.errors.username_required', 'Please enter a username') }]);
				else showError(translate('create.errors.username_required', 'Please enter a username'));
				return;
			}
			if(password.length < 8){
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'password', message: translate('create.errors.password_length', 'Password must be at least 8 characters') }]);
				else showError(translate('create.errors.password_length', 'Password must be at least 8 characters'));
				return;
			}
			if(password !== confirmPassword){
				if(renderFieldErrors) renderFieldErrors(form, [{ field: 'password_confirm', message: translate('create.errors.password_mismatch', 'Passwords do not match') }]);
				else showError(translate('create.errors.password_mismatch', 'Passwords do not match'));
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
		let id;
		let username;
		let gmLevel;
		let lastIp;
		if(row){
			id = parseInt(row.dataset.id, 10);
			username = row.dataset.username;
			gmLevel = row.dataset.gm;
			lastIp = row.dataset.lastIp || '';
		} else {
			// 浮层态：菜单已移到 <body> 下，按钮不再是 <tr> 的后代；行标识由 openRowMenu() 快照在列表上。
			const list = button.closest('.row-menu__list');
			if(!list) return;
			id = parseInt(list.dataset.accountId, 10);
			username = list.dataset.accountUsername;
			gmLevel = list.dataset.accountGm;
			lastIp = list.dataset.accountLastIp || '';
			if(!Number.isFinite(id) || id <= 0) return;
		}
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
	 * 行内"更多"菜单的浮层定位：.row-menu__list 是 absolute，而外层 .app-shell-panel
	 * {overflow:hidden} 会把它直接裁掉；所以打开时挂到 <body> 下改 position:fixed，
	 * 关闭（toggle / 点击别处 / Esc）时放回 <details>，不必改动 overflow。
	 */
	function isRowMenuListVisible(list){
		return !!list && !list.hidden && list.style.display !== 'none';
	}

	function placeRowMenu(details, list, measured){
		const summary = details.querySelector('summary');
		// list 可能已被移到 <body>（portal 的全部意义），所以按参数接收而不是重查 <details>，
		// 否则这里会静默返回、不做定位。
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
	 * 把浮层态还原回 <details> 内的默认绝对定位。必须接收 list 引用：浮层期间它挂在
	 * <body> 下，details.querySelector('.row-menu__list') 已找不到它（与 placeRowMenu 同一坑）。
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

		// 浮层期间按钮不在 <tr> 内，button.closest('tr') 返回 null 会让所有菜单操作静默失效，
		// 所以打开时把所属行标识快照到列表上，由点击委托回退读取。
		const row = details.closest('tr');
		if(row){
			list.dataset.accountId = row.dataset.id || '';
			list.dataset.accountUsername = row.dataset.username || '';
			list.dataset.accountGm = row.dataset.gm || '';
			list.dataset.accountLastIp = row.dataset.lastIp || '';
		}
		// 同样记录归属，浮层期间 details 内已查不到该 list
		list.__rowMenuOwner = details;
		details.__openRowMenuList = list;

		list.classList.add('row-menu__list--portal');
		list.style.right = 'auto';
		list.style.zIndex = '6000';
		list.style.display = 'flex';
		list.style.visibility = 'hidden';

		// 先入 <body> 再量尺寸：在 <details> 内它是被子元素撑开的，量不出菜单应有的宽度
		document.body.appendChild(list);

		// 强制一次布局，确保离屏尺寸已可读；读不到时回退到菜单常用宽度，避免用 0 导致定位错乱
		const offsetWidth = list.offsetWidth;
		const rect = list.getBoundingClientRect();
		const measured = {
			width: offsetWidth > 20 ? offsetWidth : (rect.width > 20 ? rect.width : 180),
			height: list.offsetHeight > 0 ? list.offsetHeight : rect.height
		};

		placeRowMenu(details, list, measured);
		list.style.visibility = '';
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

		// 浮层脱离文档流后要跟随滚动/缩放重新定位；只能用打开时记录的引用，
		// 不能再 details.querySelector('.row-menu__list')（浮层期间它在 <body> 下，会拿到 null 抛错）。
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
	initHotkeys();
	fillIpLocations(document);

	/**
	 * 页面级快捷键：`/` 聚焦关键字框、Esc 清空各筛选框、Ctrl+Enter 提交查询。
	 * 弹窗打开时不抢键（Esc 留给弹窗）。
	 */
	function initHotkeys(){
		if(typeof panel.hotkeys !== 'function') return;
		const filterForm = document.querySelector('form.list-filter');
		if(!filterForm) return;
		const keyword = filterForm.querySelector('input[name="search_value"], input[name="search_type"]');
		const hasModalOpen = ()=> !!document.querySelector('.modal-backdrop.active');
		panel.hotkeys({
			'/': ()=>{ if(hasModalOpen()) return; const field = filterForm.querySelector('input[name="search_value"]'); if(field){ field.focus(); field.select(); } },
			'escape': ()=>{
				if(hasModalOpen()) return;
				clearFilterFields(filterForm);
				// Esc 在输入框里也要有反应：清空之后再取消焦点，否则用户会以为快捷键坏了
				if(document.activeElement && filterForm.contains(document.activeElement)) document.activeElement.blur();
			},
			'ctrl+enter': ()=>{ if(hasModalOpen()) return; filterForm.requestSubmit ? filterForm.requestSubmit() : filterForm.submit(); }
		});
	}

	function clearFilterFields(form){
		['search_value', 'exclude_username'].forEach((name)=>{
			const field = form.querySelector('[name="' + name + '"]');
			if(field && field.value !== '') field.value = '';
		});
	}
})();

