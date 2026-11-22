<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OCB_Settings {
    const OPTION = 'ocb_settings';
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    public static function defaults() {
        return array(
            'enabled' => 1, 'call_enabled' => 1, 'zalo_enabled' => 1, 'messenger_enabled' => 1,
            'desktop' => 1, 'mobile' => 1, 'button_order' => 'call,zalo,messenger',
            'gap' => 12, 'button_size' => 48, 'radius' => 999, 'bar_bg' => '#ffffff',
            'call_bg' => '#16A34A', 'zalo_bg' => '#1677FF', 'messenger_bg' => '#0866FF',
            'text_color' => '#ffffff', 'z_index' => 99990, 'bottom_offset' => 0,
            'hotlines' => array(
                'north' => array('enabled' => 1, 'name' => 'Miền Bắc', 'description' => 'Hotline khu vực miền Bắc', 'phone' => '0896 699 996'),
                'central' => array('enabled' => 1, 'name' => 'Miền Trung', 'description' => 'Hotline khu vực miền Trung', 'phone' => '0934 657 227'),
                'south' => array('enabled' => 1, 'name' => 'Miền Nam', 'description' => 'Hotline khu vực miền Nam', 'phone' => '0934 468 778'),
            ),
            'zalo_phone' => '0896699996', 'zalo_url' => 'https://zalo.me/0896699996', 'zalo_target' => '_blank',
            'messenger_page_id' => '100737662960618', 'messenger_url' => '',
            'display_mode' => 'all', 'include_ids' => '', 'exclude_ids' => '',
        );
    }

    public static function activate() {
        if ( false === get_option( self::OPTION, false ) ) {
            add_option( self::OPTION, self::defaults(), '', false );
        }
    }

    public function get() {
        $saved = get_option( self::OPTION, array() );
        if ( ! is_array( $saved ) ) { $saved = array(); }
        $settings = array_replace_recursive( self::defaults(), $saved );
        // Preserve the Page ID shown in the supplied reference for existing installs
        // that previously had Messenger enabled but no destination configured.
        if ( empty( $settings['messenger_page_id'] ) && empty( $settings['messenger_url'] ) ) {
            $settings['messenger_page_id'] = '100737662960618';
        }
        return $settings;
    }

    public function sanitize( $input ) {
        $defaults = self::defaults();
        $input = is_array( $input ) ? $input : array();
        $saved = get_option( self::OPTION, array() );
        if ( ! is_array( $saved ) ) { $saved = array(); }
        // Preserve settings saved on other tabs instead of replacing them with defaults.
        $out = array_replace_recursive( $defaults, $saved );
        $tab = isset( $input['_ocb_tab'] ) ? sanitize_key( $input['_ocb_tab'] ) : 'legacy';

        $has = static function( $key ) use ( $input ) {
            return array_key_exists( $key, $input );
        };
        $clean_text = static function( $key, $fallback = '' ) use ( $input ) {
            return isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $fallback;
        };

        if ( 'general' === $tab || 'legacy' === $tab ) {
            foreach ( array( 'enabled', 'call_enabled', 'zalo_enabled', 'messenger_enabled', 'desktop', 'mobile' ) as $key ) {
                if ( 'general' === $tab || $has( $key ) ) {
                    $out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
                }
            }
            if ( isset( $input['button_order'] ) ) {
                $allowed = array( 'call', 'zalo', 'messenger' );
                $order = array_map( 'trim', explode( ',', sanitize_text_field( $input['button_order'] ) ) );
                $order = array_values( array_unique( array_intersect( $order, $allowed ) ) );
                foreach ( $allowed as $item ) {
                    if ( ! in_array( $item, $order, true ) ) { $order[] = $item; }
                }
                $out['button_order'] = implode( ',', $order );
            }
            foreach ( array( 'gap' => 0, 'button_size' => 32, 'radius' => 0, 'z_index' => 1, 'bottom_offset' => 0 ) as $key => $min ) {
                if ( isset( $input[ $key ] ) ) {
                    $out[ $key ] = min( 2147483000, max( $min, absint( $input[ $key ] ) ) );
                }
            }
            foreach ( array( 'bar_bg', 'call_bg', 'zalo_bg', 'messenger_bg', 'text_color' ) as $key ) {
                if ( isset( $input[ $key ] ) ) {
                    $color = sanitize_hex_color( $input[ $key ] );
                    if ( $color ) { $out[ $key ] = $color; }
                }
            }
        }

        if ( 'hotline' === $tab || 'legacy' === $tab ) {
            foreach ( $defaults['hotlines'] as $region => $default ) {
                if ( 'hotline' === $tab ) {
                    $row = isset( $input['hotlines'][ $region ] ) && is_array( $input['hotlines'][ $region ] ) ? $input['hotlines'][ $region ] : array();
                    $out['hotlines'][ $region ]['enabled'] = empty( $row['enabled'] ) ? 0 : 1;
                } elseif ( isset( $input['hotlines'][ $region ] ) && is_array( $input['hotlines'][ $region ] ) ) {
                    $row = $input['hotlines'][ $region ];
                    if ( array_key_exists( 'enabled', $row ) ) {
                        $out['hotlines'][ $region ]['enabled'] = empty( $row['enabled'] ) ? 0 : 1;
                    }
                } else {
                    continue;
                }
                foreach ( array( 'name', 'description', 'phone' ) as $field ) {
                    if ( isset( $row[ $field ] ) ) {
                        $value = sanitize_text_field( $row[ $field ] );
                        $out['hotlines'][ $region ][ $field ] = 'phone' === $field
                            ? preg_replace( '/[^0-9+().\-\s]/', '', $value )
                            : $value;
                    }
                }
            }
        }

        if ( 'zalo' === $tab || 'legacy' === $tab ) {
            if ( 'zalo' === $tab || $has( 'zalo_enabled' ) ) {
                $out['zalo_enabled'] = empty( $input['zalo_enabled'] ) ? 0 : 1;
            }
            if ( isset( $input['zalo_phone'] ) ) { $out['zalo_phone'] = sanitize_text_field( $input['zalo_phone'] ); }
            if ( isset( $input['zalo_url'] ) ) { $out['zalo_url'] = esc_url_raw( $input['zalo_url'], array( 'http', 'https' ) ); }
            if ( isset( $input['zalo_target'] ) ) { $out['zalo_target'] = '_self' === $input['zalo_target'] ? '_self' : '_blank'; }
        }

        if ( 'messenger' === $tab || 'legacy' === $tab ) {
            if ( 'messenger' === $tab || $has( 'messenger_enabled' ) ) {
                $out['messenger_enabled'] = empty( $input['messenger_enabled'] ) ? 0 : 1;
            }
            if ( isset( $input['messenger_page_id'] ) ) { $out['messenger_page_id'] = sanitize_text_field( $input['messenger_page_id'] ); }
            if ( isset( $input['messenger_url'] ) ) { $out['messenger_url'] = esc_url_raw( $input['messenger_url'], array( 'http', 'https' ) ); }
        }

        if ( 'display' === $tab || 'legacy' === $tab ) {
            if ( isset( $input['display_mode'] ) ) { $out['display_mode'] = 'selected' === $input['display_mode'] ? 'selected' : 'all'; }
            foreach ( array( 'include_ids', 'exclude_ids' ) as $key ) {
                if ( isset( $input[ $key ] ) ) { $out[ $key ] = sanitize_text_field( $input[ $key ] ); }
            }
            if ( isset( $input['bottom_offset'] ) ) { $out['bottom_offset'] = max( 0, absint( $input['bottom_offset'] ) ); }
            if ( 'display' === $tab ) {
                $out['desktop'] = empty( $input['desktop'] ) ? 0 : 1;
                $out['mobile'] = empty( $input['mobile'] ) ? 0 : 1;
                if ( isset( $input['z_index'] ) ) { $out['z_index'] = max( 1, absint( $input['z_index'] ) ); }
            }
        }

        // Analytics has no editable fields; do not alter any saved settings.
        return $out;
    }
}
