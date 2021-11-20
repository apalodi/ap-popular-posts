<?php
/**
 * Custom template tags that can be overwritten by themes.
 *
 * @package     AP_Popular_Posts/Functions
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'ap_popular_posts_widget_content' ) ) {
    /**
     * Output the widget content.
     *
     * @since   1.0
     * @access  public
     * @param   array $args Pass args with the template load.
     */
    function ap_popular_posts_widget_content( $args = array() ) {
        ap_popular_posts_get_template( 'widget-content', $args );
    }
}
