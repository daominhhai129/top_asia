/* Đồng Bộ Hóa Dữ Liệu — Local → Production & GitHub → Staging / Production Modules */
(function () {
    'use strict';

    const DBHDL = window.DBHDL;

    /* ======================================================================
       4. MODULE: LOCAL → PRODUCTION (Cũng dùng cho Staging → Production)
       ====================================================================== */
    DBHDL.registerView('local-production', (mount, state) => {
        const s = state.settings || {};
        const env = state.environment || {};
        const inv = state.inventory || {};
        const diffState = { excluded: new Set() };

        const isProd = env.role === 'production';
        const isLocal = env.role === 'local';
        const targetSlot = isLocal ? 'production' : 'staging';
        const targetSite = isLocal
            ? (s.prod_pairing_site || '')
            : (s.target_mode === 'remote_pairing' ? (s.target_pairing_site || '') : (s.target_path || ''));
        const isConfigured = isLocal ? !!s.prod_pairing_configured : (s.target_mode === 'remote_pairing' ? !!s.target_pairing_configured : !!s.target_path);
        const allowLocalProd = s.allow_local_to_prod !== false;
        const canPush = !isProd && isConfigured && (!isLocal || allowLocalProd);

        let topBanner = '';
        if (isProd) {
            topBanner =
                '<div class="dbhdl-alert dbhdl-alert-info"><strong>🔒 Đang đứng trên trang Production:</strong> Nút đẩy đi được khóa an toàn để tránh ghi ngược từ Production. Bạn có thể dùng cột bên phải để khôi phục Snapshot, hoặc mở tab <strong>GitHub → Staging / Prod</strong> để kéo bản cập nhật từ GitHub về đây.</div>';
        } else if (isLocal) {
            topBanner =
                '<div class="dbhdl-card" style="border:1.5px solid ' + (isConfigured ? '#10b981' : '#f59e0b') + ';margin-bottom:14px;">' +
                '<div class="dbhdl-card-head" style="margin-bottom:8px;">' +
                '<div><h2 class="dbhdl-card-title">🌐 Module Riêng: Local → Production (Trực tiếp)</h2>' +
                '<div class="dbhdl-muted">Đẩy trực tiếp Code, Giao diện, Menu, Customizer, Database &amp; Secrets từ Local lên Website Live qua HTTPS Pairing có chữ ký HMAC-SHA256.</div></div>' +
                '<div class="dbhdl-btn-row">' +
                (isConfigured
                    ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ Production: ' + DBHDL.esc(targetSite) + '</span>'
                    : '<span class="dbhdl-badge dbhdl-badge-danger">Chưa ghép đôi Production</span>') +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="lp-test-conn"' + (!isConfigured ? ' disabled' : '') + '>⚡ Test kết nối Production</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="lp-go-cfg">⚙️ Cấu hình Production Token</button>' +
                '</div></div>' +
                (!isConfigured
                    ? '<div class="dbhdl-alert dbhdl-alert-warn" style="margin:8px 0 0;">Vui lòng vào tab <strong>Kết nối &amp; Bảo mật</strong> (mục Production Pairing Token) và dán Token được tạo từ trang Production (vd: <code>https://topasia.vn</code>) để bật luồng đẩy này.</div>'
                    : '<div class="dbhdl-alert dbhdl-alert-danger" style="margin:8px 0 0;"><strong>🛡️ Bảo vệ Production:</strong> Hệ thống luôn tạo Snapshot (Code + DB) trên Production trước khi ghi và yêu cầu nhập lại đúng tên miền Production khi xác nhận Deploy.</div>') +
                '</div>';
        } else {
            topBanner =
                '<div class="dbhdl-alert dbhdl-alert-info" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
                '<span><strong>🚀 Staging → Production:</strong> Đẩy bản kiểm thử trên Staging sang Production (' + DBHDL.esc(targetSite || 'Chưa cấu hình') + ').</span>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="lp-go-cfg">⚙️ Cấu hình Đích</button></div>';
        }

        mount.innerHTML =
            topBanner +
            '<div class="dbhdl-grid-deploy">' +
            '<div>' +
            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">1. Chọn Mã nguồn đồng bộ lên Production</h2>' +
            '<span class="dbhdl-badge dbhdl-badge-prod">' + DBHDL.esc(targetSite || 'Chưa kết nối Production') + '</span></div>' +
            '<div id="lp-scope-wrap">' + DBHDL.renderScopeSelector('lp', inv, { defaultActiveOnly: false }) + '</div>' +
            '</div>' +

            DBHDL.renderDbAndOptionsPanel('lp', state, { defaultMaint: true }) +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🚀 4. Kiểm tra Diff &amp; Đẩy lên Production</h2></div>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn" id="lp-btn-diff"' + (!canPush ? ' disabled' : '') + '>🔍 Kiểm tra Diff với Production (Dry-Run)</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-success" id="lp-btn-run"' + (!canPush ? ' disabled' : '') + '>🌐 Tạo Snapshot &amp; Đẩy lên Production</button>' +
            '</div>' +
            '<div id="lp-diff-mount"></div>' +
            '<div id="lp-result-mount" style="margin-top:14px;"></div>' +
            '</div>' +
            '</div>' +

            '<div>' + DBHDL.renderSnapshotsCard(state.snapshots || []) + '</div>' +
            '</div>';

        const scopeWrap = mount.querySelector('#lp-scope-wrap');
        DBHDL.bindScopeToggleButtons(scopeWrap);
        DBHDL.bindSnapshotActions(mount, 'local-production');

        const goCfg = mount.querySelector('#lp-go-cfg');
        if (goCfg) goCfg.addEventListener('click', () => DBHDL.switchTab('settings'));

        const testBtn = mount.querySelector('#lp-test-conn');
        if (testBtn) {
            testBtn.addEventListener('click', async () => {
                try {
                    const res = await DBHDL.api('target/test', {
                        method: 'POST',
                        body: { target_slot: 'production', target_mode: 'remote_pairing' },
                    });
                    DBHDL.toast(res.message || ('Kết nối thành công tới ' + (res.site_url || '')), 'success');
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        const buildPayload = () => Object.assign(
            {
                target_slot: targetSlot,
                scope: DBHDL.readScopeSelection(scopeWrap, 'lp'),
                excluded_files: Array.from(diffState.excluded),
            },
            DBHDL.readDbAndOptionsPayload(mount, 'lp')
        );

        mount.querySelector('#lp-btn-diff').addEventListener('click', async () => {
            diffState.excluded.clear();
            DBHDL.showProgress('Đang đối chiếu File & Database với Production...', 50);
            try {
                const res = await DBHDL.api('deploy/diff', { method: 'POST', body: buildPayload() });
                DBHDL.hideProgress();
                DBHDL.renderDiffExplorer(mount.querySelector('#lp-diff-mount'), res, diffState);
                DBHDL.toast('Hoàn tất phân tích Diff với Production!', 'success');
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
            }
        });

        mount.querySelector('#lp-btn-run').addEventListener('click', async () => {
            const payload = buildPayload();
            let prodHost = '';
            if (isLocal && targetSite) {
                try { prodHost = new URL(targetSite).hostname; } catch (e) { prodHost = targetSite; }
            }

            const ok = await DBHDL.confirmModal({
                title: isLocal ? '⚠️ Xác nhận Đẩy TRỰC TIẾP Local → Production' : 'Xác nhận Deploy lên Production',
                bodyHtml: '<p>Đích triển khai: <strong>' + DBHDL.esc(targetSite) + '</strong></p>' +
                    '<ul style="padding-left:18px;">' +
                    '<li>Tự động tạo <strong>Snapshot (Code + DB)</strong> trên Production trước khi ghi.</li>' +
                    '<li>Số loại dữ liệu DB đã chọn: <strong>' + payload.db_scope.post_types.length + '</strong></li>' +
                    (payload.mirror_delete ? '<li style="color:#dc2626;"><strong>Cảnh báo:</strong> Đang bật Mirror Delete (sẽ xóa file thừa trên Production).</li>' : '') +
                    '</ul>',
                confirmLabel: '🌐 Đẩy lên Production ngay',
                danger: isLocal || payload.mirror_delete,
                requireText: isLocal ? prodHost : '',
            });
            if (!ok) return;

            if (isLocal) {
                payload.confirm_direct_prod = true;
                payload.confirm_target_host = prodHost;
            }

            DBHDL.showProgress('Đang đóng gói, tạo Snapshot và đẩy lên Production...', 65, 'Vui lòng giữ tab mở cho tới khi hoàn tất.');
            try {
                const res = await DBHDL.api('deploy/run', { method: 'POST', body: payload });
                DBHDL.hideProgress();
                const resBox = mount.querySelector('#lp-result-mount');
                if (res.no_changes) {
                    resBox.innerHTML = '<div class="dbhdl-alert dbhdl-alert-info">' + DBHDL.esc(res.message) + '</div>';
                } else {
                    const dbS = res.db_stats || {};
                    resBox.innerHTML = '<div class="dbhdl-alert dbhdl-alert-success">' +
                        '<strong>🎉 ĐỒNG BỘ PRODUCTION THÀNH CÔNG!</strong><br>' +
                        '• Snapshot trên Production: <code>' + DBHDL.esc(res.snapshot) + '</code><br>' +
                        '• Tệp tin: <strong>' + DBHDL.esc(res.copied ?? res.updated_count ?? 0) + '</strong> cập nhật, <strong>' + DBHDL.esc(res.deleted ?? res.deleted_count ?? 0) + '</strong> xóa.<br>' +
                        '• Database: <strong>' + ((dbS.posts_updated || 0) + (dbS.posts_created || 0)) + '</strong> bài/template, <strong>' + (dbS.menus_synced || 0) + '</strong> menu, <strong>' + (dbS.options_synced || 0) + '</strong> options.' +
                        '</div>';
                }
                DBHDL.toast('Triển khai Production thành công!', 'success');
                await DBHDL.refreshState();
            } catch (e) {
                DBHDL.hideProgress();
                mount.querySelector('#lp-result-mount').innerHTML =
                    '<div class="dbhdl-alert dbhdl-alert-danger"><strong>✖ Lỗi triển khai:</strong> ' + DBHDL.esc(e.message) + '</div>';
                DBHDL.toast(e.message, 'error');
            }
        });
    });

    /* ======================================================================
       5. MODULE: GITHUB → STAGING & GITHUB → PRODUCTION
       ====================================================================== */
    DBHDL.registerView('github-deploy', (mount, state) => {
        const s = state.settings || {};
        const env = state.environment || {};
        const stg = state.staging_detection || {};
        const inv = state.inventory || {};
        const diffState = { excluded: new Set() };

        const defaultTarget = env.role === 'production' ? 'production' : (stg.active ? 'staging' : 'current');
        let targetEnv = defaultTarget;

        const stgReady = env.role === 'staging' || !!stg.active;
        const prodReady = env.role === 'production' || !!s.prod_pairing_configured;

        const getEnvMeta = (t) => {
            if (t === 'staging') {
                return {
                    title: 'GitHub → Staging',
                    ok: stgReady,
                    site: env.role === 'staging' ? env.url : (stg.matched_url || stg.target_url || 'Chưa phát hiện URL chứa staging'),
                    desc: env.role === 'staging'
                        ? 'Kéo trực tiếp từ GitHub vào trang Staging hiện tại.'
                        : (stg.active ? 'Điều khiển từ xa yêu cầu Staging kéo code mới nhất từ GitHub.' : stg.reason),
                };
            }
            if (t === 'production') {
                return {
                    title: 'GitHub → Production',
                    ok: prodReady,
                    site: env.role === 'production' ? env.url : (s.prod_pairing_site || 'Chưa ghép đôi Production'),
                    desc: env.role === 'production'
                        ? 'Kéo trực tiếp từ GitHub về website Production hiện tại (tự tạo Snapshot trước khi ghi).'
                        : (prodReady ? 'Điều khiển từ xa yêu cầu Production kéo code mới nhất từ GitHub qua Pairing bảo mật.' : 'Vui lòng dán Production Pairing Token ở tab Kết nối & Bảo mật.'),
                };
            }
            return {
                title: 'GitHub → Website hiện tại',
                ok: true,
                site: env.url,
                desc: 'Kéo mã nguồn từ nhánh GitHub về thẳng website bạn đang mở (' + env.url + ').',
            };
        };

        const renderTargetStatus = () => {
            const meta = getEnvMeta(targetEnv);
            const box = mount.querySelector('#ghd-target-status');
            if (box) {
                box.className = 'dbhdl-alert ' + (meta.ok ? 'dbhdl-alert-info' : 'dbhdl-alert-warn');
                box.innerHTML = '<strong>' + DBHDL.esc(meta.title) + ':</strong> ' + DBHDL.esc(meta.desc) +
                    ' <span class="dbhdl-badge dbhdl-badge-neutral" style="margin-left:6px;"><code>' + DBHDL.esc(meta.site) + '</code></span>';
            }
            const diffBtn = mount.querySelector('#ghd-btn-diff');
            const pullBtn = mount.querySelector('#ghd-btn-pull');
            if (diffBtn) diffBtn.disabled = !meta.ok;
            if (pullBtn) pullBtn.disabled = !meta.ok;
        };

        mount.innerHTML =
            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📥 1. Chọn Đích Đồng Bộ từ GitHub</h2>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="ghd-check-commit">🔍 Check Commit mới</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="ghd-go-cfg">⚙️ Cấu hình GitHub &amp; Pairing</button>' +
            '</div></div>' +
            '<div id="ghd-commit-banner" class="dbhdl-alert dbhdl-alert-success" hidden></div>' +
            '<div class="dbhdl-grid-3" style="margin-bottom:12px;">' +
            '<label class="dbhdl-check-pill"><input type="radio" name="ghd_target_env" value="staging"' + (targetEnv === 'staging' ? ' checked' : '') + '>' +
            '<span><strong>🧪 GitHub → Staging</strong><br><small class="dbhdl-muted">' + (stg.active ? '✔ ' + DBHDL.esc(stg.matched_url) : '🔒 Cần URL có "staging"') + '</small></span></label>' +
            '<label class="dbhdl-check-pill"><input type="radio" name="ghd_target_env" value="production"' + (targetEnv === 'production' ? ' checked' : '') + '>' +
            '<span><strong>🌐 GitHub → Production</strong><br><small class="dbhdl-muted">' + (prodReady ? '✔ ' + DBHDL.esc(env.role === 'production' ? env.url : s.prod_pairing_site) : '⚠ Chưa ghép đôi Prod') + '</small></span></label>' +
            '<label class="dbhdl-check-pill"><input type="radio" name="ghd_target_env" value="current"' + (targetEnv === 'current' ? ' checked' : '') + '>' +
            '<span><strong>💻 GitHub → Site hiện tại</strong><br><small class="dbhdl-muted">' + DBHDL.esc(env.url) + '</small></span></label>' +
            '</div>' +
            '<div id="ghd-target-status"></div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📦 2. Chọn phạm vi kéo từ GitHub</h2>' +
            '<span class="dbhdl-badge dbhdl-badge-neutral">Repo: <code>' + DBHDL.esc(s.git_repo || 'Chưa cấu hình') + '</code> • Nhánh: <code>' + DBHDL.esc(s.git_branch || 'main') + '</code></span></div>' +
            '<div id="ghd-scope-wrap">' + DBHDL.renderScopeSelector('ghd', inv) + '</div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">⚡ 3. Kiểm tra Diff &amp; Kéo code từ GitHub về Đích</h2></div>' +
            '<div class="dbhdl-grid-2" style="margin-bottom:12px;">' +
            '<label class="dbhdl-check-pill"><input type="checkbox" id="ghd-mirror-del"><span><strong>Mirror Delete:</strong> Xóa tệp thừa ở Đích nếu GitHub không còn tệp đó trong phạm vi đã chọn.</span></label>' +
            '<label class="dbhdl-check-pill"><input type="checkbox" id="ghd-maint"' + (s.enable_maintenance_mode ? ' checked' : '') + '><span>Bật chế độ bảo trì tạm thời (<code>.maintenance</code>) lúc cập nhật.</span></label>' +
            '</div>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn" id="ghd-btn-diff">🔍 Kiểm tra Diff trước Pull</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-success" id="ghd-btn-pull">📥 Kéo từ GitHub về Đích ngay</button>' +
            '</div>' +
            '<div id="ghd-diff-mount"></div>' +
            '<div id="ghd-result-mount" style="margin-top:14px;"></div>' +
            '</div>';

        const scopeWrap = mount.querySelector('#ghd-scope-wrap');
        DBHDL.bindScopeToggleButtons(scopeWrap);
        renderTargetStatus();

        mount.querySelector('#ghd-go-cfg').addEventListener('click', () => DBHDL.switchTab('settings'));
        mount.querySelectorAll('input[name="ghd_target_env"]').forEach((r) => {
            r.addEventListener('change', () => {
                targetEnv = r.value;
                renderTargetStatus();
            });
        });

        mount.querySelector('#ghd-check-commit').addEventListener('click', async () => {
            const banner = mount.querySelector('#ghd-commit-banner');
            try {
                const res = await DBHDL.api('git/latest');
                banner.hidden = false;
                banner.innerHTML = '<strong>📌 Commit mới nhất [' + DBHDL.esc(res.sha) + ']:</strong> ' +
                    DBHDL.esc(res.message) + ' (' + DBHDL.esc(res.author) + ' • ' + DBHDL.esc(res.date) + ') ' +
                    '<a href="' + DBHDL.esc(res.html_url) + '" target="_blank" rel="noopener">Mở trên GitHub ↗</a>';
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#ghd-btn-diff').addEventListener('click', async () => {
            const scope = DBHDL.readScopeSelection(scopeWrap, 'ghd');
            diffState.excluded.clear();
            DBHDL.showProgress('Đang đối chiếu GitHub với ' + targetEnv.toUpperCase() + '...', 50);
            try {
                const res = await DBHDL.api('git/diff', {
                    method: 'POST',
                    body: { direction: 'pull', target_env: targetEnv, scope },
                });
                DBHDL.hideProgress();
                DBHDL.renderDiffExplorer(mount.querySelector('#ghd-diff-mount'), res, diffState);
                DBHDL.toast('Hoàn tất phân tích Diff GitHub → ' + targetEnv.toUpperCase() + '!', 'success');
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
            }
        });

        mount.querySelector('#ghd-btn-pull').addEventListener('click', async () => {
            const meta = getEnvMeta(targetEnv);
            const mirror = mount.querySelector('#ghd-mirror-del').checked;
            const maint = mount.querySelector('#ghd-maint').checked;
            const isRemoteProd = targetEnv === 'production' && env.role !== 'production';
            let prodHost = '';
            if (isRemoteProd && meta.site) {
                try { prodHost = new URL(meta.site).hostname; } catch (e) { prodHost = meta.site; }
            }

            const ok = await DBHDL.confirmModal({
                title: 'Xác nhận ' + meta.title,
                bodyHtml: '<p>Hệ thống sẽ tự động tạo <strong>Snapshot sao lưu</strong> tại <code>' + DBHDL.esc(meta.site) + '</code> trước khi kéo code từ nhánh <code>' + DBHDL.esc(s.git_branch || 'main') + '</code>.</p>' +
                    (mirror ? '<div class="dbhdl-alert dbhdl-alert-danger">Đang bật <strong>Mirror Delete</strong>: tệp không có trên GitHub sẽ bị xóa khỏi phạm vi đã chọn.</div>' : ''),
                confirmLabel: '📥 Kéo từ GitHub ngay',
                danger: isRemoteProd || mirror,
                requireText: isRemoteProd ? prodHost : '',
            });
            if (!ok) return;

            const body = {
                target_env: targetEnv,
                scope: DBHDL.readScopeSelection(scopeWrap, 'ghd'),
                mirror_delete: mirror,
                maintenance_mode: maint,
                excluded_files: Array.from(diffState.excluded),
            };
            if (isRemoteProd) {
                body.confirm_direct_prod = true;
                body.confirm_target_host = prodHost;
            }

            DBHDL.showProgress('Đang tải gói GitHub, tạo Snapshot và cập nhật ' + meta.title + '...', 65);
            try {
                const res = await DBHDL.api('git/pull', { method: 'POST', body });
                DBHDL.hideProgress();
                const resBox = mount.querySelector('#ghd-result-mount');
                resBox.innerHTML = '<div class="dbhdl-alert dbhdl-alert-success">' +
                    '<strong>🎉 ' + DBHDL.esc(meta.title) + ' THÀNH CÔNG!</strong><br>' +
                    '• Commit: <code>' + DBHDL.esc(res.commit_sha || s.git_branch || 'main') + '</code><br>' +
                    '• Tệp tin: <strong>' + DBHDL.esc(res.updated_count || 0) + '</strong> cập nhật, <strong>' + DBHDL.esc(res.deleted_count || 0) + '</strong> xóa.<br>' +
                    '• Snapshot: <code>' + DBHDL.esc(res.snapshot || '') + '</code>' +
                    '</div>';
                DBHDL.toast('Kéo từ GitHub thành công (' + (res.updated_count || 0) + ' tệp)!', 'success');
                await DBHDL.refreshState();
            } catch (e) {
                DBHDL.hideProgress();
                mount.querySelector('#ghd-result-mount').innerHTML =
                    '<div class="dbhdl-alert dbhdl-alert-danger"><strong>✖ Lỗi:</strong> ' + DBHDL.esc(e.message) + '</div>';
                DBHDL.toast(e.message, 'error');
            }
        });
    });

    // Backward-compatible alias if any link opens &tab=deploy
    DBHDL.registerView('deploy', (mount, state) => DBHDL.switchTab('local-production'));
})();
