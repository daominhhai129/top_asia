<?php
// Add custom Theme Functions here

// Top Asia — custom primary navigation (desktop mega menu + mobile off-canvas accordion).
require_once __DIR__ . '/inc/topasia-menu.php';

// Top Asia — homepage redesign assets (front page only).
require_once __DIR__ . '/inc/home.php';

// Top Asia — global design system (subpages, WooCommerce product & news/blog templates).
require_once __DIR__ . '/inc/topasia-design-system.php';

// Top Asia — quote download popup modal + lead capture.
require_once __DIR__ . '/inc/topasia-quote-modal.php';

// Top Asia — floating contact buttons (Phone & Zalo: 0896 699 996).
require_once __DIR__ . '/inc/topasia-floating-contact.php';

// Top Asia — H1 cho trang chuyên mục + dọn H1 thừa ở hero trang chủ.
// Dùng file_exists() để nếu chưa upload inc/topasia-h1.php thì site vẫn chạy bình thường
// (require_once thẳng vào file không tồn tại sẽ gây fatal error, trắng site).
$topasia_h1_file = __DIR__ . '/inc/topasia-h1.php';
if ( file_exists( $topasia_h1_file ) ) {
	require_once $topasia_h1_file;
}
unset( $topasia_h1_file );


add_filter('use_block_editor_for_post', '__return_false');


 
function ilc_mce_buttons($buttons){
  array_push($buttons,
     "backcolor",
     "anchor",
     "hr",
     "sub",
     "sup",
     "fontselect",
     "fontsizeselect",
     "styleselect",
     "cleanup"
);
  return $buttons;
}
add_filter("mce_buttons", "ilc_mce_buttons");






//Thay đổi Giao Diện - Logo cho trang đăng nhập

add_action( 'login_enqueue_scripts', 'giuseart_login_enqueue_scripts' );
function giuseart_login_enqueue_scripts(){
echo '<style type="text/css" media="screen">';
// FIX: khối CSS cũ thiếu dấu } đóng nên nuốt luôn các rule phía sau.
echo '#login h1 a{background-image:url(https://i.ibb.co/C9v38Dd/logo.png);}';
echo '</style>';
}

function tp_custom_logo() { ?>
    <style type="text/css">
 .login #login_error{    box-shadow: 0 30px 80px rgba(37, 37, 37, 0.1) !important;    border-radius: 15px !important;}
 .login .message{border-left: 4px solid #ec1f27 !important; border-radius: 15px;box-shadow: 0 30px 80px rgba(37, 37, 37, 0.1) !important;}
 .login #nav a:hover, .login #backtoblog a:hover{color: #eb1f27 !important;}
 .login .button-primary {background: #eb1f27 !important;border-color: #eb1f27 #eb1f27 #eb1f27 !important;box-shadow: 0 1px 0 #eb1f27 !important;border-radius: 20px !important;color: #fff !importanttext-decoration: none;text-shadow: none !important;}
 input[type=checkbox]:checked:before{color: #eb1f27;}
 .login input[type=text]:focus, .login input[type=password]:focus, input[type=email]:focus{    box-shadow: 0 0 2px rgb(255, 255, 255) !important;}
   .login h1 a{    background-size: 115px !important;     height: 115px !important; width: 209px !important; }
#login {width: 450px !important;}
 .login form {box-shadow: 0 30px 80px rgba(37, 37, 37, 0.1) !important;border-radius: 15px;    padding: 40px !important;}.login form .input, .login form input[type=checkbox], .login input[type=text] {background: #f5f5f5;border-radius: 30px;border: none;box-shadow: none;}
 @media only screen and (max-width: 480px) {
  #login {width: 320px !important;}
}
    </style>
<?php }
add_action('login_enqueue_scripts', 'tp_custom_logo');

//Thay đổi Giao Diện - Logo cho trang đăng nhập
// Tạo shortcode hiển thị breadcrumb
add_shortcode('f_breadcrumbs', function() {
    ob_start();
    woocommerce_breadcrumb();
    return ob_get_clean();
});

// tạo poup trang web
/**
 * Popup tư vấn toàn website
 * Không hiển thị ở Giỏ hàng và Thanh toán
 * Không chạy trong UX Builder / Customizer / admin / AJAX (gây kẹt UX Builder).
 */
add_action('wp_footer', 'topasia_popup_form');

function topasia_popup_form() {

    // FIX 1 — không chạy trong admin, AJAX, Customizer.
    if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
        return;
    }

    // FIX 2 — không chạy trong iframe/app của UX Builder (Flatsome).
    if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
        return;
    }

    // FIX 3 — bọc is_cart()/is_checkout() trong function_exists để không fatal
    // khi WooCommerce bị tắt (ví dụ lúc test lỗi bằng cách deactivate plugin).
    if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
        return;
    }
?>
    <!-- Popup hidden -->
    <div id="topasia-popup-form" style="display:none; max-width:700px;">
        <?php echo do_shortcode('[block id="popup-form-tu-van"]'); ?>
    </div>

    <script>
    jQuery(document).ready(function($){

        // FIX 4 — chặn lỗi JS nếu Magnific Popup chưa được nạp trên trang.
        if ( ! $.magnificPopup ) {
            return;
        }

        // Chỉ hiện lại sau 60 giây (60000 ms).
        // Muốn 1 lần / 24h thì đổi 60000 thành 86400000.
        var popupShown = localStorage.getItem('topasia_popup');

        if(popupShown){
            var diff = Date.now() - parseInt(popupShown);

            if(diff < 60000){
                return;
            }
        }

        setTimeout(function(){

            $.magnificPopup.open({
                items: {
                    src: '#topasia-popup-form'
                },
                type: 'inline',
                removalDelay: 300
            });

            localStorage.setItem(
                'topasia_popup',
                Date.now()
            );

        },7000);

    });
    </script>
<?php
}