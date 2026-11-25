# Đồng Bộ Hóa Dữ Liệu (`dong-bo-hoa-du-lieu`)

Plugin WordPress đa năng, độc lập hoàn toàn, giúp bất kỳ website WordPress nào (Localhost, cPanel/Shared Hosting, VPS, Cloud) quản lý quy trình đồng bộ **Local Project → Staging → Production** với giao diện trực quan, kiểm tra Diff trước khi áp dụng, Snapshot kép (Code + DB) và Rollback 1-Click.

---

## 1. Tính năng nổi bật

- **Hoạt động trên mọi website WordPress (`WP >= 6.0`, `PHP >= 8.0`):** Không khóa cứng tên plugin, theme, đường dẫn hay domain; tự động quét danh sách Plugins, Themes, MU-Plugins, thư mục `wp-content/*`, Templates (Elementor, Gutenberg FSE, JetEngine, ACF), CPTs và Menus.
- **4 kênh đồng bộ linh hoạt:**
  1. **GitHub 2 Chiều (Pull & Push):** Kéo zipball theo phạm vi, đẩy thay đổi thông minh qua Git Blob SHA-1 (chỉ upload tệp thực sự thay đổi, hỗ trợ cả ảnh/font nhị phân và tùy chọn Mirror Delete), ánh xạ Subpath Repo ↔ WordPress, Webhook Auto-Pull ký HMAC-SHA256 (`X-Hub-Signature-256`).
  2. **Same-Server Path + Cross-DB:** Đồng bộ trực tiếp giữa Staging và Production nằm trên cùng máy chủ (đọc `wp-config.php` an toàn bằng PHP `token_get_all()`).
  3. **Remote HTTPS Pairing:** Ghép đôi hai website WordPress ở **bất kỳ hai máy chủ khác nhau** (ví dụ từ LocalWP/Laragon/XAMPP trên máy cá nhân lên Staging online, hoặc Staging VPS A sang Production Hosting B) qua REST API ký HMAC-SHA256 + truyền gói ZIP chia chunk + kiểm tra SHA-256.
  4. **Local `.zip` Upload (Drag & Drop):** Tải trực tiếp gói `.zip` từ máy tính, xem trước Diff từng tệp và giải nén có Snapshot bảo vệ.
- **Database Sync & Search-Replace An toàn:**
  - Bộ `SafeSearchReplace` viết thuần PHP (không gọi `unserialize()`), bảo toàn byte-length cho chuỗi PHP serialized (kể cả tiếng Việt UTF-8) và JSON escaped (`https:\/\/`).
  - Khớp bản ghi qua `_dbhdl_sync_uid` / `post_name + post_type` để không bao giờ ghi đè nhầm ID đơn hàng WooCommerce hay bài viết trên Production.
  - Khóa cứng bảo vệ `wp_users`, `wp_usermeta`, `wp_comments`, đơn hàng WooCommerce, `siteurl`, `home`, `active_plugins`.
- **Snapshot kép & Rollback 1-Click:** Tự động sao lưu cả tệp tin lẫn trạng thái DB cũ (`db-backup.json`) vào thư mục ngoài webroot (hoặc thư mục được bảo vệ `.htaccess`/`web.config`/`index.php`).
- **Kho Biến Môi Trường & Secrets Mã hóa:** Mã hóa bằng `libsodium` XChaCha20-Poly1305 hoặc OpenSSL AES-256-GCM dẫn xuất từ WP Salts; giao diện Write-Only che giá trị và hỗ trợ policy riêng cho Staging vs Production.

---

## 2. Cấu trúc thư mục

- `dong-bo-hoa-du-lieu.php`: Entrypoint, PHP >= 8.0 guard, `.disabled` kill-switch, PSR-4 autoloader.
- `src/Security/`: `CryptoVault.php`, `PathGuard.php`, `RequestSigner.php`.
- `src/Discovery/`: `EnvironmentDetector.php`, `ScopeScanner.php`.
- `src/Diff/`: `DiffEngine.php`.
- `src/Database/`: `SafeSearchReplace.php`, `SelectiveDbSync.php`, `SecretsVaultManager.php`.
- `src/Snapshot/`: `SnapshotManager.php`, `RollbackEngine.php`.
- `src/Transport/`: `ZipApplyHelper.php`, `GitHubTransport.php`, `SameServerTransport.php`, `RemoteRestTransport.php`, `ZipUploadTransport.php`.
- `src/Jobs/`: `CachePurger.php`, `JobStateStore.php`.
- `src/REST/`: `SyncRestController.php`, `PairingRestController.php`, `WebhookController.php`.
- `src/Admin/`: `AdminMenu.php`, `AppRenderer.php`, `MigrationImporter.php`.
- `assets/`: `css/dbhdl-admin.css`, `css/dbhdl-admin-bar.css`, `js/dbhdl-*.js`.

---

## 3. Hướng dẫn nhanh

1. Cài đặt plugin trên cả **Local / Staging / Production** (bằng file `dong-bo-hoa-du-lieu.zip` hoặc thư mục plugin) và kích hoạt.
2. Vào menu **Công cụ → Đồng Bộ Hóa Dữ Liệu** (tab **Kết nối & Bảo mật**):
   - Chọn vai trò site (`Local`, `Staging`, hoặc `Production` — hoặc để `Tự động`).
   - Cấu hình kết nối GitHub nếu dùng luồng Git, hoặc tạo **Pairing Token** ở site nhận (Staging/Production) và dán vào site gửi (Local/Staging).
   - Nếu Staging & Production nằm cùng server, chọn *Same-Server Path* và bấm **Tự tìm** → **Test kết nối Đích**.
3. Dùng tab **Local ⇄ Staging** để Pull/Push GitHub, upload `.zip` từ Local, hoặc lấy sẵn script PowerShell/Bash/GitHub Actions.
4. Dùng tab **Staging → Production** để chạy **Kiểm tra Diff (Dry-Run)**, chọn phạm vi Code/DB/Secrets cần đẩy, và nhấn **Tạo Snapshot & Đẩy lên Đích**.
5. Nếu cần hoàn tác, bấm **⏪ Khôi phục** bên cạnh bản Snapshot tương ứng.

---

## 4. Khóa khẩn cấp (Kill Switch)

Tạo một tệp rỗng tên `.disabled` tại `wp-content/plugins/dong-bo-hoa-du-lieu/.disabled` qua FTP/File Manager để vô hiệu hóa tức thì plugin mà không xóa cấu hình hay Snapshot.
