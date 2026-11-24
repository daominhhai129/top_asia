(function () {
	'use strict';

	if (typeof window.taQirConfig === 'undefined') {
		return;
	}

	var cfg = window.taQirConfig;
	var STORAGE_KEY_ACTIVE = 'ta_qir_active';
	var STORAGE_KEY_SCOPE = 'ta_qir_scope';

	var isActive = false;
	var currentScope = localStorage.getItem(STORAGE_KEY_SCOPE) === 'page' ? 'page' : 'global';
	var currentTarget = null; // { el, type: 'img'|'bg', rawUrl, isBroken }
	var isHoveringToolbar = false;
	var isBusy = false;
	var mediaFrame = null;
	var toastTimer = null;

	// Khởi tạo trạng thái bật/tắt
	var savedActive = localStorage.getItem(STORAGE_KEY_ACTIVE);
	if (savedActive !== null) {
		isActive = savedActive === '1';
	} else {
		isActive = cfg.defaultMode === 'always';
	}

	// DOM Elements
	var overlay, btnReplace, btnUrl, btnScope, btnRevert, hintEl, urlModal, urlInput, urlPreviewWrap, urlPreviewImg, toastEl;

	function normalizeUrl(url) {
		if (!url) return '';
		var raw = String(url).trim();
		if (!raw || raw.indexOf('data:') === 0 || raw.indexOf('blob:') === 0) {
			return '';
		}
		try {
			var u = new URL(raw, window.location.origin);
			var home = new URL(cfg.homeUrl || window.location.origin);
			if (u.hostname.toLowerCase() === home.hostname.toLowerCase()) {
				return u.pathname + u.search;
			}
			return '//' + u.hostname.toLowerCase() + u.pathname + u.search;
		} catch (e) {
			return raw;
		}
	}

	function showToast(msg, isError) {
		if (!toastEl) return;
		toastEl.textContent = msg;
		toastEl.classList.toggle('is-error', !!isError);
		toastEl.classList.add('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			toastEl.classList.remove('is-visible');
		}, 3200);
	}

	function updateAdminBarUI() {
		document.body.classList.toggle('ta-qir-mode-active', isActive);
		var abNode = document.getElementById('wp-admin-bar-ta-qir-toggle');
		var abState = document.getElementById('ta-qir-ab-state');
		if (abNode) {
			abNode.classList.toggle('ta-qir-ab-on', isActive);
		}
		if (abState) {
			abState.textContent = isActive ? 'Bật' : 'Tắt';
		}
		if (!isActive) {
			hideOverlay();
		} else {
			scanBrokenImages();
		}
	}

	function scanBrokenImages() {
		var imgs = document.querySelectorAll('img');
		for (var i = 0; i < imgs.length; i++) {
			var img = imgs[i];
			if (img.closest('#wpadminbar') || img.closest('#ta-qir-url-modal') || img.closest('.media-modal')) {
				continue;
			}
			if (img.complete && img.naturalWidth === 0) {
				img.classList.add('ta-qir-broken-img');
			} else {
				img.classList.remove('ta-qir-broken-img');
			}
		}
	}

	function getImgRawUrl(img) {
		return (
			img.getAttribute('src') ||
			img.getAttribute('data-src') ||
			img.getAttribute('data-lazy-src') ||
			img.getAttribute('data-original') ||
			img.currentSrc ||
			img.src ||
			''
		);
	}

	function getBgRawUrl(el) {
		var styleBg = el.style && el.style.backgroundImage ? el.style.backgroundImage : '';
		var computed = styleBg || window.getComputedStyle(el).backgroundImage;
		if (!computed || computed === 'none') return '';
		var match = computed.match(/url\(\s*(['"]?)([^)"']+)\1\s*\)/i);
		if (!match || !match[2]) return '';
		if (match[2].indexOf('data:image/svg') === 0) return '';
		return match[2];
	}

	function findMapping(rawUrl) {
		var key = normalizeUrl(rawUrl);
		if (!key || !cfg.mappings) return null;
		return cfg.mappings[key] || null;
	}

	function getOriginalUrlForTarget(target) {
		var existing = findMapping(target.rawUrl);
		return existing ? existing.originalUrl : target.rawUrl;
	}

	function createUI() {
		overlay = document.createElement('div');
		overlay.id = 'ta-qir-overlay';
		overlay.innerHTML =
			'<div class="ta-qir-toolbar">' +
			'  <button type="button" class="ta-qir-btn ta-qir-btn--primary" data-action="media">Thay ảnh</button>' +
			'  <button type="button" class="ta-qir-btn" data-action="url">Dán URL</button>' +
			'  <button type="button" class="ta-qir-btn ta-qir-btn--scope" data-action="scope" title="Đổi phạm vi áp dụng"></button>' +
			'  <button type="button" class="ta-qir-btn ta-qir-btn--revert" data-action="revert" style="display:none;">Khôi phục</button>' +
			'</div>' +
			'<div class="ta-qir-hint"></div>';
		document.body.appendChild(overlay);

		btnReplace = overlay.querySelector('[data-action="media"]');
		btnUrl = overlay.querySelector('[data-action="url"]');
		btnScope = overlay.querySelector('[data-action="scope"]');
		btnRevert = overlay.querySelector('[data-action="revert"]');
		hintEl = overlay.querySelector('.ta-qir-hint');

		var toolbar = overlay.querySelector('.ta-qir-toolbar');
		toolbar.addEventListener('mouseenter', function () {
			isHoveringToolbar = true;
		});
		toolbar.addEventListener('mouseleave', function () {
			isHoveringToolbar = false;
		});

		btnReplace.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openMediaLibrary();
		});

		btnUrl.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openUrlModal();
		});

		btnScope.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			currentScope = currentScope === 'global' ? 'page' : 'global';
			localStorage.setItem(STORAGE_KEY_SCOPE, currentScope);
			updateScopeButton();
		});

		btnRevert.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			revertCurrentTarget();
		});

		// URL Modal
		urlModal = document.createElement('div');
		urlModal.id = 'ta-qir-url-modal';
		urlModal.innerHTML =
			'<div class="ta-qir-modal-card">' +
			'  <h3>Gắn URL ảnh trực tiếp</h3>' +
			'  <input type="url" class="ta-qir-modal-input" placeholder="https://topasia.vn/wp-content/uploads/..." />' +
			'  <div class="ta-qir-modal-preview"><img src="" alt="Preview" /></div>' +
			'  <div class="ta-qir-modal-scope">' +
			'    <label><input type="radio" name="ta_qir_modal_scope" value="global"> Toàn website</label>' +
			'    <label><input type="radio" name="ta_qir_modal_scope" value="page"> Chỉ trang này</label>' +
			'  </div>' +
			'  <div class="ta-qir-modal-actions">' +
			'    <button type="button" class="ta-qir-modal-cancel">Hủy</button>' +
			'    <button type="button" class="ta-qir-modal-save">Áp dụng</button>' +
			'  </div>' +
			'</div>';
		document.body.appendChild(urlModal);

		urlInput = urlModal.querySelector('.ta-qir-modal-input');
		urlPreviewWrap = urlModal.querySelector('.ta-qir-modal-preview');
		urlPreviewImg = urlPreviewWrap.querySelector('img');

		urlInput.addEventListener('input', function () {
			var v = urlInput.value.trim();
			if (/^https?:\/\/.+/i.test(v) || /^\//.test(v)) {
				urlPreviewImg.src = v;
				urlPreviewWrap.style.display = 'block';
			} else {
				urlPreviewWrap.style.display = 'none';
			}
		});

		urlModal.querySelector('.ta-qir-modal-cancel').addEventListener('click', closeUrlModal);
		urlModal.addEventListener('click', function (e) {
			if (e.target === urlModal) closeUrlModal();
		});
		urlModal.querySelector('.ta-qir-modal-save').addEventListener('click', function () {
			var newUrl = urlInput.value.trim();
			if (!newUrl) {
				showToast('Vui lòng nhập URL ảnh hợp lệ.', true);
				return;
			}
			var checkedScope = urlModal.querySelector('input[name="ta_qir_modal_scope"]:checked');
			var chosenScope = checkedScope ? checkedScope.value : currentScope;
			closeUrlModal();
			saveReplacement({ new_url: newUrl, scope: chosenScope });
		});

		// Toast
		toastEl = document.createElement('div');
		toastEl.id = 'ta-qir-toast';
		document.body.appendChild(toastEl);

		updateScopeButton();
	}

	function updateScopeButton() {
		if (!btnScope) return;
		btnScope.textContent = currentScope === 'global' ? 'Phạm vi: Toàn web' : 'Phạm vi: Trang này';
	}

	function positionOverlay(target) {
		if (!target || !target.el || !overlay) return;
		var rect = target.el.getBoundingClientRect();
		if (rect.width < 10 || rect.height < 10) {
			hideOverlay();
			return;
		}

		overlay.style.top = rect.top + 'px';
		overlay.style.left = rect.left + 'px';
		overlay.style.width = rect.width + 'px';
		overlay.style.height = rect.height + 'px';
		overlay.style.display = 'block';
		overlay.classList.toggle('ta-qir-is-broken', !!target.isBroken);

		var existing = findMapping(target.rawUrl);
		btnRevert.style.display = existing ? 'inline-flex' : 'none';

		if (target.isBroken) {
			hintEl.innerHTML = '<strong>Ảnh lỗi</strong> · Nhấn <strong>Ctrl+V</strong> để dán ảnh hoặc bấm Thay ảnh';
		} else {
			hintEl.innerHTML = 'Nhấn <strong>Ctrl+V</strong> để dán ảnh/URL hoặc kéo thả file vào đây';
		}
	}

	function hideOverlay() {
		if (overlay && !isBusy) {
			overlay.style.display = 'none';
		}
		if (!isBusy) {
			currentTarget = null;
		}
	}

	function detectTargetAtPoint(clientX, clientY) {
		var elements = document.elementsFromPoint(clientX, clientY);
		var minSize = cfg.minSize || 24;

		for (var i = 0; i < elements.length; i++) {
			var el = elements[i];
			if (
				el.id === 'ta-qir-overlay' ||
				el.closest('#ta-qir-overlay') ||
				el.closest('#wpadminbar') ||
				el.closest('#ta-qir-url-modal') ||
				el.closest('.media-modal')
			) {
				continue;
			}

			if (el.tagName === 'IMG') {
				var rect = el.getBoundingClientRect();
				var isBroken = el.complete && el.naturalWidth === 0;
				if (!isBroken && (rect.width < minSize || rect.height < minSize)) {
					continue;
				}
				var raw = getImgRawUrl(el);
				if (raw) {
					return { el: el, type: 'img', rawUrl: raw, isBroken: isBroken };
				}
			}
		}

		if (cfg.supportBg) {
			for (var j = 0; j < elements.length; j++) {
				var bgEl = elements[j];
				if (
					bgEl.id === 'ta-qir-overlay' ||
					bgEl.closest('#ta-qir-overlay') ||
					bgEl.closest('#wpadminbar') ||
					bgEl.closest('#ta-qir-url-modal')
				) {
					continue;
				}
				var bgRect = bgEl.getBoundingClientRect();
				if (bgRect.width < minSize || bgRect.height < minSize) {
					continue;
				}
				var bgRaw = getBgRawUrl(bgEl);
				if (bgRaw) {
					return { el: bgEl, type: 'bg', rawUrl: bgRaw, isBroken: false };
				}
			}
		}

		return null;
	}

	function applyDomReplacement(target, newUrl, srcset) {
		if (!target || !target.el) return;
		if (target.type === 'img') {
			var img = target.el;
			img.src = newUrl;
			if (img.hasAttribute('data-src')) img.setAttribute('data-src', newUrl);
			if (img.hasAttribute('data-lazy-src')) img.setAttribute('data-lazy-src', newUrl);
			if (img.hasAttribute('data-original')) img.setAttribute('data-original', newUrl);
			if (srcset) {
				img.setAttribute('srcset', srcset);
			} else {
				img.removeAttribute('srcset');
				img.removeAttribute('data-srcset');
			}
			img.classList.remove('ta-qir-broken-img');
		} else {
			target.el.style.backgroundImage = 'url("' + newUrl + '")';
		}
		target.rawUrl = newUrl;
		target.isBroken = false;
	}

	function setLoading(loading) {
		isBusy = loading;
		if (overlay) {
			overlay.classList.toggle('ta-qir-is-loading', loading);
		}
	}

	function saveReplacement(payload) {
		if (!currentTarget) return;
		var targetSnapshot = currentTarget;
		var originalUrl = getOriginalUrlForTarget(targetSnapshot);
		var reqScope = payload.scope || currentScope;

		setLoading(true);
		fetch(cfg.restUrl + '/replace', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify({
				original_url: originalUrl,
				new_url: payload.new_url || '',
				attachment_id: payload.attachment_id || 0,
				scope: reqScope,
				page_path: cfg.pagePath,
			}),
		})
			.then(function (res) {
				return res.json().then(function (data) {
					if (!res.ok) throw new Error(data.message || 'Không thể lưu ảnh.');
					return data;
				});
			})
			.then(function (data) {
				var mapping = data.mapping;
				var newKey = normalizeUrl(mapping.new_url);
				cfg.mappings[newKey] = {
					id: mapping.id,
					originalUrl: mapping.original_url,
					originalKey: mapping.original_key,
					newUrl: mapping.new_url,
					scope: mapping.scope,
					attachmentId: mapping.attachment_id,
				};
				applyDomReplacement(targetSnapshot, mapping.new_url, data.srcset || '');
				positionOverlay(targetSnapshot);
				showToast('Đã thay ảnh thành công!');
			})
			.catch(function (err) {
				showToast(err.message || 'Có lỗi xảy ra khi thay ảnh.', true);
			})
			.finally(function () {
				setLoading(false);
			});
	}

	function uploadPastedFile(file, targetSnapshot) {
		if (!targetSnapshot) return;
		var originalUrl = getOriginalUrlForTarget(targetSnapshot);
		var altText = targetSnapshot.type === 'img' ? (targetSnapshot.el.getAttribute('alt') || '') : '';

		var formData = new FormData();
		formData.append('file', file, file.name || 'pasted-image.png');
		formData.append('original_url', originalUrl);
		formData.append('alt_text', altText);
		formData.append('scope', currentScope);
		formData.append('page_path', cfg.pagePath);

		setLoading(true);
		fetch(cfg.restUrl + '/upload-paste', {
			method: 'POST',
			headers: {
				'X-WP-Nonce': cfg.nonce,
			},
			body: formData,
		})
			.then(function (res) {
				return res.json().then(function (data) {
					if (!res.ok) throw new Error(data.message || 'Không thể tải ảnh lên.');
					return data;
				});
			})
			.then(function (data) {
				var mapping = data.mapping;
				var newKey = normalizeUrl(mapping.new_url);
				cfg.mappings[newKey] = {
					id: mapping.id,
					originalUrl: mapping.original_url,
					originalKey: mapping.original_key,
					newUrl: mapping.new_url,
					scope: mapping.scope,
					attachmentId: mapping.attachment_id,
				};
				applyDomReplacement(targetSnapshot, mapping.new_url, data.srcset || '');
				positionOverlay(targetSnapshot);
				showToast('Đã lưu vào Media Library và thay ảnh thành công!');
			})
			.catch(function (err) {
				showToast(err.message || 'Lỗi tải ảnh dán vào.', true);
			})
			.finally(function () {
				setLoading(false);
			});
	}

	function revertCurrentTarget() {
		if (!currentTarget) return;
		var targetSnapshot = currentTarget;
		var existing = findMapping(targetSnapshot.rawUrl);
		if (!existing) return;

		setLoading(true);
		fetch(cfg.restUrl + '/revert', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify({
				id: existing.id,
				original_url: existing.originalUrl,
				page_path: cfg.pagePath,
			}),
		})
			.then(function (res) {
				return res.json().then(function (data) {
					if (!res.ok) throw new Error(data.message || 'Không thể khôi phục ảnh.');
					return data;
				});
			})
			.then(function () {
				delete cfg.mappings[normalizeUrl(targetSnapshot.rawUrl)];
				applyDomReplacement(targetSnapshot, existing.originalUrl, '');
				positionOverlay(targetSnapshot);
				showToast('Đã khôi phục về ảnh gốc.');
			})
			.catch(function (err) {
				showToast(err.message || 'Lỗi khôi phục ảnh.', true);
			})
			.finally(function () {
				setLoading(false);
			});
	}

	function openMediaLibrary() {
		if (!currentTarget || typeof wp === 'undefined' || !wp.media) {
			showToast('Không thể mở WordPress Media Library.', true);
			return;
		}
		var targetSnapshot = currentTarget;

		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: 'Chọn ảnh thay thế',
				button: { text: 'Dùng ảnh này' },
				library: { type: 'image' },
				multiple: false,
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				if (attachment && attachment.id) {
					currentTarget = targetSnapshot;
					saveReplacement({
						attachment_id: attachment.id,
						new_url: attachment.url,
					});
				}
			});
		}

		mediaFrame.open();
	}

	function openUrlModal() {
		if (!currentTarget || !urlModal) return;
		urlInput.value = '';
		urlPreviewWrap.style.display = 'none';
		var radios = urlModal.querySelectorAll('input[name="ta_qir_modal_scope"]');
		for (var i = 0; i < radios.length; i++) {
			radios[i].checked = radios[i].value === currentScope;
		}
		urlModal.classList.add('is-open');
		setTimeout(function () {
			urlInput.focus();
		}, 50);
	}

	function closeUrlModal() {
		if (urlModal) {
			urlModal.classList.remove('is-open');
		}
	}

	function bindEvents() {
		// Nút bật/tắt trên Admin Bar
		var abToggle = document.querySelector('#wp-admin-bar-ta-qir-toggle > a');
		if (abToggle) {
			abToggle.addEventListener('click', function (e) {
				e.preventDefault();
				isActive = !isActive;
				localStorage.setItem(STORAGE_KEY_ACTIVE, isActive ? '1' : '0');
				updateAdminBarUI();
				showToast(isActive ? 'Đã BẬT chế độ Thay ảnh nhanh (rê chuột & Ctrl+V).' : 'Đã TẮT chế độ Thay ảnh nhanh.');
			});
		}

		// Theo dõi rê chuột
		document.addEventListener(
			'mousemove',
			function (e) {
				if (!isActive || isBusy || isHoveringToolbar || (urlModal && urlModal.classList.contains('is-open'))) {
					return;
				}
				var target = detectTargetAtPoint(e.clientX, e.clientY);
				if (target) {
					currentTarget = target;
					positionOverlay(target);
				} else {
					hideOverlay();
				}
			},
			{ passive: true }
		);

		window.addEventListener(
			'scroll',
			function () {
				if (isActive && currentTarget && overlay.style.display === 'block') {
					positionOverlay(currentTarget);
				}
			},
			{ passive: true }
		);

		window.addEventListener('resize', function () {
			if (isActive && currentTarget && overlay.style.display === 'block') {
				positionOverlay(currentTarget);
			}
		});

		// Hỗ trợ Ctrl+V / Cmd+V dán ảnh hoặc URL trực tiếp vào ảnh đang hover
		document.addEventListener('paste', function (e) {
			if (!isActive || !currentTarget || isBusy) {
				return;
			}
			var activeEl = document.activeElement;
			if (
				activeEl &&
				(activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable)
			) {
				return;
			}

			var cd = e.clipboardData || window.clipboardData;
			if (!cd) return;

			// 1. Ưu tiên kiểm tra dữ liệu ảnh trong clipboard
			var items = cd.items || [];
			for (var i = 0; i < items.length; i++) {
				if (items[i].type && items[i].type.indexOf('image/') === 0) {
					var blob = items[i].getAsFile();
					if (blob) {
						e.preventDefault();
						uploadPastedFile(blob, currentTarget);
						return;
					}
				}
			}

			if (cd.files && cd.files.length > 0 && cd.files[0].type.indexOf('image/') === 0) {
				e.preventDefault();
				uploadPastedFile(cd.files[0], currentTarget);
				return;
			}

			// 2. Nếu clipboard là URL ảnh
			var text = cd.getData ? cd.getData('text/plain').trim() : '';
			if (text && (/^https?:\/\/\S+$/i.test(text) || /^\/\S+$/.test(text))) {
				e.preventDefault();
				saveReplacement({ new_url: text });
			}
		});

		// Hỗ trợ Kéo & Thả file ảnh từ máy tính vào ảnh đang hover
		document.addEventListener('dragover', function (e) {
			if (!isActive || isBusy) return;
			if (e.dataTransfer && e.dataTransfer.types && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1) {
				var target = detectTargetAtPoint(e.clientX, e.clientY);
				if (target) {
					e.preventDefault();
					currentTarget = target;
					positionOverlay(target);
					overlay.classList.add('ta-qir-is-dragover');
				}
			}
		});

		document.addEventListener('dragleave', function () {
			if (overlay) {
				overlay.classList.remove('ta-qir-is-dragover');
			}
		});

		document.addEventListener('drop', function (e) {
			if (!isActive || isBusy) return;
			if (overlay) {
				overlay.classList.remove('ta-qir-is-dragover');
			}
			var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
			if (!files || !files.length) return;
			var file = files[0];
			if (!file.type || file.type.indexOf('image/') !== 0) return;

			var target = detectTargetAtPoint(e.clientX, e.clientY) || currentTarget;
			if (target) {
				e.preventDefault();
				currentTarget = target;
				uploadPastedFile(file, target);
			}
		});
	}

	function init() {
		createUI();
		bindEvents();
		updateAdminBarUI();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
