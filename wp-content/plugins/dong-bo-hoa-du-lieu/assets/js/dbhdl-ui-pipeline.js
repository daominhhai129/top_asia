/* Đồng Bộ Hóa Dữ Liệu — Overview, Local → GitHub & Local → Staging Modules */
(function () {
    'use strict';

    const DBHDL = window.DBHDL;

    /* ======================================================================
       1. TỔNG QUAN PIPELINE
       ====================================================================== */
    DBHDL.registerView('overview', (mount, state) => {
        const env = state.environment || {};
        const s = state.settings || {};
        const stg = state.staging_detection || {};
        const snaps = state.snapshots || [];
        const logs = state.logs || [];
        const latestSnap = snaps[0] || null;

        const stgBadge = stg.active
            ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ Tự kích hoạt (' + DBHDL.esc(stg.matched_url) + ')</span>'
            : '<span class="dbhdl-badge dbhdl-badge-staging">🔒 Chưa phát hiện "staging" trong URL</span>';

        const prodBadge = s.prod_pairing_configured
            ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ Đã kết nối: ' + DBHDL.esc(s.prod_pairing_site) + '</span>'
            : '<span class="dbhdl-badge dbhdl-badge-neutral">Chưa ghép đôi Production</span>';

        const gitBadge = (s.git_repo && s.git_token_configured)
            ? '<span class="dbhdl-badge dbhdl-badge-prod">✔ ' + DBHDL.esc(s.git_repo) + ' (' + DBHDL.esc(s.git_branch || 'main') + ')</span>'
            : '<span class="dbhdl-badge dbhdl-badge-neutral">Chưa cấu hình đầy đủ GitHub</span>';

        let roleBanner = '';
        if (env.role === 'local') {
            roleBanner =
                '<div class="dbhdl-alert dbhdl-alert-info" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">' +
                '<div><strong>💻 Bạn đang ở LOCAL PROJECT:</strong> Chọn trực tiếp module đi lên bên dưới — <em>Local → GitHub</em>, <em>Local → Staging</em>, <em>Local → Production</em> hoặc đồng bộ <em>GitHub → Staging / Prod</em>.</div>' +
                '</div>';
        } else if (env.role === 'production') {
            roleBanner =
                '<div class="dbhdl-alert dbhdl-alert-success" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">' +
                '<div><strong>🌐 Bạn đang ở PRODUCTION (LIVE):</strong> Có thể kéo bản cập nhật từ <strong>GitHub → Production</strong>, cấp mã Pairing cho Local/Staging hoặc khôi phục Snapshot.</div>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-primary" data-jump="github-deploy">📥 GitHub → Production</button>' +
                '<button type="button" class="dbhdl-btn" data-jump="settings">🔑 Tạo Pairing Token</button>' +
                '</div></div>';
        } else {
            roleBanner =
                '<div class="dbhdl-alert dbhdl-alert-warn" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">' +
                '<div><strong>🧪 Bạn đang ở STAGING:</strong> Có thể kéo từ <strong>GitHub → Staging</strong> hoặc đẩy từ <strong>Staging → Production</strong>.</div>' +
                '<div class="dbhdl-btn-row">' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-primary" data-jump="github-deploy">📥 GitHub → Staging</button>' +
                '<button type="button" class="dbhdl-btn dbhdl-btn-success" data-jump="local-production">🚀 Staging → Production</button>' +
                '</div></div>';
        }

        const recentRows = logs.slice(0, 6).map((l) =>
            '<tr>' +
            '<td>' + DBHDL.esc(l.time) + '</td>' +
            '<td><span class="dbhdl-badge ' + (l.status === 'success' ? 'dbhdl-badge-prod' : 'dbhdl-badge-danger') + '">' + DBHDL.esc(l.action) + '</span></td>' +
            '<td>' + DBHDL.esc(l.direction) + '</td>' +
            '<td>' + DBHDL.esc(l.summary) + '</td>' +
            '</tr>'
        ).join('');

        mount.innerHTML =
            roleBanner +
            '<div class="dbhdl-module-grid">' +
            '<div class="dbhdl-module-card">' +
            '<div class="dbhdl-module-head"><span class="dbhdl-module-ico">🐙</span><div><h3>Local → GitHub</h3><div class="dbhdl-muted">So sánh Diff &amp; đẩy code từ Local lên kho GitHub</div></div></div>' +
            '<div style="margin:10px 0;">' + gitBadge + '</div>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" data-jump="local-github">Mở Local → GitHub →</button>' +
            '</div>' +

            '<div class="dbhdl-module-card">' +
            '<div class="dbhdl-module-head"><span class="dbhdl-module-ico">🧪</span><div><h3>Local → Staging</h3><div class="dbhdl-muted">Tự động kích hoạt khi URL có chứa chữ <code>staging</code></div></div></div>' +
            '<div style="margin:10px 0;">' + stgBadge + '</div>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" data-jump="local-staging">Mở Local → Staging →</button>' +
            '</div>' +

            '<div class="dbhdl-module-card">' +
            '<div class="dbhdl-module-head"><span class="dbhdl-module-ico">🌐</span><div><h3>Local → Production</h3><div class="dbhdl-muted">Module riêng đẩy thẳng Code + DB lên Production qua Pairing</div></div></div>' +
            '<div style="margin:10px 0;">' + prodBadge + '</div>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-success" data-jump="local-production">Mở Local → Production →</button>' +
            '</div>' +

            '<div class="dbhdl-module-card">' +
            '<div class="dbhdl-module-head"><span class="dbhdl-module-ico">📥</span><div><h3>GitHub → Staging / Prod</h3><div class="dbhdl-muted">Đồng bộ code từ GitHub về Staging hoặc về Production</div></div></div>' +
            '<div style="margin:10px 0;"><span class="dbhdl-badge dbhdl-badge-info">Hỗ trợ Diff + Auto Snapshot + Webhook</span></div>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" data-jump="github-deploy">Mở GitHub → Staging / Prod →</button>' +
            '</div>' +
            '</div>' +

            '<div class="dbhdl-grid-4">' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Vai trò Site này</div><div class="dbhdl-kpi-val">' + DBHDL.esc((env.role || '').toUpperCase()) + '</div><div class="dbhdl-kpi-sub">Phát hiện: ' + DBHDL.esc(env.source === 'manual' ? 'Thủ công' : 'Tự động') + '</div></div>' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Git Repository</div><div class="dbhdl-kpi-val">' + DBHDL.esc(s.git_repo || 'Chưa cấu hình') + '</div><div class="dbhdl-kpi-sub">Nhánh: <code>' + DBHDL.esc(s.git_branch || 'main') + '</code> • ' + (s.git_token_configured ? '✔ Có Token' : '⚠ Thiếu Token') + '</div></div>' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Local → Staging</div><div class="dbhdl-kpi-val" style="font-size:15px;">' + (stg.active ? '✔ Đang kích hoạt' : '🔒 Tạm khóa') + '</div><div class="dbhdl-kpi-sub">' + DBHDL.esc(stg.target_url || 'Cần URL chứa "staging"') + '</div></div>' +
            '<div class="dbhdl-kpi"><div class="dbhdl-kpi-label">Snapshot Gần Nhất</div><div class="dbhdl-kpi-val">' + (latestSnap ? DBHDL.esc(latestSnap.size_mb + ' MB') : 'Chưa có') + '</div><div class="dbhdl-kpi-sub">' + (latestSnap ? DBHDL.esc(latestSnap.time) : 'Tự sinh khi đồng bộ') + '</div></div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📋 Hoạt động đồng bộ gần đây</h2>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-jump="logs">Xem toàn bộ nhật ký →</button></div>' +
            '<div class="dbhdl-table-wrap"><table class="dbhdl-table"><thead><tr><th>Thời gian</th><th>Hành động</th><th>Luồng</th><th>Tóm tắt</th></tr></thead><tbody>' +
            (recentRows || '<tr><td colspan="4" class="dbhdl-muted">Chưa có lịch sử đồng bộ nào.</td></tr>') +
            '</tbody></table></div></div>';

        mount.querySelectorAll('[data-jump]').forEach((b) => {
            b.addEventListener('click', () => {
                const slot = b.getAttribute('data-slot');
                if (slot) DBHDL.nextDeploySlot = slot;
                DBHDL.switchTab(b.getAttribute('data-jump'));
            });
        });
    });

    /* ======================================================================
       2. MODULE: LOCAL → GITHUB
       ====================================================================== */
    DBHDL.registerView('local-github', (mount, state) => {
        const s = state.settings || {};
        const inv = state.inventory || {};
        const diffState = { excluded: new Set() };
        const whUrl = s.webhook_url || '';
        const whSec = s.webhook_secret_raw || '';

        const snippets = {
            ps: '$body = "{\\"ref\\":\\"refs/heads/' + (s.git_branch || 'main') + '\\"}"\n' +
                '$hmac = [System.Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes("' + whSec + '"))\n' +
                '$sig  = "sha256=" + ([BitConverter]::ToString($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($body))).Replace("-","").ToLower())\n' +
                'git push origin ' + (s.git_branch || 'main') + '\n' +
                'Invoke-RestMethod -Uri "' + whUrl + '" -Method Post -ContentType "application/json" -Headers @{ "X-Hub-Signature-256" = $sig } -Body $body',
            bash: 'git push origin ' + (s.git_branch || 'main') + '\n' +
                'BODY=\'{"ref":"refs/heads/' + (s.git_branch || 'main') + '"}\'\n' +
                'SIG="sha256=$(printf \'%s\' "$BODY" | openssl dgst -sha256 -hmac "' + whSec + '" | sed \'s/^.* //\')"\n' +
                'curl -fsS -X POST "' + whUrl + '" -H "Content-Type: application/json" -H "X-Hub-Signature-256: $SIG" -d "$BODY"',
            gha: 'name: Deploy Webhook\non:\n  push:\n    branches: [ "' + (s.git_branch || 'main') + '" ]\njobs:\n  trigger:\n    runs-on: ubuntu-latest\n    steps:\n      - name: Trigger Pull Webhook\n        run: |\n          curl -fsS -X POST "' + whUrl + '" \\\n            -H "X-DBHDL-Webhook-Secret: ${{ secrets.DBHDL_WEBHOOK_SECRET }}"',
        };

        mount.innerHTML =
            '<div class="dbhdl-alert dbhdl-alert-info" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
            '<span><strong>🐙 Module Local → GitHub:</strong> Kiểm tra Diff và đẩy mã nguồn WordPress từ môi trường hiện tại lên nhánh GitHub <code>' + DBHDL.esc(s.git_branch || 'main') + '</code>.</span>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="lg-check-commit">🔍 Check Commit mới</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="lg-go-cfg">⚙️ Cấu hình GitHub</button>' +
            '</div></div>' +
            '<div id="lg-commit-banner" class="dbhdl-alert dbhdl-alert-success" hidden></div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📦 1. Chọn phạm vi mã nguồn đẩy lên GitHub</h2>' +
            '<span class="dbhdl-badge dbhdl-badge-neutral">Repo: <code>' + DBHDL.esc(s.git_repo || 'Chưa cấu hình') + '</code> • Nhánh: <code>' + DBHDL.esc(s.git_branch || 'main') + '</code></span></div>' +
            '<div id="lg-scope-wrap">' + DBHDL.renderScopeSelector('lg', inv) + '</div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📤 2. Kiểm tra Diff &amp; Đẩy (Push) lên GitHub</h2></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Commit Message:</label>' +
            '<input type="text" id="lg-commit-msg" class="dbhdl-input" value="sync: Cập nhật từ Local WordPress"></div>' +
            '<label class="dbhdl-check-pill" style="margin-bottom:12px;"><input type="checkbox" id="lg-mirror-del">' +
            '<span><strong>Mirror Delete:</strong> Gỡ các tệp cũ trên GitHub nếu máy Local không còn tệp đó trong phạm vi đã chọn.</span></label>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn" id="lg-btn-diff">🔍 Kiểm tra Diff trước khi Push</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="lg-btn-push">📤 Đẩy lên GitHub ngay</button>' +
            '</div>' +
            '<div id="lg-diff-mount"></div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🖥️ Nhật ký &amp; Script Git CLI nhanh từ máy Local</h2></div>' +
            '<div id="lg-console" class="dbhdl-console" style="margin-bottom:14px;">Sẵn sàng đẩy Local → GitHub. Chọn phạm vi ở trên và bấm Kiểm tra Diff hoặc Đẩy lên GitHub.</div>' +
            '<div class="dbhdl-btn-row" style="margin-bottom:8px;">' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm dbhdl-btn-primary" data-snip="ps">PowerShell (Windows)</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-snip="bash">Bash / macOS / Linux</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" data-snip="gha">GitHub Actions YAML</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="lg-copy-snip">📋 Copy Script</button>' +
            '</div>' +
            '<pre id="lg-snip-box" class="dbhdl-console" style="margin:0;">' + DBHDL.esc(snippets.ps) + '</pre>' +
            '</div>';

        const scopeWrap = mount.querySelector('#lg-scope-wrap');
        DBHDL.bindScopeToggleButtons(scopeWrap);
        mount.querySelector('#lg-go-cfg').addEventListener('click', () => DBHDL.switchTab('settings'));

        const logLine = (msg) => {
            const con = mount.querySelector('#lg-console');
            if (con) con.textContent += '\n' + msg;
        };

        mount.querySelector('#lg-check-commit').addEventListener('click', async () => {
            const banner = mount.querySelector('#lg-commit-banner');
            try {
                const res = await DBHDL.api('git/latest');
                banner.hidden = false;
                banner.innerHTML = '<strong>📌 Commit mới nhất [' + DBHDL.esc(res.sha) + ']:</strong> ' +
                    DBHDL.esc(res.message) + ' (' + DBHDL.esc(res.author) + ' • ' + DBHDL.esc(res.date) + ') ' +
                    '<a href="' + DBHDL.esc(res.html_url) + '" target="_blank" rel="noopener">Mở trên GitHub ↗</a>';
            } catch (e) { DBHDL.toast(e.message, 'error'); }
        });

        mount.querySelector('#lg-btn-diff').addEventListener('click', async () => {
            const scope = DBHDL.readScopeSelection(scopeWrap, 'lg');
            diffState.excluded.clear();
            DBHDL.showProgress('Đang quét Diff Local → GitHub...', 45);
            try {
                const res = await DBHDL.api('git/diff', { method: 'POST', body: { direction: 'push', scope } });
                DBHDL.hideProgress();
                DBHDL.renderDiffExplorer(mount.querySelector('#lg-diff-mount'), res, diffState);
                logLine('✔ Diff Local → GitHub: +' + res.summary.added + ' ~' + res.summary.modified + ' -' + res.summary.deleted);
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
                logLine('✖ Lỗi Diff: ' + e.message);
            }
        });

        mount.querySelector('#lg-btn-push').addEventListener('click', async () => {
            const mirror = mount.querySelector('#lg-mirror-del').checked;
            const msg = mount.querySelector('#lg-commit-msg').value.trim();
            const ok = await DBHDL.confirmModal({
                title: 'Xác nhận Đẩy mã nguồn Local → GitHub',
                bodyHtml: '<p>Commit message: <code>' + DBHDL.esc(msg || 'auto') + '</code></p>' +
                    (mirror ? '<div class="dbhdl-alert dbhdl-alert-warn">Chế độ Mirror Delete sẽ gỡ các blob cũ không còn ở Local khỏi phạm vi đã chọn trên GitHub.</div>' : ''),
                confirmLabel: '📤 Đẩy lên GitHub',
            });
            if (!ok) return;

            const scope = DBHDL.readScopeSelection(scopeWrap, 'lg');
            DBHDL.showProgress('Đang so sánh Git Blob & đẩy thay đổi lên GitHub...', 55);
            try {
                const res = await DBHDL.api('git/push', {
                    method: 'POST',
                    body: {
                        scope,
                        commit_msg: msg,
                        mirror_delete: mirror,
                        excluded_files: Array.from(diffState.excluded),
                    },
                });
                DBHDL.hideProgress();
                if (res.no_changes) {
                    DBHDL.toast(res.message, 'info');
                    logLine('ℹ ' + res.message);
                } else {
                    DBHDL.toast('Đã đẩy commit ' + res.short_sha + ' (' + res.pushed_count + ' tệp) lên GitHub!', 'success');
                    logLine('🎉 Push thành công [' + res.short_sha + ']: ' + res.commit_url);
                }
                await DBHDL.refreshState();
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
                logLine('✖ Lỗi Push: ' + e.message);
            }
        });

        mount.querySelectorAll('[data-snip]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const key = btn.getAttribute('data-snip');
                mount.querySelectorAll('[data-snip]').forEach((b) => b.classList.remove('dbhdl-btn-primary'));
                btn.classList.add('dbhdl-btn-primary');
                mount.querySelector('#lg-snip-box').textContent = snippets[key] || '';
            });
        });
        mount.querySelector('#lg-copy-snip').addEventListener('click', (e) => {
            DBHDL.copyText(mount.querySelector('#lg-snip-box').textContent, e.currentTarget);
        });
    });

    /* ======================================================================
       3. MODULE: LOCAL → STAGING (Chỉ kích hoạt khi URL có chữ "staging")
       ====================================================================== */
    DBHDL.registerView('local-staging', (mount, state) => {
        const s = state.settings || {};
        const inv = state.inventory || {};
        const stg = state.staging_detection || {};
        const isStgActive = !!stg.active;
        const diffState = { excluded: new Set() };
        const zipDiffState = { excluded: new Set(), uploadId: '' };

        const targetOptions = []
            .concat((inv.plugins || []).map((p) => '<option value="wp-content/plugins/' + DBHDL.esc(p.slug) + '">Plugin: ' + DBHDL.esc(p.name) + ' (' + DBHDL.esc(p.slug) + ')</option>'))
            .concat((inv.themes || []).map((t) => '<option value="wp-content/themes/' + DBHDL.esc(t.slug) + '">Theme: ' + DBHDL.esc(t.name) + ' (' + DBHDL.esc(t.slug) + ')</option>'))
            .join('');

        const stgBanner = isStgActive
            ? '<div class="dbhdl-alert dbhdl-alert-success" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
              '<div><strong>✔ Module Local → Staging ĐÃ TỰ KÍCH HOẠT:</strong> Phát hiện từ khóa <code>staging</code> trong URL <code>' + DBHDL.esc(stg.matched_url) + '</code>.</div>' +
              '<button type="button" class="dbhdl-btn dbhdl-btn-sm" id="ls-go-cfg">⚙️ Đổi đích Staging</button></div>'
            : '<div class="dbhdl-card" style="border:1.5px solid #f59e0b;background:#fffbeb;">' +
              '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title" style="color:#92400e;">🔒 Module Local → Staging chưa kích hoạt</h2>' +
              '<span class="dbhdl-badge dbhdl-badge-staging">Cần URL chứa chữ "staging"</span></div>' +
              '<p style="margin:0 0 12px;color:#78350f;font-size:13.5px;">' + DBHDL.esc(stg.reason || 'Module Local → Staging chỉ tự động kích hoạt khi URL đích (hoặc URL hiện tại) có chứa chữ staging.') +
              (stg.target_url ? ' (URL đích đang trỏ: <code>' + DBHDL.esc(stg.target_url) + '</code>)' : '') + '</p>' +
              '<div class="dbhdl-grid-2">' +
              '<div>' +
              '<label class="dbhdl-label">Cách 1 — Dán Staging Pairing Token (URL chứa <code>staging</code>):</label>' +
              '<div class="dbhdl-input-row"><input type="text" id="ls-quick-token" class="dbhdl-input dbhdl-mono" placeholder="Dán token từ https://.../staging/...">' +
              '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="ls-save-quick-token">🔗 Kích hoạt Staging</button></div>' +
              '</div>' +
              '<div>' +
              '<label class="dbhdl-label">Cách 2 — Tự tìm thư mục /staging cùng máy chủ:</label>' +
              '<div class="dbhdl-btn-row"><button type="button" class="dbhdl-btn" id="ls-detect-sibling">🔍 Quét thư mục Staging</button>' +
              '<button type="button" class="dbhdl-btn" id="ls-go-cfg">⚙️ Mở Kết nối &amp; Bảo mật</button></div>' +
              '<div id="ls-sibling-list" style="margin-top:8px;"></div>' +
              '</div></div></div>';

        mount.innerHTML =
            stgBanner +
            '<div class="dbhdl-grid-deploy">' +
            '<div>' +
            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">1. Chọn Mã nguồn đồng bộ Local → Staging</h2>' +
            '<span class="dbhdl-badge ' + (isStgActive ? 'dbhdl-badge-staging' : 'dbhdl-badge-neutral') + '">' +
            (isStgActive ? 'Đích: ' + DBHDL.esc(stg.matched_url) : 'Đang tạm khóa') + '</span></div>' +
            '<div id="ls-scope-wrap">' + DBHDL.renderScopeSelector('ls', inv, { defaultActiveOnly: false }) + '</div>' +
            '</div>' +

            DBHDL.renderDbAndOptionsPanel('ls', state, { defaultMaint: false }) +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">🚀 4. Thực thi Đồng bộ Local → Staging</h2></div>' +
            '<div class="dbhdl-btn-row">' +
            '<button type="button" class="dbhdl-btn" id="ls-btn-diff"' + (!isStgActive ? ' disabled' : '') + '>🔍 Kiểm tra Diff với Staging</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-primary" id="ls-btn-run"' + (!isStgActive ? ' disabled' : '') + '>🧪 Tạo Snapshot &amp; Đẩy sang Staging</button>' +
            '</div>' +
            '<div id="ls-diff-mount"></div>' +
            '<div id="ls-result-mount" style="margin-top:14px;"></div>' +
            '</div>' +

            '<div class="dbhdl-card">' +
            '<div class="dbhdl-card-head"><h2 class="dbhdl-card-title">📁 Giải nén gói .ZIP thủ công từ Local</h2>' +
            '<span class="dbhdl-badge dbhdl-badge-local">Không cần Pairing / Git</span></div>' +
            '<div class="dbhdl-field"><label class="dbhdl-label">Thư mục đích khi file ZIP không chứa sẵn <code>wp-content/</code>:</label>' +
            '<select id="ls-zip-target" class="dbhdl-select"><option value="">Tự động nhận diện (yêu cầu ZIP chứa wp-content/...)</option>' + targetOptions + '</select></div>' +
            '<div id="ls-dropzone" class="dbhdl-dropzone" tabindex="0">' +
            '<div style="font-size:28px;">🗜️</div>' +
            '<strong>Kéo thả file .zip từ máy tính vào đây</strong> hoặc bấm để chọn file' +
            '<div class="dbhdl-muted" id="ls-zip-name">Hỗ trợ xem Diff trước khi giải nén &amp; tự tạo Snapshot</div>' +
            '<input type="file" id="ls-zip-input" accept=".zip" hidden>' +
            '</div>' +
            '<div class="dbhdl-btn-row" style="margin-top:12px;">' +
            '<button type="button" class="dbhdl-btn" id="ls-btn-zip-inspect" disabled>🔍 Kiểm tra Diff trong ZIP</button>' +
            '<button type="button" class="dbhdl-btn dbhdl-btn-success" id="ls-btn-zip-apply" disabled>⚡ Giải nén &amp; Cập nhật ngay</button>' +
            '</div>' +
            '<div id="ls-zip-diff-mount"></div>' +
            '</div>' +
            '</div>' +

            '<div>' + DBHDL.renderSnapshotsCard(state.snapshots || []) + '</div>' +
            '</div>';

        const scopeWrap = mount.querySelector('#ls-scope-wrap');
        DBHDL.bindScopeToggleButtons(mount);
        DBHDL.bindSnapshotActions(mount, 'local-staging');

        const goCfg = mount.querySelector('#ls-go-cfg');
        if (goCfg) goCfg.addEventListener('click', () => DBHDL.switchTab('settings'));

        const quickTokenBtn = mount.querySelector('#ls-save-quick-token');
        if (quickTokenBtn) {
            quickTokenBtn.addEventListener('click', async () => {
                const tok = mount.querySelector('#ls-quick-token').value.trim();
                if (!tok) {
                    DBHDL.toast('Vui lòng dán Staging Pairing Token trước.', 'error');
                    return;
                }
                try {
                    await DBHDL.api('settings', {
                        method: 'POST',
                        body: { target_mode: 'remote_pairing', target_pairing_token: tok },
                    });
                    await DBHDL.refreshState();
                    DBHDL.switchTab('local-staging');
                    DBHDL.toast('Đã lưu Pairing Token và kiểm tra tự động kích hoạt Staging!', 'success');
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        const siblingBtn = mount.querySelector('#ls-detect-sibling');
        if (siblingBtn) {
            siblingBtn.addEventListener('click', async () => {
                try {
                    const res = await DBHDL.api('target/detect');
                    const wrap = mount.querySelector('#ls-sibling-list');
                    const list = res.siblings || [];
                    wrap.innerHTML = list.length
                        ? list.map((it) => '<button type="button" class="dbhdl-btn dbhdl-btn-sm ls-pick-sib" data-p="' + DBHDL.esc(it.path) + '" style="margin:2px;">📁 ' + DBHDL.esc(it.path) + ' (' + DBHDL.esc(it.site_url || it.relation) + ')</button>').join('')
                        : '<span class="dbhdl-muted">Không phát hiện thư mục WordPress staging nào gần đây.</span>';
                    wrap.querySelectorAll('.ls-pick-sib').forEach((b) => {
                        b.addEventListener('click', async () => {
                            await DBHDL.api('settings', {
                                method: 'POST',
                                body: { target_mode: 'same_server', target_path: b.getAttribute('data-p') },
                            });
                            await DBHDL.refreshState();
                            DBHDL.switchTab('local-staging');
                        });
                    });
                } catch (e) { DBHDL.toast(e.message, 'error'); }
            });
        }

        const buildPayload = () => Object.assign(
            {
                target_slot: 'staging',
                scope: DBHDL.readScopeSelection(scopeWrap, 'ls'),
                excluded_files: Array.from(diffState.excluded),
            },
            DBHDL.readDbAndOptionsPayload(mount, 'ls')
        );

        mount.querySelector('#ls-btn-diff').addEventListener('click', async () => {
            diffState.excluded.clear();
            DBHDL.showProgress('Đang đối chiếu File & Database Local → Staging...', 50);
            try {
                const res = await DBHDL.api('deploy/diff', { method: 'POST', body: buildPayload() });
                DBHDL.hideProgress();
                DBHDL.renderDiffExplorer(mount.querySelector('#ls-diff-mount'), res, diffState);
                DBHDL.toast('Hoàn tất phân tích Diff với Staging!', 'success');
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
            }
        });

        mount.querySelector('#ls-btn-run').addEventListener('click', async () => {
            const payload = buildPayload();
            const ok = await DBHDL.confirmModal({
                title: 'Xác nhận Đẩy Local → Staging',
                bodyHtml: '<p>Đích Staging: <strong>' + DBHDL.esc(stg.matched_url) + '</strong></p>' +
                    '<p>Hệ thống tự động tạo <strong>Snapshot (Code + DB)</strong> trên Staging trước khi cập nhật.</p>',
                confirmLabel: '🧪 Bắt đầu đẩy sang Staging',
                danger: payload.mirror_delete,
            });
            if (!ok) return;

            DBHDL.showProgress('Đang đóng gói, tạo Snapshot và đẩy sang Staging...', 65);
            try {
                const res = await DBHDL.api('deploy/run', { method: 'POST', body: payload });
                DBHDL.hideProgress();
                const resBox = mount.querySelector('#ls-result-mount');
                if (res.no_changes) {
                    resBox.innerHTML = '<div class="dbhdl-alert dbhdl-alert-info">' + DBHDL.esc(res.message) + '</div>';
                } else {
                    const dbS = res.db_stats || {};
                    resBox.innerHTML = '<div class="dbhdl-alert dbhdl-alert-success">' +
                        '<strong>🎉 ĐẨY SANG STAGING THÀNH CÔNG!</strong><br>' +
                        '• Snapshot: <code>' + DBHDL.esc(res.snapshot) + '</code><br>' +
                        '• Tệp: <strong>' + DBHDL.esc(res.copied ?? res.updated_count ?? 0) + '</strong> cập nhật, <strong>' + DBHDL.esc(res.deleted ?? res.deleted_count ?? 0) + '</strong> xóa.<br>' +
                        '• Database: <strong>' + ((dbS.posts_updated || 0) + (dbS.posts_created || 0)) + '</strong> bài/template, <strong>' + (dbS.menus_synced || 0) + '</strong> menu.' +
                        '</div>';
                }
                DBHDL.toast('Đồng bộ Local → Staging hoàn tất!', 'success');
                await DBHDL.refreshState();
            } catch (e) {
                DBHDL.hideProgress();
                mount.querySelector('#ls-result-mount').innerHTML =
                    '<div class="dbhdl-alert dbhdl-alert-danger"><strong>✖ Lỗi:</strong> ' + DBHDL.esc(e.message) + '</div>';
                DBHDL.toast(e.message, 'error');
            }
        });

        // Local ZIP Dropzone
        const dz = mount.querySelector('#ls-dropzone');
        const fileInp = mount.querySelector('#ls-zip-input');
        const btnInspectZip = mount.querySelector('#ls-btn-zip-inspect');
        const btnApplyZip = mount.querySelector('#ls-btn-zip-apply');
        let selectedZipFile = null;

        const pickFile = (file) => {
            if (!file || !file.name.toLowerCase().endsWith('.zip')) {
                DBHDL.toast('Vui lòng chọn tệp định dạng .zip', 'error');
                return;
            }
            selectedZipFile = file;
            mount.querySelector('#ls-zip-name').textContent = 'Đã chọn: ' + file.name + ' (' + DBHDL.formatBytes(file.size) + ')';
            btnInspectZip.disabled = false;
            btnApplyZip.disabled = true;
            zipDiffState.uploadId = '';
        };

        dz.addEventListener('click', () => fileInp.click());
        dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('is-dragover'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('is-dragover'));
        dz.addEventListener('drop', (e) => {
            e.preventDefault();
            dz.classList.remove('is-dragover');
            if (e.dataTransfer.files[0]) pickFile(e.dataTransfer.files[0]);
        });
        fileInp.addEventListener('change', () => { if (fileInp.files[0]) pickFile(fileInp.files[0]); });

        btnInspectZip.addEventListener('click', async () => {
            if (!selectedZipFile) return;
            const fd = new FormData();
            fd.append('package', selectedZipFile);
            fd.append('target_prefix', mount.querySelector('#ls-zip-target').value);
            DBHDL.showProgress('Đang tải lên và phân tích Diff trong file ZIP...', 50);
            try {
                const res = await DBHDL.api('zip/inspect', { method: 'POST', formData: fd });
                DBHDL.hideProgress();
                zipDiffState.uploadId = res.upload_id;
                zipDiffState.excluded.clear();
                DBHDL.renderDiffExplorer(mount.querySelector('#ls-zip-diff-mount'), res, zipDiffState);
                btnApplyZip.disabled = false;
                DBHDL.toast('Đã kiểm tra gói ZIP (' + res.mapped_target + ').', 'success');
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
            }
        });

        btnApplyZip.addEventListener('click', async () => {
            if (!zipDiffState.uploadId) return;
            const ok = await DBHDL.confirmModal({
                title: 'Xác nhận giải nén gói Local ZIP',
                bodyHtml: '<p>Hệ thống sẽ tạo Snapshot bảo lưu trước khi ghi đè các tệp từ gói ZIP vào website.</p>',
                confirmLabel: '⚡ Giải nén ngay',
            });
            if (!ok) return;

            DBHDL.showProgress('Đang tạo Snapshot và giải nén gói ZIP...', 70);
            try {
                const res = await DBHDL.api('zip/apply', {
                    method: 'POST',
                    body: {
                        upload_id: zipDiffState.uploadId,
                        excluded_files: Array.from(zipDiffState.excluded),
                        mirror_delete: false,
                    },
                });
                DBHDL.hideProgress();
                DBHDL.toast('Đã cập nhật ' + res.updated_count + ' tệp từ gói ZIP! Snapshot: ' + res.snapshot, 'success');
                await DBHDL.refreshState();
                DBHDL.switchTab('local-staging');
            } catch (e) {
                DBHDL.hideProgress();
                DBHDL.toast(e.message, 'error');
            }
        });
    });
})();
