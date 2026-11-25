(function () {
	'use strict';

	/* Reveal animations */
	if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		var els = document.querySelectorAll('.ta-home .ta-line, .ta-home .ta-app, .ta-home .ta-proj, .ta-home .ta-doc, .ta-home .ta-q, .ta-home .ta-fact');
		document.documentElement.classList.add('js');
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
		}, { rootMargin: '0px 0px -8% 0px' });
		els.forEach(function (el) { el.classList.add('ta-reveal'); io.observe(el); });
	}

	function lockScroll(locked) {
		document.documentElement.style.overflow = locked ? 'hidden' : '';
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

	/* 1. Quote Download Modal (Phone Gate) */
	var quoteModal = document.getElementById('ta-quote-modal');
	if (quoteModal) {
		var quoteForm = document.getElementById('ta-quote-download-form');
		var formView = quoteModal.querySelector('[data-ta-qview="form"]');
		var successView = quoteModal.querySelector('[data-ta-qview="success"]');
		var downloadsWrap = quoteModal.querySelector('[data-ta-qdownloads]');
		var phoneInput = document.getElementById('ta-qd-phone');
		var errorEl = quoteModal.querySelector('.ta-qmodal__error');
		var submitBtn = quoteModal.querySelector('.ta-qmodal__submit');
		var lastFocusQuote = null;

		function setQuoteError(msg) {
			if (!errorEl) return;
			if (!msg) {
				errorEl.textContent = '';
				errorEl.setAttribute('hidden', '');
			} else {
				errorEl.textContent = msg;
				errorEl.removeAttribute('hidden');
			}
		}

		function resetQuoteModal() {
			if (formView) formView.removeAttribute('hidden');
			if (successView) {
				successView.setAttribute('hidden', '');
				var st = successView.querySelector('.ta-qmodal__title');
				var sd = successView.querySelector('.ta-qmodal__desc');
				if (st) st.textContent = 'Đã mở khóa bảng báo giá!';
				if (sd) sd.textContent = 'Tệp báo giá bạn chọn đang tự động tải về. Bạn cũng có thể bấm chọn tải thêm bảng giá các dòng sản phẩm bên dưới:';
			}
			setQuoteError('');
			if (submitBtn) {
				submitBtn.disabled = false;
				var sp = submitBtn.querySelector('span');
				if (sp) sp.textContent = 'Xác nhận & Tải bảng giá';
			}
		}

		function openQuoteModal() {
			lastFocusQuote = document.activeElement;
			resetQuoteModal();
			quoteModal.removeAttribute('inert');
			quoteModal.setAttribute('aria-hidden', 'false');
			quoteModal.classList.add('is-open');
			lockScroll(true);
			if (phoneInput) {
				setTimeout(function () { phoneInput.focus(); }, 60);
			}
		}

		function closeQuoteModal() {
			quoteModal.classList.remove('is-open');
			quoteModal.setAttribute('aria-hidden', 'true');
			quoteModal.setAttribute('inert', '');
			lockScroll(false);
			if (lastFocusQuote && typeof lastFocusQuote.focus === 'function') {
				try { lastFocusQuote.focus({ preventScroll: true }); } catch (e) {}
			}
		}

		document.querySelectorAll('[data-ta-open-quote]').forEach(function (btn) {
			btn.addEventListener('click', openQuoteModal);
		});

		quoteModal.querySelectorAll('[data-ta-modal-close]').forEach(function (btn) {
			btn.addEventListener('click', closeQuoteModal);
		});

		if (quoteForm) {
			quoteForm.addEventListener('submit', function (e) {
				e.preventDefault();
				setQuoteError('');

				var rawPhone = phoneInput ? phoneInput.value.trim() : '';
				var normPhone = rawPhone.replace(/\D+/g, '');
				if (normPhone.indexOf('84') === 0) {
					normPhone = '0' + normPhone.slice(2);
				}
				if (!/^0\d{9,10}$/.test(normPhone)) {
					setQuoteError('Vui lòng nhập số điện thoại hợp lệ (10–11 chữ số, bắt đầu bằng 0).');
					if (phoneInput) phoneInput.focus();
					return;
				}

				if (typeof window.TAFQuoteConfig === 'undefined' || !window.TAFQuoteConfig.ajaxUrl) {
					setQuoteError('Hệ thống tải báo giá chưa sẵn sàng. Vui lòng thử lại sau.');
					return;
				}

				var checkedProduct = quoteForm.querySelector('input[name="product"]:checked');
				var product = checkedProduct ? checkedProduct.value : 'asia-gold';
				var hp = quoteForm.querySelector('input[name="taf_qd_company"]');

				var fd = new FormData();
				fd.append('action', 'taf_qd_submit');
				fd.append('nonce', window.TAFQuoteConfig.nonce);
				fd.append('phone', rawPhone);
				fd.append('product', product);
				fd.append('source', (window.TAFQuoteConfig && window.TAFQuoteConfig.source) || 'home_quote_modal');
				fd.append('page_url', window.location.href);
				fd.append('taf_qd_company', hp ? hp.value : '');

				if (submitBtn) {
					submitBtn.disabled = true;
					var sp = submitBtn.querySelector('span');
					if (sp) sp.textContent = 'Đang xác nhận...';
				}

				fetch(window.TAFQuoteConfig.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: fd
				})
					.then(function (res) {
						return res.json().then(function (json) {
							return { ok: res.ok, json: json };
						});
					})
					.then(function (result) {
						var payload = result.json && result.json.data ? result.json.data : null;
						if ((!result.ok || !result.json || !result.json.success) && payload && payload.leadSaved) {
							if (downloadsWrap) downloadsWrap.innerHTML = '';
							if (successView) {
								var st = successView.querySelector('.ta-qmodal__title');
								var sd = successView.querySelector('.ta-qmodal__desc');
								if (st) st.textContent = 'Đã ghi nhận số điện thoại liên hệ!';
								if (sd) sd.textContent = payload.message || 'Thông tin của bạn đã được lưu. Nhân viên Top Asia sẽ liên hệ gửi bảng giá ngay.';
								successView.removeAttribute('hidden');
							}
							if (formView) formView.setAttribute('hidden', '');
							return;
						}

						if (!result.ok || !result.json || !result.json.success || !payload || !payload.downloadUrl) {
							var msg = (payload && payload.message) || 'Không thể tải báo giá lúc này. Vui lòng thử lại.';
							throw new Error(msg);
						}

						var data = payload;
						triggerDownload(data.downloadUrl);

						if (downloadsWrap) {
							downloadsWrap.innerHTML = '';
							var list = Array.isArray(data.downloads) && data.downloads.length ? data.downloads : [{ key: product, label: 'Bảng báo giá', url: data.downloadUrl }];
							list.forEach(function (item) {
								var a = document.createElement('a');
								a.className = 'ta-qmodal__dl-item';
								a.href = item.url;
								a.innerHTML = '<span>Bảng giá ' + item.label + ' (PDF)</span><span class="ta-qmodal__dl-badge">Tải về</span>';
								downloadsWrap.appendChild(a);
							});
						}

						if (formView) formView.setAttribute('hidden', '');
						if (successView) successView.removeAttribute('hidden');
					})
					.catch(function (err) {
						setQuoteError(err.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
						if (submitBtn) {
							submitBtn.disabled = false;
							var sp = submitBtn.querySelector('span');
							if (sp) sp.textContent = 'Xác nhận & Tải bảng giá';
						}
					});
			});
		}
	}

	/* 2. Full-Screen Certificate Viewer Modal */
	var certModal = document.getElementById('ta-cert-modal');
	if (certModal) {
		var certTitle = certModal.querySelector('#ta-cert-modal-title');
		var certCaption = certModal.querySelector('[data-ta-cert-caption]');
		var certImg = certModal.querySelector('.ta-cert-modal__img');
		var certPdfLink = certModal.querySelector('[data-ta-cert-pdf-link]');
		var certStage = certModal.querySelector('[data-ta-cert-stage]');
		var certPctBtn = certModal.querySelector('[data-ta-cert-zoom="reset"]');
		var certTabs = certModal.querySelectorAll('[data-ta-cert-tab]');
		var certCloseBtn = certModal.querySelector('[data-ta-cert-close]');
		var zoom = 1;
		var lastFocusCert = null;

		function applyZoom(val) {
			zoom = Math.max(0.75, Math.min(2.5, Math.round(val * 100) / 100));
			if (certImg) {
				certImg.style.transform = zoom === 1 ? '' : 'scale(' + zoom + ')';
				certImg.classList.toggle('is-zoomed', zoom > 1);
			}
			if (certPctBtn) {
				certPctBtn.textContent = Math.round(zoom * 100) + '%';
			}
		}

		function selectCertTab(tab) {
			certTabs.forEach(function (t) {
				var active = t === tab;
				t.classList.toggle('is-active', active);
				t.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			var title = tab.getAttribute('data-cert-title') || '';
			var desc = tab.getAttribute('data-cert-desc') || '';
			var img = tab.getAttribute('data-cert-img') || '';
			var pdf = tab.getAttribute('data-cert-pdf') || '';
			if (certTitle) certTitle.textContent = title;
			if (certCaption) certCaption.textContent = desc;
			if (certImg && img) {
				certImg.src = img;
				certImg.alt = title;
			}
			if (certPdfLink) {
				if (pdf) {
					certPdfLink.href = pdf;
					certPdfLink.removeAttribute('hidden');
				} else {
					certPdfLink.setAttribute('hidden', '');
				}
			}
			applyZoom(1);
			if (certStage) certStage.scrollTop = 0;
		}

		function openCertModal() {
			lastFocusCert = document.activeElement;
			applyZoom(1);
			if (certStage) certStage.scrollTop = 0;
			certModal.removeAttribute('inert');
			certModal.setAttribute('aria-hidden', 'false');
			certModal.classList.add('is-open');
			lockScroll(true);
			if (certCloseBtn) {
				setTimeout(function () { certCloseBtn.focus(); }, 60);
			}
		}

		function closeCertModal() {
			certModal.classList.remove('is-open');
			certModal.setAttribute('aria-hidden', 'true');
			certModal.setAttribute('inert', '');
			lockScroll(false);
			applyZoom(1);
			if (lastFocusCert && typeof lastFocusCert.focus === 'function') {
				try { lastFocusCert.focus({ preventScroll: true }); } catch (e) {}
			}
		}

		document.querySelectorAll('[data-ta-open-cert]').forEach(function (btn) {
			btn.addEventListener('click', openCertModal);
		});

		if (certCloseBtn) {
			certCloseBtn.addEventListener('click', closeCertModal);
		}

		certTabs.forEach(function (tab) {
			tab.addEventListener('click', function () { selectCertTab(tab); });
		});

		certModal.querySelectorAll('[data-ta-cert-zoom]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var mode = btn.getAttribute('data-ta-cert-zoom');
				if (mode === 'in') applyZoom(zoom + 0.25);
				else if (mode === 'out') applyZoom(zoom - 0.25);
				else applyZoom(1);
			});
		});

		if (certImg) {
			certImg.addEventListener('click', function (e) {
				e.stopPropagation();
				applyZoom(zoom > 1 ? 1 : 1.5);
			});
		}

		if (certStage) {
			certStage.addEventListener('click', function (e) {
				if (e.target === certStage || e.target.classList.contains('ta-cert-modal__canvas')) {
					closeCertModal();
				}
			});
		}
	}

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		if (certModal && certModal.classList.contains('is-open')) {
			certModal.querySelector('[data-ta-cert-close]').click();
		} else if (quoteModal && quoteModal.classList.contains('is-open')) {
			quoteModal.querySelector('[data-ta-modal-close]').click();
		}
	});
})();
