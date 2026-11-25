/* Đồng Bộ Hóa Dữ Liệu — Core REST Client, Shared UI Components & SPA Router */
(function () {
    'use strict';

    const boot = window.DBHDL_BOOT || {};
    const views = {};

    const DBHDL = {
        boot: boot,
        state: null,
        activeTab: boot.initial_tab || 'overview',

        esc(val) {
            return String(val ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        formatBytes(bytes) {
            const n = Number(bytes || 0);
            if (n < 1024) return n + ' B';
            if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
            return (n / 1048576).toFixed(2) + ' MB';
        },

        async api(route, opts = {}) {
            const method = (opts.method || 'GET').toUpperCase();
            const url = boot.rest_base.replace(/\/+$/, '') + '/' + route.replace(/^\/+/, '');
            const headers = { 'X-WP-Nonce': boot.nonce };
            const fetchOpts = { method, headers, credentials: 'same-origin' };

            if (opts.formData) {
                fetchOpts.body = opts.formData;
            } else if (opts.body !== undefined) {
                headers['Content-Type'] = 'application/json';
                fetchOpts.body = JSON.stringify(opts.body);
            }

            const res = await fetch(url, fetchOpts);
            const text = await res.text();
            let json = null;
            try {
                json = text ? JSON.parse(text) : {};
            } catch (e) {
                throw new Error('Phản hồi không hợp lệ từ máy chủ (HTTP ' + res.status + ').');
            }
            if (!res.ok || (json && json.ok === false)) {
                const msg = (json && (json.error || json.message)) || ('Lỗi HTTP ' + res.status);
                throw new Error(msg);
            }
            return json;
        },

        toast(message, type = 'info') {
            const region = document.getElementById('dbhdl-toast-region');
            if (!region) return;
            const el = document.createElement('div');
            el.className = 'dbhdl-toast dbhdl-toast-' + type;
            el.textContent = message;
            region.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 250);
            }, 4500);
        },

        showProgress(title, percent, detail = '') {
            const box = document.getElementById('dbhdl-progress-region');
            if (!box) return;
            box.hidden = false;
            box.innerHTML =
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">' +
                '<strong>' + DBHDL.esc(title) + '</strong>' +
                '<span>' + Math.round(percent) + '%</span>' +
                '</div>' +
                (detail ? '<div style="font-size:12px;color:#cbd5e1;margin-top:4px;">' + DBHDL.esc(detail) + '</div>' : '') +
                '<div class="dbhdl-progress-bar"><div class="dbhdl-progress-fill" style="width:' + Math.min(100, Math.max(0, percent)) + '%"></div></div>';
        },

        hideProgress() {
            const box = document.getElementById('dbhdl-progress-region');
            if (box) {
                box.hidden = true;
                box.innerHTML = '';
            }
        },

        confirmModal(cfg) {
            return new Promise((resolve) => {
                const root = document.getElementById('dbhdl-modal-root');
                if (!root) {
                    resolve(false);
                    return;
                }
                const danger = !!cfg.danger;
                const confirmText = cfg.confirmLabel || 'Xác nhận thực hiện';
                const btnCls = danger ? 'dbhdl-btn-danger' : 'dbhdl-btn-success';
                const reqText = String(cfg.requireText || '').trim();
                const reqHtml = reqText
                    ? '<div style="margin-top:12px;padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">' +
                      '<label class="dbhdl-label" style="color:#991b1b;margin-bottom:6px;">Nhập chính xác <code style="user-select:all;">' + DBHDL.esc(reqText) + '</code> để xác nhận:</label>' +
                      '<input type="text" class="dbhdl-input dbhdl-mono" data-modal-require-input autocomplete="off" placeholder="' + DBHDL.esc(reqText) + '">' +
                      '</div>'
                    : '';

                root.hidden = false;
                root.setAttribute('aria-hidden', 'false');
                root.innerHTML =
                    '<div class="dbhdl-modal" role="dialog" aria-modal="true">' +
                    '<div class="dbhdl-modal-head">' +
                    '<h3 style="margin:0;font-size:16px;font-weight:800;">' + DBHDL.esc(cfg.title || 'Xác nhận') + '</h3>' +
                    '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-modal-cancel>&times;</button>' +
                    '</div>' +
                    '<div class="dbhdl-modal-body">' + (cfg.bodyHtml || DBHDL.esc(cfg.message || '')) + reqHtml + '</div>' +
                    '<div class="dbhdl-modal-foot">' +
                    '<button type="button" class="dbhdl-btn" data-modal-cancel>Hủy</button>' +
                    '<button type="button" class="dbhdl-btn ' + btnCls + '" data-modal-ok' + (reqText ? ' disabled' : '') + '>' + DBHDL.esc(confirmText) + '</button>' +
                    '</div>' +
                    '</div>';

                const close = (ok) => {
                    root.hidden = true;
                    root.setAttribute('aria-hidden', 'true');
                    root.innerHTML = '';
                    resolve(ok);
                };

                const okBtn = root.querySelector('[data-modal-ok]');
                const reqInput = root.querySelector('[data-modal-require-input]');
                if (reqInput && reqText) {
                    reqInput.addEventListener('input', () => {
                        okBtn.disabled = reqInput.value.trim().toLowerCase() !== reqText.toLowerCase();
                    });
                    setTimeout(() => reqInput.focus(), 30);
                }

                root.querySelectorAll('[data-modal-cancel]').forEach((b) => b.addEventListener('click', () => close(false)));
                okBtn.addEventListener('click', () => {
                    if (!okBtn.disabled) close(true);
                });
                root.addEventListener('click', (e) => {
                    if (e.target === root) close(false);
                }, { once: true });
            });
        },

        copyText(text, btn) {
            if (!text) return;
            const done = () => {
                if (btn) {
                    const prev = btn.textContent;
                    btn.textContent = '✓ Đã copy';
                    setTimeout(() => { btn.textContent = prev; }, 1400);
                }
                DBHDL.toast('Đã sao chép vào bộ nhớ tạm.', 'success');
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
                done();
            }
        },

        /**
         * Shared dynamic scope selector used across Local⇄Staging and Staging→Production tabs.
         */
        renderScopeSelector(prefix, inv, opts = {}) {
            const plugins = (inv && inv.plugins) || [];
            const themes = (inv && inv.themes) || [];
            const customDirs = (inv && inv.custom_dirs) || [];
            const mu = (inv && inv.mu_plugins) || { exists: false, file_count: 0 };
            const defaultActiveOnly = !!opts.defaultActiveOnly;

            const pluginHtml = plugins.map((p) => {
                const checked = defaultActiveOnly ? (p.active || p.is_self) : !p.is_self;
                const badge = p.active ? ' <span class="dbhdl-badge dbhdl-badge-prod">Active</span>' : '';
                return '<label class="dbhdl-check-pill">' +
                    '<input type="checkbox" class="' + prefix + '-scope-plugin" value="' + DBHDL.esc(p.slug) + '"' + (checked ? ' checked' : '') + '>' +
                    '<span><strong>' + DBHDL.esc(p.name) + '</strong>' + badge + '<br><code class="dbhdl-mono">' + DBHDL.esc(p.slug) + '</code></span>' +
                    '</label>';
            }).join('');

            const themeHtml = themes.map((t) => {
                const checked = t.active || t.is_parent;
                const badge = t.active ? ' <span class="dbhdl-badge dbhdl-badge-info">Active</span>' : (t.is_parent ? ' <span class="dbhdl-badge dbhdl-badge-neutral">Parent</span>' : '');
                return '<label class="dbhdl-check-pill">' +
                    '<input type="checkbox" class="' + prefix + '-scope-theme" value="' + DBHDL.esc(t.slug) + '"' + (checked ? ' checked' : '') + '>' +
                    '<span><strong>' + DBHDL.esc(t.name) + '</strong>' + badge + '<br><code class="dbhdl-mono">' + DBHDL.esc(t.slug) + '</code></span>' +
                    '</label>';
            }).join('');

            const customHtml = customDirs.map((d) =>
                '<label class="dbhdl-check-pill">' +
                '<input type="checkbox" class="' + prefix + '-scope-custom ' + prefix + '-scope-other" value="' + DBHDL.esc(d.slug) + '">' +
                '<span><strong>' + DBHDL.esc(d.slug) + '</strong><br><code class="dbhdl-mono">' + DBHDL.esc(d.path) + '</code></span>' +
                '</label>'
            ).join('');

            return '' +
                '<div class="dbhdl-scope-group">' +
                '<div class="dbhdl-scope-head">' +
                '<strong>🔌 Plugins (' + plugins.length + ')</strong>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-all="' + prefix + '-scope-plugin">Chọn hết</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-none="' + prefix + '-scope-plugin">Bỏ hết</button>' +
                '</div></div>' +
                '<div class="dbhdl-scope-list">' + (pluginHtml || '<span class="dbhdl-muted">Không có plugin nào.</span>') + '</div>' +
                '</div>' +
                '<div class="dbhdl-scope-group">' +
                '<div class="dbhdl-scope-head">' +
                '<strong>🎨 Giao diện / Themes (' + themes.length + ')</strong>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-all="' + prefix + '-scope-theme">Chọn hết</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-none="' + prefix + '-scope-theme">Bỏ hết</button>' +
                '</div></div>' +
                '<div class="dbhdl-scope-list">' + (themeHtml || '<span class="dbhdl-muted">Không có theme nào.</span>') + '</div>' +
                '</div>' +
                '<div class="dbhdl-scope-group">' +
                '<div class="dbhdl-scope-head">' +
                '<strong>📁 Thành phần khác</strong>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-all="' + prefix + '-scope-other">Chọn tất</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-none="' + prefix + '-scope-other">Bỏ hết</button>' +
                '</div></div>' +
                '<div class="dbhdl-scope-list">' +
                (mu.exists ? '<label class="dbhdl-check-pill"><input type="checkbox" id="' + prefix + '-scope-mu" class="' + prefix + '-scope-other" checked><span><strong>MU-Plugins</strong><br><small class="dbhdl-muted">' + mu.file_count + ' mục</small></span></label>' : '') +
                '<label class="dbhdl-check-pill"><input type="checkbox" id="' + prefix + '-scope-media" class="' + prefix + '-scope-other"><span><strong>Thư viện Media (uploads/)</strong><br><small class="dbhdl-muted">Mặc định tắt để tránh nặng &amp; đè ảnh đích</small></span></label>' +
                customHtml +
                '</div></div>';
        },

        bindScopeToggleButtons(container) {
            if (!container) return;
            container.querySelectorAll('[data-scope-all]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    container.querySelectorAll('.' + btn.getAttribute('data-scope-all')).forEach((cb) => { cb.checked = true; });
                });
            });
            container.querySelectorAll('[data-scope-none]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    container.querySelectorAll('.' + btn.getAttribute('data-scope-none')).forEach((cb) => { cb.checked = false; });
                });
            });
        },

        readScopeSelection(container, prefix) {
            const vals = (cls) => Array.from(container.querySelectorAll('.' + cls + ':checked')).map((el) => el.value);
            const mu = container.querySelector('#' + prefix + '-scope-mu');
            const media = container.querySelector('#' + prefix + '-scope-media');
            return {
                plugins: vals(prefix + '-scope-plugin'),
                themes: vals(prefix + '-scope-theme'),
                custom_dirs: vals(prefix + '-scope-custom'),
                mu_plugins: !!(mu && mu.checked),
                include_media: !!(media && media.checked),
            };
        },

        renderDbAndOptionsPanel(prefix, state, opts = {}) {
            const s = state.settings || {};
            const dbInv = (state.inventory && state.inventory.db) || {};
            const vaultCount = (state.vault_services || []).length;
            const maintChecked = opts.defaultMaint !== undefined ? !!opts.defaultMaint : !!s.enable_maintenance_mode;

            const tplTypesHtml = (dbInv.template_types || []).map((t) =>
                '<label class="dbhdl-check-pill">' +
                '<input type="checkbox" class="' + prefix + '-db-pt ' + prefix + '-db-tpl ' + prefix + '-db-all" value="' + DBHDL.esc(t.post_type) + '" checked>' +
                '<span><strong>' + DBHDL.esc(t.label) + '</strong><br><small class="dbhdl-muted">' + t.count + ' mục (<code>' + DBHDL.esc(t.post_type) + '</code>)</small></span>' +
                '</label>'
            ).join('');

            const contentTypesHtml = (dbInv.content_types || []).map((c) =>
                '<label class="dbhdl-check-pill">' +
                '<input type="checkbox" class="' + prefix + '-db-pt ' + prefix + '-db-extra ' + prefix + '-db-all" value="' + DBHDL.esc(c.post_type) + '">' +
                '<span><strong>' + DBHDL.esc(c.label) + '</strong><br><small class="dbhdl-muted">' + c.count + ' mục (<code>' + DBHDL.esc(c.post_type) + '</code>)</small></span>' +
                '</label>'
            ).join('');

            return '' +
                '<div class="dbhdl-card">' +
                '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">2. Chọn Dữ liệu Database &amp; Giao diện (An toàn tuyệt đối)</h2>' +
                '<div class="dbhdl-btn-row">' +
                '<span class="dbhdl-badge dbhdl-badge-prod">Tự khớp Slug / UUID • Bảo vệ Users &amp; Orders</span>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-all="' + prefix + '-db-all">Chọn tất</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-none="' + prefix + '-db-all">Bỏ hết</button>' +
                '</div></div>' +
                '<div class="dbhdl-scope-group"><div class="dbhdl-scope-head">' +
                '<strong>📐 Templates &amp; Thiết kế</strong>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-all="' + prefix + '-db-tpl">Chọn tất</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-none="' + prefix + '-db-tpl">Bỏ hết</button>' +
                '</div></div>' +
                '<div class="dbhdl-scope-list">' + (tplTypesHtml || '<span class="dbhdl-muted">Không phát hiện template builder.</span>') + '</div></div>' +
                '<div class="dbhdl-scope-group"><div class="dbhdl-scope-head">' +
                '<strong>📝 Nội dung &amp; Cấu trúc bổ sung (Tùy chọn)</strong>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-all="' + prefix + '-db-extra">Chọn tất</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-scope-none="' + prefix + '-db-extra">Bỏ hết</button>' +
                '</div></div>' +
                '<div class="dbhdl-scope-list">' +
                '<label class="dbhdl-check-pill"><input type="checkbox" id="' + prefix + '-db-menus" class="' + prefix + '-db-extra ' + prefix + '-db-all"><span><strong>Navigation Menus</strong><br><small class="dbhdl-muted">' + (dbInv.menus_count || 0) + ' menu điều hướng</small></span></label>' +
                '<label class="dbhdl-check-pill"><input type="checkbox" id="' + prefix + '-db-thememods" class="' + prefix + '-db-extra ' + prefix + '-db-all"><span><strong>Theme Customizer</strong><br><small class="dbhdl-muted"><code>' + DBHDL.esc(dbInv.theme_mods_key || 'theme_mods') + '</code></small></span></label>' +
                contentTypesHtml +
                '</div></div>' +
                '<div class="dbhdl-grid-2">' +
                '<div class="dbhdl-field"><label class="dbhdl-label">Options bổ sung (ngăn cách dấu phẩy, tùy chọn):</label>' +
                '<input type="text" id="' + prefix + '-db-options" class="dbhdl-input" placeholder="vd: my_theme_options, custom_settings"></div>' +
                '<div class="dbhdl-field"><label class="dbhdl-label">Xử lý URL khi sang Đích:</label>' +
                '<label class="dbhdl-check-pill"><input type="checkbox" id="' + prefix + '-search-replace" checked><span>Tự động Search &amp; Replace URL an toàn cho chuỗi serialized PHP &amp; JSON</span></label></div>' +
                '</div>' +
                '</div>' +

                '<div class="dbhdl-card">' +
                '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">3. Environment Secrets Vault &amp; Tùy chọn Triển khai</h2></div>' +
                '<div class="dbhdl-grid-2">' +
                '<div>' +
                '<label class="dbhdl-check-pill" style="margin-bottom:8px;"><input type="checkbox" id="' + prefix + '-sync-vault"><span><strong>Đồng bộ Kho Secrets / API Keys</strong> (' + vaultCount + ' dịch vụ)</span></label>' +
                '<div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12.5px;padding-left:6px;">' +
                '<label><input type="radio" name="' + prefix + '_vault_mode" value="fill_missing" checked> Chỉ bù key đích còn thiếu</label>' +
                '<label><input type="radio" name="' + prefix + '_vault_mode" value="overwrite"> Ghi đè theo trang nguồn</label>' +
                '</div></div>' +
                '<div>' +
                '<label class="dbhdl-check-pill" style="margin-bottom:8px;"><input type="checkbox" id="' + prefix + '-maint-mode"' + (maintChecked ? ' checked' : '') + '><span>Bật chế độ Bảo trì tạm thời (<code>.maintenance</code>) trong lúc cutover</span></label>' +
                '<label class="dbhdl-check-pill"><input type="checkbox" id="' + prefix + '-mirror-del"><span><strong>Mirror Delete:</strong> Xóa tệp ở Đích nếu không còn ở nguồn</span></label>' +
                '</div></div>' +
                '</div>';
        },

        readDbAndOptionsPayload(mount, prefix) {
            const pts = Array.from(mount.querySelectorAll('.' + prefix + '-db-pt:checked')).map((el) => el.value);
            const rawOpts = (mount.querySelector('#' + prefix + '-db-options')?.value || '')
                .split(',').map((x) => x.trim()).filter(Boolean);
            const vModeEl = mount.querySelector('input[name="' + prefix + '_vault_mode"]:checked');
            return {
                db_scope: {
                    post_types: pts,
                    include_menus: !!mount.querySelector('#' + prefix + '-db-menus')?.checked,
                    include_theme_mods: !!mount.querySelector('#' + prefix + '-db-thememods')?.checked,
                    options: rawOpts,
                },
                search_replace: !!mount.querySelector('#' + prefix + '-search-replace')?.checked,
                sync_vault: !!mount.querySelector('#' + prefix + '-sync-vault')?.checked,
                vault_mode: vModeEl ? vModeEl.value : 'fill_missing',
                mirror_delete: !!mount.querySelector('#' + prefix + '-mirror-del')?.checked,
                maintenance_mode: !!mount.querySelector('#' + prefix + '-maint-mode')?.checked,
            };
        },

        /**
         * Shared Visual Diff Explorer allowing filtering and per-file exclusion before applying.
         */
        renderDiffExplorer(container, diffData, stateObj) {
            if (!container) return;
            const summary = diffData.summary || {};
            const items = diffData.items || [];
            if (!stateObj.excluded) stateObj.excluded = new Set();

            const tagMap = {
                added: ['+ Mới', 'tag-added'],
                modified: ['~ Sửa', 'tag-modified'],
                deleted: ['- Xóa', 'tag-deleted'],
            };

            const draw = (filterStatus = 'all', query = '') => {
                const q = query.trim().toLowerCase();
                const filtered = items.filter((it) => {
                    if (filterStatus !== 'all' && it.status !== filterStatus) return false;
                    if (q && it.path.toLowerCase().indexOf(q) === -1) return false;
                    return true;
                });

                const rowsHtml = filtered.length
                    ? filtered.slice(0, 400).map((it) => {
                        const [label, cls] = tagMap[it.status] || [it.status, 'tag-modified'];
                        const checked = !stateObj.excluded.has(it.path);
                        return '<div class="dbhdl-diff-row">' +
                            '<label>' +
                            '<input type="checkbox" class="dbhdl-diff-file-cb" value="' + DBHDL.esc(it.path) + '"' + (checked ? ' checked' : '') + '>' +
                            '<span class="dbhdl-diff-tag ' + cls + '">' + DBHDL.esc(label) + '</span>' +
                            '<code class="dbhdl-mono">' + DBHDL.esc(it.path) + '</code>' +
                            '</label>' +
                            '<span class="dbhdl-muted">' + DBHDL.formatBytes(it.size || it.target_size || 0) + '</span>' +
                            '</div>';
                    }).join('')
                    : '<div style="padding:18px;text-align:center;" class="dbhdl-muted">Không có tệp nào khác biệt trong bộ lọc này.</div>';

                const listEl = container.querySelector('.dbhdl-diff-list');
                if (listEl) {
                    listEl.innerHTML = rowsHtml;
                    listEl.querySelectorAll('.dbhdl-diff-file-cb').forEach((cb) => {
                        cb.addEventListener('change', () => {
                            if (cb.checked) stateObj.excluded.delete(cb.value);
                            else stateObj.excluded.add(cb.value);
                        });
                    });
                }
            };

            let dbBanner = '';
            if (diffData.db_summary) {
                const dbs = diffData.db_summary;
                dbBanner = '<div class="dbhdl-alert dbhdl-alert-info" style="margin-bottom:10px;">' +
                    '<strong>🗄️ Dữ liệu DB sẽ đồng bộ:</strong> ' +
                    (dbs.posts_count || 0) + ' bài/template, ' +
                    (dbs.menus_count || 0) + ' menu, ' +
                    (dbs.options_count || 0) + ' nhóm options.' +
                    (diffData.vault_items && diffData.vault_items.length ? ' | 🔐 ' + diffData.vault_items.length + ' Secrets Vault.' : '') +
                    '</div>';
            }

            container.innerHTML =
                '<div class="dbhdl-diff-box">' +
                dbBanner +
                '<div class="dbhdl-diff-toolbar">' +
                '<div class="dbhdl-diff-filters">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-primary" data-df="all">Tất cả (' + items.length + ')</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-df="added">+ Mới (' + (summary.added || 0) + ')</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-df="modified">~ Sửa (' + (summary.modified || 0) + ')</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-df="deleted">- Xóa (' + (summary.deleted || 0) + ')</button>' +
                '<span class="dbhdl-badge dbhdl-badge-neutral">Truyền tải: ' + DBHDL.formatBytes(summary.transfer_bytes || 0) + '</span>' +
                '</div>' +
                '<input type="search" class="dbhdl-input" style="max-width:260px;" placeholder="🔍 Lọc đường dẫn tệp..." data-diff-search>' +
                '</div>' +
                '<div class="dbhdl-diff-list"></div>' +
                (items.length > 400 ? '<div class="dbhdl-muted" style="margin-top:6px;">Hiển thị 400/' + items.length + ' mục đầu tiên.</div>' : '') +
                '</div>';

            let curFilter = 'all';
            let curSearch = '';
            draw(curFilter, curSearch);

            container.querySelectorAll('[data-df]').forEach((b) => {
                b.addEventListener('click', () => {
                    curFilter = b.getAttribute('data-df');
                    container.querySelectorAll('[data-df]').forEach((x) => x.classList.remove('dbhdl-btn-primary'));
                    b.classList.add('dbhdl-btn-primary');
                    draw(curFilter, curSearch);
                });
            });
            const sInput = container.querySelector('[data-diff-search]');
            if (sInput) {
                sInput.addEventListener('input', () => {
                    curSearch = sInput.value;
                    draw(curFilter, curSearch);
                });
            }
        },

        renderSnapshotsCard(snaps) {
            const list = snaps || [];
            const snapsHtml = list.length
                ? list.map((sn) =>
                    '<div class="dbhdl-snap-item">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap;">' +
                    '<strong class="dbhdl-mono" style="font-size:12px;">' + DBHDL.esc(sn.filename) + '</strong>' +
                    '<span class="dbhdl-badge ' + (sn.has_db ? 'dbhdl-badge-info' : 'dbhdl-badge-neutral') + '">' + (sn.has_db ? 'Code + DB' : 'Code Only') + '</span>' +
                    '</div>' +
                    '<div class="dbhdl-muted" style="margin:4px 0 8px;">' + DBHDL.esc(sn.time) + ' • ' + DBHDL.esc(sn.size_mb) + ' MB • ' + sn.files_count + ' tệp</div>' +
                    '<div class="dbhdl-btn-row">' +
                    '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-primary dep-rollback" data-file="' + DBHDL.esc(sn.filename) + '">⏪ Khôi phục</button>' +
                    '<button type="button" class="dbhdl-btn dbhdl-btn-sm dep-dl-snap" data-file="' + DBHDL.esc(sn.filename) + '">⬇ Tải ZIP</button>' +
                    '<button type="button" class="dbhdl-btn dbhdl-btn-sm dep-del-snap" data-file="' + DBHDL.esc(sn.filename) + '" style="color:#b91c1c;">🗑</button>' +
                    '</div></div>'
                ).join('')
                : '<p class="dbhdl-muted">Chưa có Snapshot nào. Hệ thống tự động tạo Snapshot ngay trước mỗi lần đồng bộ.</p>';

            return '' +
                '<div class="dbhdl-card">' +
                '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🛡️ Lá chắn Bảo vệ Dữ liệu</h2></div>' +
                '<ul style="margin:0;padding-left:18px;font-size:13px;color:#475569;line-height:1.65;">' +
                '<li><strong>Snapshot kép tự động:</strong> Lưu cả File + Bản ghi DB cũ trước khi ghi đè.</li>' +
                '<li><strong>Bảo vệ Users &amp; Orders:</strong> Không bao giờ đè tài khoản, bình luận hay đơn hàng WooCommerce.</li>' +
                '<li><strong>Không lỗi Serialized:</strong> Parser thuần PHP giữ nguyên độ dài byte UTF-8.</li>' +
                '<li><strong>Bảo vệ Uploads:</strong> Thư mục Media tắt mặc định.</li>' +
                '<li><strong>Auto Cache Purge:</strong> Xóa OPcache, Elementor CSS, LiteSpeed, WP Rocket sau deploy.</li>' +
                '</ul></div>' +
                '<div class="dbhdl-card">' +
                '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">⏪ Snapshots &amp; Rollback (' + list.length + ')</h2></div>' +
                '<div class="dbhdl-snaps-list">' + snapsHtml + '</div>' +
                '</div>';
        },

        bindSnapshotActions(mount, reloadTab) {
            mount.querySelectorAll('.dep-rollback').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const file = btn.getAttribute('data-file');
                    const ok = await DBHDL.confirmModal({
                        title: 'Khôi phục Snapshot (Code + DB)',
                        bodyHtml: '<p>Bạn có chắc muốn đưa hệ thống về trạng thái trước khi đồng bộ của bản <code>' + DBHDL.esc(file) + '</code>?</p>',
                        confirmLabel: '⏪ Khôi phục ngay',
                        danger: true,
                    });
                    if (!ok) return;
                    DBHDL.showProgress('Đang khôi phục tệp & Database từ Snapshot...', 60);
                    try {
                        const res = await DBHDL.api('snapshots/rollback', { method: 'POST', body: { filename: file } });
                        DBHDL.hideProgress();
                        DBHDL.toast('Khôi phục thành công ' + res.restored_files + ' tệp!', 'success');
                        await DBHDL.refreshState();
                        DBHDL.switchTab(reloadTab || DBHDL.activeTab);
                    } catch (e) {
                        DBHDL.hideProgress();
                        DBHDL.toast(e.message, 'error');
                    }
                });
            });

            mount.querySelectorAll('.dep-dl-snap').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    try {
                        const res = await DBHDL.api('snapshots/ticket', { method: 'POST', body: { filename: btn.getAttribute('data-file') } });
                        window.location.href = res.download_url;
                    } catch (e) {
                        DBHDL.toast(e.message, 'error');
                    }
                });
            });

            mount.querySelectorAll('.dep-del-snap').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const file = btn.getAttribute('data-file');
                    const ok = await DBHDL.confirmModal({
                        title: 'Xóa bản Snapshot',
                        bodyHtml: '<p>Xóa vĩnh viễn tệp <code>' + DBHDL.esc(file) + '</code>?</p>',
                        confirmLabel: '🗑 Xóa',
                        danger: true,
                    });
                    if (!ok) return;
                    try {
                        await DBHDL.api('snapshots/delete', { method: 'POST', body: { filename: file } });
                        DBHDL.toast('Đã xóa Snapshot.', 'success');
                        await DBHDL.refreshState();
                        DBHDL.switchTab(reloadTab || DBHDL.activeTab);
                    } catch (e) {
                        DBHDL.toast(e.message, 'error');
                    }
                });
            });
        },

        registerView(tabSlug, renderer) {
            views[tabSlug] = renderer;
        },

        async refreshState() {
            const data = await DBHDL.api('status');
            DBHDL.state = data;
            const roleBadge = document.getElementById('dbhdl-header-role-badge');
            if (roleBadge && data.environment) {
                const r = data.environment.role;
                const map = {
                    local: ['Môi trường: LOCAL', 'dbhdl-badge-local'],
                    staging: ['Môi trường: STAGING', 'dbhdl-badge-staging'],
                    production: ['Môi trường: PRODUCTION', 'dbhdl-badge-prod'],
                };
                const [txt, cls] = map[r] || map.staging;
                roleBadge.textContent = txt;
                roleBadge.className = 'dbhdl-badge ' + cls;
                document.querySelectorAll('.dbhdl-pipe-node').forEach((n) => {
                    n.classList.toggle('is-current', n.getAttribute('data-pipe-role') === r);
                });
            }
            return data;
        },

        async switchTab(tabSlug) {
            if (tabSlug === 'deploy' && !views[tabSlug]) tabSlug = 'local-production';
            if (!views[tabSlug]) tabSlug = 'overview';
            DBHDL.activeTab = tabSlug;
            document.querySelectorAll('.dbhdl-tab-btn').forEach((btn) => {
                const active = btn.getAttribute('data-tab') === tabSlug;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabSlug);
                window.history.replaceState({}, '', url.toString());
            }

            const mount = document.getElementById('dbhdl-view-mount');
            if (!mount) return;
            mount.setAttribute('aria-labelledby', 'dbhdl-tab-btn-' + tabSlug);
            if (!DBHDL.state) {
                try {
                    await DBHDL.refreshState();
                } catch (err) {
                    mount.innerHTML = '<div class="dbhdl-alert dbhdl-alert-danger">' + DBHDL.esc(err.message) + '</div>';
                    return;
                }
            }
            views[tabSlug](mount, DBHDL.state);
        },
    };

    window.DBHDL = DBHDL;

    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('dbhdl-app');
        if (!app) return;
        document.querySelectorAll('.dbhdl-tab-btn').forEach((btn) => {
            btn.addEventListener('click', () => DBHDL.switchTab(btn.getAttribute('data-tab')));
        });
        DBHDL.switchTab(DBHDL.activeTab);
    });
})();
