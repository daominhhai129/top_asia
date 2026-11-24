/* Đồng Bộ Hóa Dữ Liệu — Tab 4 (Connections, Pairing & Vault) & Tab 5 (Logs & Diagnostics) */
(function () {
    'use strict';

    const DBHDL = window.DBHDL;

    DBHDL.registerView('settings', (mount, state) => {
        const s = state.settings || {};
        const env = state.environment || {};
        const isLocal = env.role === 'local';
        const pairSrv = state.pairing_server || {};
        const services = state.vault_services || [];
        const presets = state.vault_presets || [];

        const subpathPairs = Object.entries(s.git_subpath_map || {})
            .map(([k, v]) => k + '=' + v).join('\n');

        const legacyBanner = state.has_legacy_cfg
            ? '<div class="dbhdl-alert dbhdl-alert-info" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
              '<span>💡 Phát hiện cấu hình từ plugin <strong>ProID Sync</strong> cũ. Bạn có thể nhập nhanh sang plugin mới (không làm thay đổi dữ liệu plugin cũ).</span>' +
              '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-primary" id="cfg-import-legacy">📥 Import cấu hình cũ</button></div>'
            : '';

        const servicesHtml = services.length
            ? services.map((sv) =>
                '<div class="dbhdl-snap-item">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">' +
                '<div><strong>' + DBHDL.esc(sv.label) + '</strong> ' +
                '<span class="dbhdl-badge dbhdl-badge-neutral">' + DBHDL.esc(sv.policy) + '</span> ' +
                '<code class="dbhdl-mono">' + DBHDL.esc(sv.slug) + '</code>' +
                (sv.option ? ' • <code class="dbhdl-mono">opt:' + DBHDL.esc(sv.option) + '</code>' : '') +
                '</div>' +
                '<div class="dbhdl-btn-row">' +
                (sv.configured ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ ' + DBHDL.esc(sv.hint) + '</span>' : '<span class="dbhdl-badge dbhdl-badge-danger">Chưa có giá trị</span>') +
                (sv.configured && !sv.from_config ? '<button type="button" class="dbhdl-btn dbhdl-btn-sm v-clear" data-slug="' + DBHDL.esc(sv.slug) + '">Xóa key</button>' : '') +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm v-del" data-slug="' + DBHDL.esc(sv.slug) + '" style="color:#b91c1c;">🗑</button>' +
                '</div></div>' +
                '<input type="password" class="dbhdl-input v-key-input" data-slug="' + DBHDL.esc(sv.slug) + '" autocomplete="new-password" ' +
                (sv.from_config ? 'disabled placeholder="Đang khóa bởi hằng số ' + DBHDL.esc(sv.constant) + ' trong wp-config.php"' : 'placeholder="' + (sv.configured ? 'Đã mã hóa (' + DBHDL.esc(sv.hint) + ') — nhập để thay thế' : 'Nhập secret key mới...') + '"') +
                '></div>'
            ).join('')
            : '<div class="dbhdl-alert dbhdl-alert-warn">Kho Secrets đang trống. Bạn có thể thêm nhanh từ mẫu có sẵn hoặc khai báo biến mới bên dưới.</div>';

        const presetBtns = presets.map((pr) =>
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm v-preset" data-slug="' + DBHDL.esc(pr.slug) + '">+ ' + DBHDL.esc(pr.label) + '</button>'
        ).join('');

        mount.innerHTML =
            legacyBanner +
            '<div class="dbhdl-grid-2">' +
            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🌐 1. Môi trường &amp; Hành vi An toàn</h2></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Vai trò website hiện tại:</label>' +
            '<select id="cfg-role" class="dbhdl-select">' +
            '<option value="auto"' + (s.env_role === 'auto' ? ' selected' : '') + '>Tự động nhận diện (' + DBHDL.esc(state.environment.auto_role) + ')</option>' +
            '<option value="local"' + (s.env_role === 'local' ? ' selected' : '') + '>Local Project (Máy cá nhân)</option>' +
            '<option value="staging"' + (s.env_role === 'staging' ? ' selected' : '') + '>Staging (Môi trường thử nghiệm)</option>' +
            '<option value="production"' + (s.env_role === 'production' ? ' selected' : '') + '>Production (Website chính)</option>' +
            '</select></div>' +
            '<div class="dbhdl-grid-2">' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Giữ lại tối đa (Snapshots):</label>' +
            '<input type="number" id="cfg-retention" class="dbhdl-input" min="3" max="50" value="' + DBHDL.esc(s.snapshot_retention || 10) + '"></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Kích thước Chunk truyền tải (KB):</label>' +
            '<input type="number" id="cfg-chunk" class="dbhdl-input" min="256" max="4096" value="' + DBHDL.esc(s.chunk_size_kb || 1536) + '"></div>' +
            '</div>' +
            '<label class="dbhdl-check-pill" style="margin-bottom:8px;"><input type="checkbox" id="cfg-maint"' + (s.enable_maintenance_mode ? ' checked' : '') + '><span>Mặc định bật <code>.maintenance</code> khi Deploy Production</span></label>' +
            '<label class="dbhdl-check-pill" style="margin-bottom:12px;"><input type="checkbox" id="cfg-uninstall"' + (s.delete_data_on_uninstall ? ' checked' : '') + '><span>Xóa toàn bộ cấu hình &amp; nhật ký khi gỡ bỏ hoàn toàn plugin</span></label>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="cfg-save-general">💾 Lưu cài đặt chung</button>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🔗 2. Kết nối Đích &amp; Ghép Đôi Site-to-Site</h2>' +
            (state.staging_detection && state.staging_detection.active
                ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ Staging Tự kích hoạt</span>'
                : '<span class="dbhdl-badge dbhdl-badge-staging">🔒 Staging: cần URL chứa "staging"</span>') +
            '</div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Phương thức đẩy từ trang này sang trang đích:</label>' +
            '<select id="cfg-target-mode" class="dbhdl-select">' +
            '<option value="same_server"' + (s.target_mode === 'same_server' ? ' selected' : '') + '>Cùng máy chủ (Same-Server Path + Cross-DB)</option>' +
            '<option value="remote_pairing"' + (s.target_mode === 'remote_pairing' ? ' selected' : '') + '>Khác máy chủ / Local → Staging (Remote HTTPS Pairing)</option>' +
            '</select></div>' +
            '<div id="cfg-same-box"' + (s.target_mode === 'remote_pairing' ? ' hidden' : '') + '>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Đường dẫn thư mục WordPress đích (ABSPATH):</label>' +
            '<div class="dbhdl-input-row"><input type="text" id="cfg-target-path" class="dbhdl-input dbhdl-mono" value="' + DBHDL.esc(s.target_path || '') + '" placeholder="/home/user/public_html">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-detect-paths">🔍 Tự tìm</button></div></div>' +
            '<div id="cfg-detected-list" style="margin-bottom:10px;"></div>' +
            '</div>' +
            '<div id="cfg-remote-box"' + (s.target_mode !== 'remote_pairing' ? ' hidden' : '') + '>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Pairing Token của trang đích ' + (s.target_pairing_configured ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ ' + DBHDL.esc(s.target_pairing_site) + '</span>' : '') + ':</label>' +
            '<textarea id="cfg-pair-token" class="dbhdl-textarea dbhdl-mono" rows="2" placeholder="Dán Pairing Token được tạo ở trang đích (Staging hoặc Production)..."></textarea></div>' +
            '</div>' +
            '<div class="dbhdl-btn-row" style="margin-bottom:14px;">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="cfg-save-target">💾 Lưu Đích</button>' +
            '<button type="button" class="dbhdl-btn" id="cfg-test-target">⚡ Test kết nối Đích</button>' +
            '</div>' +
            (isLocal
                ? '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;margin-bottom:14px;">' +
                  '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">' +
                  '<strong style="font-size:13px;color:#9a3412;">🌐 Đẩy trực tiếp Local → Production (Bỏ qua Staging)</strong>' +
                  (s.prod_pairing_configured ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ ' + DBHDL.esc(s.prod_pairing_site) + '</span>' : '<span class="dbhdl-badge dbhdl-badge-neutral">Chưa ghép đôi</span>') +
                  '</div>' +
                  '<label class="dbhdl-check-pill" style="margin-bottom:8px;"><input type="checkbox" id="cfg-allow-local-prod"' + (s.allow_local_to_prod !== false ? ' checked' : '') + '><span>Cho phép chọn Production làm đích triển khai trực tiếp từ Local</span></label>' +
                  '<div class="dbhdl-field" style="margin-bottom:8px;"><label class="dbhdl-label">Production Pairing Token (tạo ở trang Production Live):</label>' +
                  '<textarea id="cfg-prod-pair-token" class="dbhdl-textarea dbhdl-mono" rows="2" placeholder="Dán Pairing Token được tạo ở site Production (topasia.vn)..."></textarea></div>' +
                  '<div class="dbhdl-btn-row">' +
                  '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-primary" id="cfg-save-prod-target">💾 Lưu Đích Production</button>' +
                  '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-test-prod-target">⚡ Test kết nối Production</button>' +
                  (s.prod_pairing_configured ? '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-danger" id="cfg-clear-prod-target">Xóa Token Prod</button>' : '') +
                  '</div></div>'
                : '') +
            '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;">' +
            '<strong style="font-size:13px;">📡 Cho phép site khác đẩy vào site này (Pairing Receiver):</strong>' +
            '<div class="dbhdl-muted" style="margin:4px 0 8px;">Trạng thái: ' + (pairSrv.enabled ? '<span class="dbhdl-badge dbhdl-badge-prod">Đang bật (' + DBHDL.esc(pairSrv.pair_id) + ')</span>' : '<span class="dbhdl-badge dbhdl-badge-neutral">Đang tắt</span>') + '</div>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-success" id="cfg-gen-pairing">🔑 Tạo Pairing Token mới</button>' +
            (pairSrv.enabled ? '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-danger" id="cfg-rev-pairing">Thu hồi quyền Pairing</button>' : '') +
            '</div>' +
            '<div id="cfg-new-pair-box" hidden style="margin-top:10px;"></div>' +
            '</div></div></div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🐙 3. Cấu hình GitHub Repository &amp; Auto-Pull Webhook</h2>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-test-git">⚡ Test kết nối GitHub</button></div>' +
            '<div class="dbhdl-grid-2">' +
            '<div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Repository (owner/repo):</label>' +
            '<input type="text" id="cfg-git-repo" class="dbhdl-input dbhdl-mono" value="' + DBHDL.esc(s.git_repo || '') + '" placeholder="username/my-wordpress-site"></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Nhánh (Branch):</label>' +
            '<div class="dbhdl-input-row"><input type="text" id="cfg-git-branch" class="dbhdl-input dbhdl-mono" value="' + DBHDL.esc(s.git_branch || 'main') + '">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-load-branches">Tải nhánh</button></div>' +
            '<div id="cfg-branches-wrap" style="margin-top:6px;"></div></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Personal Access Token ' + (s.git_token_configured ? '<span class="dbhdl-badge dbhdl-badge-prod">Đã lưu (' + DBHDL.esc(s.git_token_hint) + ')</span>' : '') + ':</label>' +
            '<input type="password" id="cfg-git-token" class="dbhdl-input dbhdl-mono" autocomplete="new-password" placeholder="ghp_... (để trống nếu giữ nguyên token cũ)"></div>' +
            '<div class="dbhdl-grid-2">' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Commit Author Name:</label><input type="text" id="cfg-git-aname" class="dbhdl-input" value="' + DBHDL.esc(s.git_author_name || '') + '"></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Commit Author Email:</label><input type="email" id="cfg-git-aemail" class="dbhdl-input" value="' + DBHDL.esc(s.git_author_email || '') + '"></div>' +
            '</div>' +
            '</div>' +
            '<div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Ánh xạ thư mục Repo ↔ WordPress (tùy chọn, mỗi dòng <code>repo_dir=wp_dir</code>):</label>' +
            '<textarea id="cfg-git-submap" class="dbhdl-textarea dbhdl-mono" rows="3" placeholder="vd: .=wp-content/themes/my-theme&#10;src/plugin=wp-content/plugins/my-plugin">' + DBHDL.esc(subpathPairs) + '</textarea></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Webhook Endpoint (cho GitHub / CI Auto-Pull về Staging):</label>' +
            '<div class="dbhdl-input-row"><input type="text" readonly class="dbhdl-input dbhdl-mono" value="' + DBHDL.esc(s.webhook_url || '') + '">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-copy-wh">Copy</button></div></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Webhook HMAC Secret:</label>' +
            '<div class="dbhdl-input-row"><input type="text" readonly id="cfg-wh-sec" class="dbhdl-input dbhdl-mono" value="' + DBHDL.esc(s.webhook_secret_raw || '') + '">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-regen-wh">🔄 Đổi khóa</button></div></div>' +
            '<label class="dbhdl-check-pill"><input type="checkbox" id="cfg-wh-enabled"' + (s.webhook_auto_pull_enabled ? ' checked' : '') + '><span>Bật Webhook Auto-Pull trên môi trường Staging/Local</span></label>' +
            '</div></div>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="cfg-save-git" style="margin-top:10px;">💾 Lưu cấu hình GitHub &amp; Webhook</button>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🔐 4. Kho Biến Môi Trường &amp; API Secrets (Mã hóa tại DB)</h2>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-primary" id="v-save-batch">💾 Lưu tất cả Key vừa nhập</button></div>' +
            '<div class="dbhdl-btn-row" style="margin-bottom:12px;"><span class="dbhdl-muted">Thêm nhanh mẫu:</span>' + presetBtns + '</div>' +
            '<div id="v-list">' + servicesHtml + '</div>' +
            '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-top:14px;">' +
            '<strong style="display:block;margin-bottom:10px;">➕ Khai báo biến / API Key tùy biến mới</strong>' +
            '<div class="dbhdl-grid-4">' +
            '<input type="text" id="v-new-label" class="dbhdl-input" placeholder="Tên (vd: Stripe Secret)">' +
            '<input type="text" id="v-new-slug" class="dbhdl-input dbhdl-mono" placeholder="slug (vd: stripe_key)">' +
            '<input type="text" id="v-new-opt" class="dbhdl-input dbhdl-mono" placeholder="wp_option (tùy chọn)">' +
            '<select id="v-new-policy" class="dbhdl-select"><option value="sync">Policy: Đồng bộ sang Đích</option><option value="fill_missing">Policy: Chỉ bù nếu Đích thiếu</option><option value="keep_target">Policy: Giữ riêng từng môi trường</option></select>' +
            '</div>' +
            '<div class="dbhdl-input-row" style="margin-top:10px;">' +
            '<input type="password" id="v-new-secret" class="dbhdl-input dbhdl-mono" autocomplete="new-password" placeholder="Giá trị Secret ban đầu (tùy chọn)">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="v-add-btn">➕ Thêm vào Kho</button>' +
            '</div></div></div>';

        const modeSel = mount.querySelector('#cfg-target-mode');
        modeSel.addEventListener('change', () => {
            mount.querySelector('#cfg-same-box').hidden = modeSel.value === 'remote_pairing';
            mount.querySelector('#cfg-remote-box').hidden = modeSel.value !== 'remote_pairing';
        });

        const impBtn = mount.querySelector('#cfg-import-legacy');
        if (impBtn) {
            impBtn.addEventListener('click', async () => {
                try {
                    const res = await DBHDL.api('settings/import-legacy', { method: 'POST' });
                    DBHDL.toast(res.message, 'success');
                    await DBHDL.refreshState();
                    DBHDL.switchTab('settings');
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        mount.querySelector('#cfg-save-general').addEventListener('click', async () => {
            try {
                await DBHDL.api('settings', {
                    method: 'POST',
                    body: {
                        env_role: mount.querySelector('#cfg-role').value,
                        snapshot_retention: Number(mount.querySelector('#cfg-retention').value),
                        chunk_size_kb: Number(mount.querySelector('#cfg-chunk').value),
                        enable_maintenance_mode: mount.querySelector('#cfg-maint').checked,
                        delete_data_on_uninstall: mount.querySelector('#cfg-uninstall').checked,
                    },
                });
                DBHDL.toast('Đã lưu cài đặt môi trường!', 'success');
                await DBHDL.refreshState();
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#cfg-detect-paths').addEventListener('click', async () => {
            try {
                const res = await DBHDL.api('target/detect');
                const wrap = mount.querySelector('#cfg-detected-list');
                const list = res.siblings || [];
                wrap.innerHTML = list.length
                    ? list.map((it) => '<button type="button" class="dbhdl-btn dbhdl-btn-sm cfg-pick-path" data-p="' + DBHDL.esc(it.path) + '" style="margin:2px;">📁 ' + DBHDL.esc(it.path) + ' (' + DBHDL.esc(it.site_url || it.relation) + ')</button>').join('')
                    : '<span class="dbhdl-muted">Không tự phát hiện thư mục WordPress nào khác gần đây.</span>';
                wrap.querySelectorAll('.cfg-pick-path').forEach((b) => b.addEventListener('click', () => {
                    mount.querySelector('#cfg-target-path').value = b.getAttribute('data-p');
                }));
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#cfg-save-target').addEventListener('click', async () => {
            try {
                const body = {
                    target_mode: modeSel.value,
                    target_path: mount.querySelector('#cfg-target-path').value,
                };
                const tok = mount.querySelector('#cfg-pair-token').value.trim();
                if (tok) body.target_pairing_token = tok;
                await DBHDL.api('settings', { method: 'POST', body });
                DBHDL.toast('Đã lưu cấu hình Đích!', 'success');
                await DBHDL.refreshState();
                DBHDL.switchTab('settings');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#cfg-test-target').addEventListener('click', async () => {
            try {
                const res = await DBHDL.api('target/test', {
                    method: 'POST',
                    body: {
                        target_mode: modeSel.value,
                        target_path: mount.querySelector('#cfg-target-path').value,
                        pairing_token: mount.querySelector('#cfg-pair-token').value.trim(),
                    },
                });
                DBHDL.toast(res.message || ('Kết nối thành công tới ' + (res.site_url || res.path)), 'success');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        const saveProdBtn = mount.querySelector('#cfg-save-prod-target');
        if (saveProdBtn) {
            saveProdBtn.addEventListener('click', async () => {
                try {
                    const body = {
                        allow_local_to_prod: mount.querySelector('#cfg-allow-local-prod').checked,
                    };
                    const tok = mount.querySelector('#cfg-prod-pair-token').value.trim();
                    if (tok) body.prod_pairing_token = tok;
                    await DBHDL.api('settings', { method: 'POST', body });
                    DBHDL.toast('Đã lưu cấu hình Đích Production!', 'success');
                    await DBHDL.refreshState();
                    DBHDL.switchTab('settings');
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        const testProdBtn = mount.querySelector('#cfg-test-prod-target');
        if (testProdBtn) {
            testProdBtn.addEventListener('click', async () => {
                try {
                    const res = await DBHDL.api('target/test', {
                        method: 'POST',
                        body: {
                            target_slot: 'production',
                            target_mode: 'remote_pairing',
                            pairing_token: mount.querySelector('#cfg-prod-pair-token').value.trim(),
                        },
                    });
                    DBHDL.toast(res.message || ('Kết nối thành công tới ' + (res.site_url || '')), 'success');
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        const clearProdBtn = mount.querySelector('#cfg-clear-prod-target');
        if (clearProdBtn) {
            clearProdBtn.addEventListener('click', async () => {
                try {
                    await DBHDL.api('settings', { method: 'POST', body: { clear_prod_pairing: true } });
                    DBHDL.toast('Đã xóa Production Pairing Token.', 'info');
                    await DBHDL.refreshState();
                    DBHDL.switchTab('settings');
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        mount.querySelector('#cfg-gen-pairing').addEventListener('click', async () => {
            try {
                const res = await DBHDL.api('pairing/generate', { method: 'POST' });
                const box = mount.querySelector('#cfg-new-pair-box');
                box.hidden = false;
                box.innerHTML = '<div class="dbhdl-alert dbhdl-alert-success">' +
                    '<strong>Pairing Token (copy và dán vào site nguồn Local/Staging):</strong>' +
                    '<div class="dbhdl-input-row" style="margin-top:6px;">' +
                    '<input type="text" readonly class="dbhdl-input dbhdl-mono" value="' + DBHDL.esc(res.token) + '">' +
                    '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="cfg-copy-pair">Copy</button>' +
                    '</div></div>';
                box.querySelector('#cfg-copy-pair').addEventListener('click', (e) => DBHDL.copyText(res.token, e.currentTarget));
                DBHDL.toast('Đã tạo Pairing Token mới!', 'success');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        const revBtn = mount.querySelector('#cfg-rev-pairing');
        if (revBtn) {
            revBtn.addEventListener('click', async () => {
                await DBHDL.api('pairing/revoke', { method: 'POST' });
                DBHDL.toast('Đã thu hồi quyền Pairing.', 'info');
                await DBHDL.refreshState();
                DBHDL.switchTab('settings');
            });
        }

        mount.querySelector('#cfg-save-git').addEventListener('click', async () => {
            const submap = {};
            (mount.querySelector('#cfg-git-submap').value || '').split('\n').forEach((line) => {
                const idx = line.indexOf('=');
                if (idx > 0) submap[line.slice(0, idx).trim()] = line.slice(idx + 1).trim();
            });
            const body = {
                git_repo: mount.querySelector('#cfg-git-repo').value,
                git_branch: mount.querySelector('#cfg-git-branch').value,
                git_author_name: mount.querySelector('#cfg-git-aname').value,
                git_author_email: mount.querySelector('#cfg-git-aemail').value,
                git_subpath_map: submap,
                webhook_auto_pull_enabled: mount.querySelector('#cfg-wh-enabled').checked,
            };
            const gt = mount.querySelector('#cfg-git-token').value.trim();
            if (gt) body.git_token = gt;
            try {
                await DBHDL.api('settings', { method: 'POST', body });
                DBHDL.toast('Đã lưu cấu hình GitHub!', 'success');
                await DBHDL.refreshState();
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#cfg-test-git').addEventListener('click', async () => {
            try {
                const res = await DBHDL.api('git/test', {
                    method: 'POST',
                    body: {
                        repo: mount.querySelector('#cfg-git-repo').value,
                        token: mount.querySelector('#cfg-git-token').value.trim(),
                    },
                });
                DBHDL.toast(res.message, 'success');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#cfg-load-branches').addEventListener('click', async () => {
            try {
                const res = await DBHDL.api('git/branches');
                const w = mount.querySelector('#cfg-branches-wrap');
                w.innerHTML = (res.branches || []).map((b) =>
                    '<button type="button" class="dbhdl-btn dbhdl-btn-sm cfg-pick-br" data-b="' + DBHDL.esc(b.name) + '" style="margin:2px;">' + DBHDL.esc(b.name) + '</button>'
                ).join('');
                w.querySelectorAll('.cfg-pick-br').forEach((btn) => btn.addEventListener('click', () => {
                    mount.querySelector('#cfg-git-branch').value = btn.getAttribute('data-b');
                }));
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#cfg-copy-wh').addEventListener('click', (e) => DBHDL.copyText(s.webhook_url, e.currentTarget));
        mount.querySelector('#cfg-regen-wh').addEventListener('click', async () => {
            try {
                const res = await DBHDL.api('git/regen-webhook', { method: 'POST' });
                mount.querySelector('#cfg-wh-sec').value = res.webhook_secret;
                DBHDL.toast('Đã xoay vòng khóa Webhook!', 'success');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        // Vault handlers
        mount.querySelectorAll('.v-preset').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const p = presets.find((x) => x.slug === btn.getAttribute('data-slug'));
                if (!p) return;
                await DBHDL.api('vault/service', { method: 'POST', body: p });
                DBHDL.toast('Đã thêm mẫu ' + p.label, 'success');
                await DBHDL.refreshState();
                DBHDL.switchTab('settings');
            });
        });

        mount.querySelector('#v-add-btn').addEventListener('click', async () => {
            try {
                await DBHDL.api('vault/service', {
                    method: 'POST',
                    body: {
                        label: mount.querySelector('#v-new-label').value,
                        slug: mount.querySelector('#v-new-slug').value,
                        option: mount.querySelector('#v-new-opt').value,
                        policy: mount.querySelector('#v-new-policy').value,
                        secret: mount.querySelector('#v-new-secret').value,
                    },
                });
                DBHDL.toast('Đã thêm dịch vụ vào Kho Bảo mật!', 'success');
                await DBHDL.refreshState();
                DBHDL.switchTab('settings');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#v-save-batch').addEventListener('click', async () => {
            const keys = {};
            mount.querySelectorAll('.v-key-input').forEach((inp) => {
                if (!inp.disabled && inp.value.trim()) keys[inp.getAttribute('data-slug')] = inp.value.trim();
            });
            try {
                await DBHDL.api('vault/save-batch', { method: 'POST', body: { keys } });
                DBHDL.toast('Đã mã hóa và lưu Keys thành công!', 'success');
                await DBHDL.refreshState();
                DBHDL.switchTab('settings');
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelectorAll('.v-clear').forEach((btn) => btn.addEventListener('click', async () => {
            await DBHDL.api('vault/save-batch', { method: 'POST', body: { clear_slugs: [btn.getAttribute('data-slug')] } });
            await DBHDL.refreshState();
            DBHDL.switchTab('settings');
        }));

        mount.querySelectorAll('.v-del').forEach((btn) => btn.addEventListener('click', async () => {
            await DBHDL.api('vault/service/delete', { method: 'POST', body: { slug: btn.getAttribute('data-slug') } });
            await DBHDL.refreshState();
            DBHDL.switchTab('settings');
        }));
    });

    DBHDL.registerView('logs', (mount, state) => {
        const d = state.diagnostics || {};
        const logs = state.logs || [];

        const rowsHtml = logs.map((l) =>
            '<tr>' +
            '<td>' + DBHDL.esc(l.time) + '</td>' +
            '<td>' + DBHDL.esc(l.actor) + '</td>' +
            '<td><span class="dbhdl-badge ' + (l.status === 'success' ? 'dbhdl-badge-prod' : 'dbhdl-badge-danger') + '">' + DBHDL.esc(l.action) + '</span></td>' +
            '<td>' + DBHDL.esc(l.direction) + '</td>' +
            '<td>' + DBHDL.esc(l.summary) + '</td>' +
            '<td>' + (l.snapshot ? '<code class="dbhdl-mono">' + DBHDL.esc(l.snapshot) + '</code>' : '—') + '</td>' +
            '</tr>'
        ).join('');

        mount.innerHTML =
            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🩺 Kiểm tra Sức khỏe &amp; Bảo mật Máy chủ</h2></div>' +
            '<div class="dbhdl-grid-4">' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">PHP / Memory</div><div class="dbhdl-kpi-val">' + DBHDL.esc(d.php_version) + '</div><div class="dbhdl-kpi-sub">Limit: ' + DBHDL.esc(d.memory_limit) + ' • Timeout: ' + DBHDL.esc(d.max_execution_time) + 's</div></div>' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Nén &amp; Mã hóa</div><div class="dbhdl-kpi-val">' + (d.zip_archive ? '✔ ZipArchive' : '✖ Thiếu ZIP') + '</div><div class="dbhdl-kpi-sub">' + (d.libsodium ? '✔ Libsodium XChaCha20' : (d.openssl ? '✔ OpenSSL AES-GCM' : '✖ Thiếu Crypto')) + '</div></div>' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Thư mục Snapshot</div><div class="dbhdl-kpi-val">' + (d.outside_webroot ? '✔ Ngoài Webroot' : '🛡️ Trong wp-content') + '</div><div class="dbhdl-kpi-sub">' + (d.snapshot_writable ? '✔ Có quyền ghi' : '✖ Không ghi được') + '</div></div>' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Quyền wp-content</div><div class="dbhdl-kpi-val">' + (d.wp_content_writable ? '✔ Sẵn sàng' : '✖ Chỉ đọc') + '</div><div class="dbhdl-kpi-sub">Max upload: ' + DBHDL.esc(d.upload_max_size) + '</div></div>' +
            '</div>' +
            '<div class="dbhdl-muted">Đường dẫn Snapshot: <code class="dbhdl-mono">' + DBHDL.esc(d.snapshot_dir) + '</code></div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📜 Lịch sử Đồng bộ (Audit Log)</h2>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="logs-clear">🗑 Xóa nhật ký</button></div>' +
            '<div class="dbhdl-table-wrap"><table class="dbhdl-table"><thead><tr><th>Thời gian</th><th>Người dùng</th><th>Loại</th><th>Hướng</th><th>Nội dung</th><th>Snapshot</th></tr></thead><tbody>' +
            (rowsHtml || '<tr><td colspan="6" class="dbhdl-muted">Chưa có nhật ký nào.</td></tr>') +
            '</tbody></table></div></div>';

        mount.querySelector('#logs-clear').addEventListener('click', async () => {
            const ok = await DBHDL.confirmModal({
                title: 'Xóa lịch sử nhật ký',
                bodyHtml: '<p>Xóa toàn bộ nhật ký hoạt động? (Không ảnh hưởng tới các bản Snapshot.)</p>',
                confirmLabel: 'Xóa nhật ký',
                danger: true,
            });
            if (!ok) return;
            await DBHDL.api('logs/clear', { method: 'POST' });
            await DBHDL.refreshState();
            DBHDL.switchTab('logs');
        });
    });
})();
