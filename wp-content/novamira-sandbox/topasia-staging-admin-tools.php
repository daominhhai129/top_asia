<?php
/**
 * Keep the staging admin navigation organized.
 * Pro ID Guard stays visible in Plugins and the Admin bar.
 */

if (!defined('ABSPATH')) {
    return;
}

add_filter('plugins_list', 'topasia_staging_hide_proid_loader_row', 100);
function topasia_staging_hide_proid_loader_row(array $plugins): array
{
    unset($plugins['mustuse']['00-proid-guard-loader.php']);

    return $plugins;
}

function topasia_proid_guard_pause_deadline(): int
{
    static $initialized = false;
    static $deadline = 0;

    if ($initialized) {
        return $deadline;
    }

    $initialized = true;
    $deadline = (int) get_option('topasia_proid_guard_pause_until', 0);

    if ($deadline <= time()) {
        if ($deadline > 0) {
            delete_option('topasia_proid_guard_pause_until');
        }
        $deadline = 0;
    }

    return $deadline;
}

function topasia_proid_guard_remove_callbacks(): void
{
    global $wp_filter;

    if (!isset($wp_filter) || !is_array($wp_filter)) {
        return;
    }

    $plugin_root = trailingslashit(wp_normalize_path(WP_PLUGIN_DIR . '/proid-guard'));

    foreach ($wp_filter as $hook_name => $hook) {
        if (!$hook instanceof WP_Hook) {
            continue;
        }

        foreach ($hook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $callback = $entry['function'] ?? null;

                try {
                    if ($callback instanceof Closure) {
                        $reflection = new ReflectionFunction($callback);
                    } elseif (is_array($callback) && count($callback) === 2) {
                        $reflection = new ReflectionMethod($callback[0], $callback[1]);
                    } elseif (is_string($callback) && strpos($callback, '::') !== false) {
                        $reflection = new ReflectionMethod($callback);
                    } elseif (is_string($callback) && function_exists($callback)) {
                        $reflection = new ReflectionFunction($callback);
                    } elseif (is_object($callback) && is_callable($callback)) {
                        $reflection = new ReflectionMethod($callback, '__invoke');
                    } else {
                        continue;
                    }
                } catch (Throwable $error) {
                    continue;
                }

                $source_file = $reflection->getFileName();
                if (!is_string($source_file)) {
                    continue;
                }

                if (strpos(wp_normalize_path($source_file), $plugin_root) === 0) {
                    remove_filter((string) $hook_name, $callback, (int) $priority);
                }
            }
        }
    }
}

function topasia_proid_guard_action_url(string $action): string
{
    return wp_nonce_url(
        admin_url('admin-post.php?action=' . rawurlencode($action)),
        $action
    );
}

function topasia_proid_guard_admin_bar_controls($admin_bar): void
{
    if (!current_user_can('manage_options') || !is_object($admin_bar) || !method_exists($admin_bar, 'add_node')) {
        return;
    }

    $deadline = topasia_proid_guard_pause_deadline();

    if ($deadline > 0) {
        $admin_bar->add_node([
            'id'    => 'proid-guard-badge',
            'title' => 'Pro ID Guard: TẠM DỪNG',
            'href'  => admin_url('index.php'),
            'meta'  => ['title' => 'Tự hoạt động lại lúc ' . wp_date('H:i d/m/Y', $deadline)],
        ]);
        $action = 'topasia_proid_guard_resume_now';
        $title = 'Bật lại Pro ID Guard ngay';
    } else {
        $action = 'topasia_proid_guard_pause_8h';
        $title = 'Tạm dừng 8 giờ';
    }

    $admin_bar->add_node([
        'id'     => 'topasia-proid-guard-pause-control',
        'parent' => 'proid-guard-badge',
        'title'  => $title,
        'href'   => topasia_proid_guard_action_url($action),
        'meta'   => ['title' => 'Tạm ngừng các hook của Pro ID Guard; plugin vẫn Active.'],
    ]);
}

function topasia_proid_guard_pause_notice(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $deadline = topasia_proid_guard_pause_deadline();
    if ($deadline <= 0) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>Pro ID Guard đang tạm dừng.</strong> Các hook bảo vệ sẽ tự hoạt động lại lúc '
        . esc_html(wp_date('H:i d/m/Y', $deadline))
        . '. Plugin vẫn Active; cài đặt khóa sửa file trong quản trị vẫn giữ nguyên. <a href="'
        . esc_url(topasia_proid_guard_action_url('topasia_proid_guard_resume_now'))
        . '">Bật lại ngay</a></p></div>';
}

function topasia_proid_guard_finish_admin_action(): void
{
    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = admin_url('index.php');
    }

    $redirect = remove_query_arg(['_wpnonce', 'action'], $redirect);
    wp_safe_redirect($redirect);
    exit;
}

function topasia_proid_guard_pause_8h(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Bạn không có quyền thực hiện thao tác này.', 'Không đủ quyền', ['response' => 403]);
    }

    check_admin_referer('topasia_proid_guard_pause_8h');
    update_option('topasia_proid_guard_pause_until', time() + (8 * HOUR_IN_SECONDS), false);
    topasia_proid_guard_finish_admin_action();
}

function topasia_proid_guard_resume_now(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Bạn không có quyền thực hiện thao tác này.', 'Không đủ quyền', ['response' => 403]);
    }

    check_admin_referer('topasia_proid_guard_resume_now');
    delete_option('topasia_proid_guard_pause_until');
    topasia_proid_guard_finish_admin_action();
}

if (topasia_proid_guard_pause_deadline() > 0) {
    topasia_proid_guard_remove_callbacks();
}

add_action('admin_bar_menu', 'topasia_proid_guard_admin_bar_controls', 1000);
add_action('admin_notices', 'topasia_proid_guard_pause_notice', 1);
add_action('admin_post_topasia_proid_guard_pause_8h', 'topasia_proid_guard_pause_8h');
add_action('admin_post_topasia_proid_guard_resume_now', 'topasia_proid_guard_resume_now');
