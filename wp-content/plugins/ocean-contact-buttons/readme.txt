=== Ocean Contact Buttons ===
Contributors: ocean
Tags: contact, hotline, floating button, zalo, messenger
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later

Thanh liên hệ nổi cố định ở cạnh dưới website, popup chọn hotline theo ba khu vực, nút Zalo và Messenger.

== Cài đặt ==
1. Vào Plugins > Add New Plugin > Upload Plugin.
2. Tải lên file ocean-contact-buttons.zip và chọn Install Now.
3. Chọn Activate Plugin.
4. Mở menu Ocean Contact Buttons trong trang quản trị để cấu hình.

== Cấu hình ==
* Cấu hình chung: bật/tắt thanh và từng nút, thiết bị, thứ tự, kích thước, màu sắc, khoảng cách, bo góc và z-index.
* Hotline: chỉnh tên, mô tả, số điện thoại và trạng thái cho Miền Bắc, Miền Trung, Miền Nam. Mặc định lần lượt là 0896 699 996, 0934 657 227, 0934 468 778.
* Zalo: nhập số điện thoại và/hoặc URL tùy chỉnh. URL tùy chỉnh được ưu tiên.
* Messenger: nhập Messenger URL hoặc Facebook Page ID. URL tùy chỉnh được ưu tiên. Mặc định đã điền Page ID 100737662960618 theo thông tin cung cấp.
* Hiển thị: toàn website hoặc chỉ các ID chỉ định; có thể loại trừ ID, ẩn theo thiết bị và chỉnh khoảng cách đáy.

== Shortcode ==
[ocean_contact_buttons]

Có thể đặt shortcode trong nội dung, widget shortcode hoặc block Shortcode. Nếu thanh tự động đã được xuất trên trang, shortcode không tạo bản sao thứ hai.

== Ghi chú ==
* Link gọi sử dụng giao thức tel:, do đó hành vi thực tế phụ thuộc thiết bị và ứng dụng gọi điện của khách truy cập.
* Nếu chưa nhập URL Zalo/Messenger hợp lệ, nút tương ứng sẽ không xuất hiện.
* Plugin không cần Elementor Pro và không tạo bảng dữ liệu riêng.

== Phiên bản 1.0.2 ==
* Đồng bộ chiều cao và căn giữa nội dung ba nút Gọi ngay, Zalo và Messenger.
* Chuẩn hóa biểu tượng điện thoại bằng SVG nét, giữ đúng tỷ lệ trên desktop và mobile.
* Tăng khả năng ưu tiên kiểu dáng của plugin để tránh CSS giao diện website làm lệch nút.

== Phiên bản 1.0.4 ==
* Bổ sung hiệu ứng pulse theo nhịp lệch nhau cho Gọi ngay, Zalo và Messenger.
* Thêm vòng sáng ripple lan tỏa, ánh sáng lướt nhẹ và hiệu ứng hover.
* Tôn trọng cài đặt giảm chuyển động của thiết bị.

== Phiên bản 1.0.5 ==
* Tăng độ rõ của chuyển động tự động: nút nhịp phóng to/thu nhỏ và nổi nhẹ liên tục.
* Bổ sung vòng sáng lan tỏa, ánh sáng quét qua và chuyển động biểu tượng riêng cho từng nút.
* Các hiệu ứng tự chạy ngay khi tải trang, không cần rê chuột hoặc chạm; ba nút chạy lệch nhịp để tạo điểm nhấn.

= 1.1.0 =
* Added click tracking for Call Now, Zalo and Messenger buttons.
* Added daily aggregate reports for 7, 30, 90 and 365 days in WordPress admin.
* Stores aggregate counts only; no visitor IP or personal profile data is collected.

= 1.2.2 =
* Rebuilt on the stable 1.1.0 codebase.
* Preserved existing settings with recursive defaults to prevent missing options from hiding the contact bar.
* Added independent click tracking for North, Central and South hotline links.
* Separated database schema version from plugin version to avoid unnecessary database upgrades.
