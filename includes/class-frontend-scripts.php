<?php
/**
 * Frontend scripts.
 *
 * @package     AP_Popular_Posts/Classes
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_Frontend_Scripts class.
 */
class AP_Popular_Posts_Frontend_Scripts {

    /**
     * Hook in methods.
     *
     * @since   1.0.0
     * @access  public
     */
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
        add_action( 'wp_print_scripts', array( __CLASS__, 'localize_printed_scripts' ) );
    }

    /**
     * Enqueue frontend scripts.
     *
     * @since   1.0.0
     * @access  public
     */
    public static function enqueue_scripts() {

        $assets_url = ap_popular_posts()->get_plugin_url() . 'assets/';
        $plugin_version = ap_popular_posts()->get_version();

        // Register JS
        wp_register_script( 'ap-popular-posts', $assets_url . 'js/main.js', array( 'jquery' ), $plugin_version, true );

        // Enqueue JS
        if ( is_ap_popular_posts_ajax_update_views() || is_ap_popular_posts_ajax_refresh_fragments() ) {
            wp_enqueue_script( 'ap-popular-posts' );
        }
    }

    /**
     * Localize scripts.
     *
     * @since   1.0.0
     * @access  public
     */
    public static function localize_printed_scripts() {

        wp_localize_script(
            'ap-popular-posts',
            'ap_popular_posts_vars',
            array(
                'rest_url' => esc_url_raw( rest_url() ),
                'ajax_url' => ap_popular_posts()->ajax->get_url(),
                'is_single' => is_singular( 'post' ) ? 'true' : 'false',
                'post_id' => get_the_ID(),
                'ajax_update_views' => is_ap_popular_posts_ajax_update_views() ? 'true' : 'false',
                'ajax_refresh_fragments' => is_ap_popular_posts_ajax_refresh_fragments() ? 'true' : 'false',
                'fragments' => array_keys( ap_popular_posts()->ajax->get_fragments() ),
            )
        );

    }

}

AP_Popular_Posts_Frontend_Scripts::init();
