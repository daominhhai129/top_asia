<?php
/**
 * Top Asia homepage redesign — asset loading (front page only).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Nội dung trang chủ sửa được từ wp-admin. Nạp ở đây (không chỉ trong hook) vì
// front-page.php gọi taf_opt() ngay khi render.
require_once __DIR__ . '/home-content.php';

// Asset trang chủ nay nạp trong functions.php (ta-home.css / ta-home.js).

