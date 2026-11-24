(function () {
	'use strict';

	var started = false;

	function dl() {
		if (!window.dataLayer) {
			window.dataLayer = [];
		}
		return window.dataLayer;
	}

	function normalizeRoleValue(val) {
		var map = {
			'Chủ đầu tư / Nhà thầu': 'Chủ đầu tư & Nhà thầu',
			'Đại lý / Nhà phân phối': 'Đại lý & Nhà phân phối',
			'Khách lẻ': 'Nhu cầu cá nhân'
		};
		return map[val] || val;
	}

	function setRadio(name, val) {
		var target = name === 'customer-type' ? normalizeRoleValue(val) : val;
		var radios = document.getElementsByName(name);
		for (var i = 0; i < radios.length; i++) {
			var r = radios[i];
			if (r.value === target || r.value === val) {
				r.checked = true;
				r.dispatchEvent(new Event('change', { bubbles: true }));
				return r.value;
			}
		}
		return '';
	}

	function getRadioVal(name) {
		var radios = document.getElementsByName(name);
		for (var i = 0; i < radios.length; i++) {
			var r = radios[i];
			if (r.type === 'radio') {
				if (r.checked) return r.value;
			} else {
				return r.value;
			}
		}
		return '';
	}

	function formatVND(num) {
		if (!num || num <= 0) return '—';
		return Number(num).toLocaleString('vi-VN') + 'đ';
	}

	/* ---------- Live Selection Summary inside #form-bao-gia ---------- */
	function ensureFormSummaryBar() {
		var card = document.querySelector('#form-bao-gia .tap-form .bgq-form');
		if (!card) return null;
		var bar = document.getElementById('bgq-form-selected-bar');
		if (!bar) {
			bar = document.createElement('div');
			bar.id = 'bgq-form-selected-bar';
			bar.className = 'bgq-form-selected';
			bar.innerHTML =
				'<span>Đã chọn: <strong id="bgq-form-selected-text"></strong></span>' +
				'<button type="button" class="bgq-form-selected-clear">Bỏ chọn</button>';
			card.insertBefore(bar, card.firstChild);

			bar.querySelector('.bgq-form-selected-clear').addEventListener('click', function () {
				bar.classList.remove('is-visible');
				document.querySelectorAll('.bgq-table tbody tr.is-selected, .bgq-m-card.is-selected').forEach(function (el) {
					el.classList.remove('is-selected');
				});
			});
		}
		return bar;
	}

	function setFormSelectionSummary(summaryText) {
		var bar = ensureFormSummaryBar();
		if (!bar) return;
		var txtEl = document.getElementById('bgq-form-selected-text');
		if (txtEl) txtEl.textContent = summaryText;
		bar.classList.add('is-visible');
	}

	function appendNote(txt) {
		var noteEl = document.getElementById('bgq-note');
		if (!noteEl) return;
		var cur = (noteEl.value || '').trim();
		if (cur.indexOf(txt) < 0) {
			noteEl.value = cur ? cur + ' ' + txt : txt;
			noteEl.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	function scrollToForm() {
		var formSec = document.getElementById('form-bao-gia');
		if (formSec) {
			formSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		setTimeout(function () {
			var nameEl = document.getElementById('bgq-name');
			if (nameEl && !nameEl.value) {
				try {
					nameEl.focus({ preventScroll: true });
				} catch (e) {}
			}
		}, 650);
	}

	/* ---------- Header Height Scroll Margin Sync ---------- */
	function syncHeaderOffset() {
		var headerMain = document.querySelector('#header .header-main');
		var hdrH = headerMain ? Math.round(headerMain.getBoundingClientRect().height) : 70;
		document.documentElement.style.setProperty('--bgq-hdr-h', hdrH + 'px');
	}

	/* ---------- Mobile Line Tab Switcher ---------- */
	var activeLineSlug = 'asia-gold';

	function activateMobileTab(slug, syncFormRadio) {
		var tabBtn = document.querySelector('[data-bgq-mtab="' + slug + '"]');
		var panel = document.querySelector('[data-bgq-mpanel="' + slug + '"]');
		if (!tabBtn || !panel) return;

		activeLineSlug = slug;
		document.querySelectorAll('[data-bgq-mtab]').forEach(function (btn) {
			var isMatch = btn === tabBtn;
			btn.classList.toggle('is-active', isMatch);
			btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
		});

		document.querySelectorAll('[data-bgq-mpanel]').forEach(function (p) {
			var isMatch = p === panel;
			p.classList.toggle('is-active', isMatch);
			if (isMatch) {
				p.removeAttribute('hidden');
			} else {
				p.setAttribute('hidden', '');
			}
		});

		var lineName = tabBtn.getAttribute('data-value') || '';
		if (syncFormRadio && lineName) {
			setRadio('product-line', lineName);
			dl().push({
				event: 'quote_product_line_select',
				product_line: lineName,
				lead_source: '/bao-gia/'
			});
		}
	}

	/* ---------- Mobile Quick Estimator (#bgq-m-calc) ---------- */
	function initMobileCalculator() {
		var specSelect = document.getElementById('bgq-calc-spec');
		var areaInput = document.getElementById('bgq-calc-area');
		var uvToggle = document.getElementById('bgq-calc-uv');
		var totalEl = document.getElementById('bgq-calc-total');
		var applyBtn = document.getElementById('bgq-calc-apply');
		if (!specSelect || !areaInput || !totalEl) return null;

		function updateCalc() {
			var opt = specSelect.options[specSelect.selectedIndex];
			var unitPrice = opt ? parseInt(opt.getAttribute('data-price') || '0', 10) : 0;
			var area = parseFloat((areaInput.value || '0').replace(',', '.'));
			var uv = !!(uvToggle && uvToggle.checked);

			if (!opt || unitPrice <= 0) {
				totalEl.textContent = 'Liên hệ báo giá trực tiếp';
				return { unitPrice: 0, area: area, uv: uv, total: 0 };
			}
			if (isNaN(area) || area <= 0) {
				totalEl.textContent = 'Nhập số m² hợp lệ';
				return { unitPrice: unitPrice, area: 0, uv: uv, total: 0 };
			}

			var effectiveUnit = unitPrice + (uv ? 20000 : 0);
			var total = Math.round(area * effectiveUnit);
			totalEl.textContent = formatVND(total);
			return { unitPrice: effectiveUnit, area: area, uv: uv, total: total };
		}

		specSelect.addEventListener('change', updateCalc);
		areaInput.addEventListener('input', updateCalc);
		if (uvToggle) uvToggle.addEventListener('change', updateCalc);

		if (applyBtn) {
			applyBtn.addEventListener('click', function () {
				var opt = specSelect.options[specSelect.selectedIndex];
				if (!opt) return;
				var lineName = opt.getAttribute('data-line') || '';
				var thick = opt.getAttribute('data-thick') || '';
				var res = updateCalc();

				if (lineName) setRadio('product-line', lineName);
				var parts = ['Quan tâm: ' + lineName + ' ' + thick];
				if (res.area > 0) parts.push('diện tích ~' + res.area + ' m²');
				if (res.uv) parts.push('phủ UV (+20.000đ/m²)');
				if (res.total > 0) parts.push('tạm tính ' + formatVND(res.total));

				var noteTxt = parts.join(', ') + '.';
				appendNote(noteTxt);
				setFormSelectionSummary(lineName + ' · ' + thick + (res.area > 0 ? ' (' + res.area + ' m²)' : ''));
				scrollToForm();
			});
		}

		updateCalc();
		return {
			selectSpec: function (lineName, thick) {
				var target = lineName + '|' + thick;
				for (var i = 0; i < specSelect.options.length; i++) {
					if (specSelect.options[i].value === target) {
						specSelect.selectedIndex = i;
						break;
					}
				}
				updateCalc();
				var calcSec = document.getElementById('bgq-m-calc');
				if (calcSec) calcSec.scrollIntoView({ behavior: 'smooth', block: 'center' });
				setTimeout(function () {
					try {
						areaInput.focus({ preventScroll: true });
						areaInput.select();
					} catch (e) {}
				}, 450);
			}
		};
	}

	/* ---------- PDF Gate Modal (when taf_qd_* is active) ---------- */
	function initPdfModal() {
		var modal = document.getElementById('ta-quote-modal');
		if (!modal) return;
		var form = document.getElementById('ta-quote-download-form');
		var formView = modal.querySelector('[data-ta-qview="form"]');
		var successView = modal.querySelector('[data-ta-qview="success"]');
		var downloadsWrap = modal.querySelector('[data-ta-qdownloads]');
		var phoneInput = document.getElementById('ta-qd-phone');
		var errorEl = modal.querySelector('.ta-qmodal__error');
		var submitBtn = modal.querySelector('.ta-qmodal__submit');
		var lastFocus = null;

		function setError(msg) {
			if (!errorEl) return;
			if (!msg) {
				errorEl.textContent = '';
				errorEl.setAttribute('hidden', '');
			} else {
				errorEl.textContent = msg;
				errorEl.removeAttribute('hidden');
			}
		}

		function resetModal() {
			if (formView) formView.removeAttribute('hidden');
			if (successView) successView.setAttribute('hidden', '');
			setError('');
			if (submitBtn) {
				submitBtn.disabled = false;
				var sp = submitBtn.querySelector('span');
				if (sp) sp.textContent = 'Xác nhận & Tải bảng giá';
			}
			var radio = modal.querySelector('input[name="product"][value="' + activeLineSlug + '"]');
			if (radio) radio.checked = true;
		}

		function openModal() {
			lastFocus = document.activeElement;
			resetModal();
			modal.removeAttribute('inert');
			modal.setAttribute('aria-hidden', 'false');
			modal.classList.add('is-open');
			document.documentElement.style.overflow = 'hidden';
			if (phoneInput) {
				setTimeout(function () {
					try { phoneInput.focus(); } catch (e) {}
				}, 80);
			}
		}

		function closeModal() {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			modal.setAttribute('inert', '');
			document.documentElement.style.overflow = '';
			if (lastFocus && typeof lastFocus.focus === 'function') {
				try { lastFocus.focus({ preventScroll: true }); } catch (e) {}
			}
		}

		function triggerDownload(url) {
			var iframe = document.createElement('iframe');
			iframe.style.display = 'none';
			iframe.src = url;
			document.body.appendChild(iframe);
			setTimeout(function () {
				if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
			}, 15000);
		}

		document.querySelectorAll('[data-ta-open-quote]').forEach(function (btn) {
			btn.addEventListener('click', openModal);
		});
		modal.querySelectorAll('[data-ta-modal-close]').forEach(function (btn) {
			btn.addEventListener('click', closeModal);
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.classList.contains('is-open')) {
				closeModal();
			}
		});

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				setError('');
				var rawPhone = phoneInput ? phoneInput.value.trim() : '';
				var normPhone = rawPhone.replace(/\D+/g, '');
				if (normPhone.indexOf('84') === 0) normPhone = '0' + normPhone.slice(2);
				if (!/^0\d{9,10}$/.test(normPhone)) {
					setError('Vui lòng nhập số điện thoại hợp lệ (10–11 chữ số, bắt đầu bằng 0).');
					if (phoneInput) phoneInput.focus();
					return;
				}
				if (!window.TAFQuoteConfig || !window.TAFQuoteConfig.ajaxUrl) {
					setError('Cấu hình tải xuống chưa sẵn sàng.');
					return;
				}
				var prodEl = form.querySelector('input[name="product"]:checked');
				var hpEl = form.querySelector('input[name="taf_qd_company"]');

				if (submitBtn) {
					submitBtn.disabled = true;
					var sp = submitBtn.querySelector('span');
					if (sp) sp.textContent = 'Đang xử lý...';
				}

				var body = new FormData();
				body.append('action', 'taf_qd_submit');
				body.append('nonce', window.TAFQuoteConfig.nonce);
				body.append('phone', normPhone);
				body.append('product', prodEl ? prodEl.value : 'asia-gold');
				body.append('taf_qd_company', hpEl ? hpEl.value : '');

				fetch(window.TAFQuoteConfig.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				})
					.then(function (res) {
						return res.json().then(function (data) {
							return { ok: res.ok, body: data };
						});
					})
					.then(function (res) {
						if (!res.ok || !res.body || !res.body.success) {
							var msg = res.body && res.body.data && res.body.data.message ? res.body.data.message : 'Không thể mở khóa file lúc này.';
							throw new Error(msg);
						}
						var data = res.body.data;
						if (downloadsWrap && Array.isArray(data.downloads)) {
							downloadsWrap.innerHTML = '';
							data.downloads.forEach(function (item) {
								var a = document.createElement('a');
								a.className = 'ta-qmodal__dl-item';
								a.href = item.url;
								a.innerHTML = '<span>Bảng giá <strong>' + item.label + '</strong></span><span class="ta-qmodal__dl-badge">Tải PDF</span>';
								downloadsWrap.appendChild(a);
							});
						}
						if (formView) formView.setAttribute('hidden', '');
						if (successView) successView.removeAttribute('hidden');
						if (data.downloadUrl) triggerDownload(data.downloadUrl);
					})
					.catch(function (err) {
						setError(err && err.message ? err.message : 'Có lỗi xảy ra, vui lòng thử lại.');
						if (submitBtn) {
							submitBtn.disabled = false;
							var sp = submitBtn.querySelector('span');
							if (sp) sp.textContent = 'Xác nhận & Tải bảng giá';
						}
					});
			});
		}
	}

	/* ---------- Bootstrap All Quote Interactions ---------- */
	document.addEventListener('DOMContentLoaded', function () {
		var calc = initMobileCalculator();
		initPdfModal();
		syncHeaderOffset();

		window.addEventListener('resize', syncHeaderOffset, { passive: true });

		// Handle direct hash links on mobile (#gia-asia-gold / #gia-asia-pearl / #gia-asia-lite)
		var hash = window.location.hash || '';
		if (/^#gia-(asia-gold|asia-pearl|asia-lite)$/.test(hash) && window.innerWidth <= 767) {
			activateMobileTab(hash.replace('#gia-', ''), true);
			var mApp = document.getElementById('bgq-mobile-app');
			if (mApp) {
				setTimeout(function () {
					mApp.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 120);
			}
		}

		// Delegated click handler covering Mobile + Desktop controls
		document.addEventListener('click', function (e) {
			var tabBtn = e.target.closest('[data-bgq-mtab]');
			if (tabBtn) {
				var slug = tabBtn.getAttribute('data-bgq-mtab');
				activateMobileTab(slug, true);
				return;
			}

			var filterChip = e.target.closest('[data-bgq-filter]');
			if (filterChip) {
				var filter = filterChip.getAttribute('data-bgq-filter');
				var lineSlug = filterChip.getAttribute('data-line');
				var group = filterChip.closest('.bgq-m-filters');
				if (group) {
					group.querySelectorAll('[data-bgq-filter]').forEach(function (c) {
						var active = c === filterChip;
						c.classList.toggle('is-active', active);
						c.setAttribute('aria-pressed', active ? 'true' : 'false');
					});
				}
				var list = document.querySelector('[data-bgq-list="' + lineSlug + '"]');
				if (list) {
					list.querySelectorAll('[data-bgq-card]').forEach(function (card) {
						if (filter === 'all') {
							card.removeAttribute('hidden');
							return;
						}
						var buckets = (card.getAttribute('data-buckets') || '').split(/\s+/);
						if (buckets.indexOf(filter) >= 0) {
							card.removeAttribute('hidden');
						} else {
							card.setAttribute('hidden', '');
						}
					});
				}
				return;
			}

			var pickCalcBtn = e.target.closest('[data-bgq-pick-calc]');
			if (pickCalcBtn && calc) {
				var cLine = pickCalcBtn.getAttribute('data-line') || '';
				var cThick = pickCalcBtn.getAttribute('data-thick') || '';
				calc.selectSpec(cLine, cThick);
				return;
			}

			var bgqEl = e.target.closest('[data-bgq]');
			if (!bgqEl) return;

			var action = bgqEl.getAttribute('data-bgq');
			var val = bgqEl.getAttribute('data-value') || '';
			var thick = bgqEl.getAttribute('data-thick') || '';

			if (action === 'kh') {
				var normVal = normalizeRoleValue(val);
				document.querySelectorAll('[data-bgq="kh"]').forEach(function (btn) {
					var btnNorm = normalizeRoleValue(btn.getAttribute('data-value') || '');
					btn.setAttribute('aria-pressed', btnNorm === normVal ? 'true' : 'false');
				});
				setRadio('customer-type', normVal);
				dl().push({ event: 'quote_customer_type_select', customer_type: normVal, lead_source: '/bao-gia/' });
				return;
			}

			if (action === 'line') {
				setRadio('product-line', val);
				dl().push({ event: 'quote_product_line_select', product_line: val, lead_source: '/bao-gia/' });
				return;
			}

			if (action === 'row') {
				setRadio('product-line', val);
				appendNote('Quan tâm: ' + val + ' ' + thick + '.');

				document.querySelectorAll('.bgq-table tbody tr.is-selected, .bgq-m-card.is-selected').forEach(function (el) {
					el.classList.remove('is-selected');
				});
				var card = bgqEl.closest('.bgq-m-card');
				if (card) card.classList.add('is-selected');
				var tr = bgqEl.closest('tr');
				if (tr) tr.classList.add('is-selected');

				setFormSelectionSummary(val + ' · ' + thick);
				dl().push({
					event: 'quote_price_row_select',
					product_line: val,
					thickness: thick,
					lead_source: '/bao-gia/'
				});
				scrollToForm();
				return;
			}

			if (action === 'cta') {
				scrollToForm();
				return;
			}

			if (action === 'tel') {
				dl().push({ event: 'quote_phone_click', lead_source: '/bao-gia/' });
				return;
			}

			if (action === 'doc') {
				dl().push({ event: 'quote_document_click', lead_source: '/bao-gia/' });
			}
		});

		// Sync CF7 radio changes back to Step 1 cards/pills if user edits radios directly inside the form
		document.addEventListener('change', function (e) {
			if (e.target && e.target.name === 'customer-type') {
				var norm = normalizeRoleValue(e.target.value);
				document.querySelectorAll('[data-bgq="kh"]').forEach(function (btn) {
					var btnNorm = normalizeRoleValue(btn.getAttribute('data-value') || '');
					btn.setAttribute('aria-pressed', btnNorm === norm ? 'true' : 'false');
				});
			}
		});

		document.addEventListener('focusin', function (e) {
			if (started) return;
			var f = document.querySelector('#form-bao-gia .wpcf7-form');
			if (f && f.contains(e.target)) {
				started = true;
				dl().push({ event: 'quote_form_start', lead_source: '/bao-gia/' });
			}
		});

		document.addEventListener('submit', function (e) {
			var f = e.target;
			if (f && f.classList && f.classList.contains('wpcf7-form')) {
				var btn = f.querySelector('input.wpcf7-submit');
				if (btn) {
					btn.disabled = true;
					setTimeout(function () { btn.disabled = false; }, 8000);
				}
			}
		}, true);

		document.addEventListener('wpcf7submit', function (e) {
			var btn = e.target.querySelector('input.wpcf7-submit');
			if (btn) btn.disabled = false;
		});

		document.addEventListener('wpcf7mailsent', function () {
			dl().push({
				event: 'quote_form_submit',
				customer_type: getRadioVal('customer-type'),
				product_line: getRadioVal('product-line'),
				lead_source: '/bao-gia/'
			});
		});

		document.addEventListener('wpcf7invalid', function () {
			dl().push({ event: 'quote_form_error', error_type: 'validation', lead_source: '/bao-gia/' });
		});

		document.addEventListener('wpcf7mailfailed', function () {
			dl().push({ event: 'quote_form_error', error_type: 'mail_failed', lead_source: '/bao-gia/' });
		});
	});
})();
