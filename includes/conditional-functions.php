<?php
/**
 * Conditional Functions.
 *
 * @package     AP_Popular_Posts/Functions
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Returns true if ajax update views is enabled.
 *
 * @since   1.0
 * @access  public
 * @return  bool
 */
function is_ap_popular_posts_ajax_update_views() {
    return ( get_option( 'ap_popular_posts_ajax_update_views' ) === '1' && ! is_customize_preview() );
}

/**
 * Returns true if ajax refresh fragments is enabled.
 *
 * @since   1.0
 * @access  public
 * @return  bool
 */
function is_ap_popular_posts_ajax_refresh_fragments() {
    return ( get_option( 'ap_popular_posts_ajax_refresh_fragments' ) === '1' && ! is_customize_preview() );
}
