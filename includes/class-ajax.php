<?php
/**
 * AJAX Event Handlers.
 *
 * @package     AP_Popular_Posts/Classes
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_AJAX class.
 */
class AP_Popular_Posts_AJAX {

    /**
     * Hook in methods.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __construct() {

        /**
         * Trigger right after the theme is setup because here only 4 SQL queries are executed and with
         * later actions like template_redirect over 20 SQL queries are executed loading all posts.
         *
         * Note: user isn't loaded yet here, only options
         */
        add_action( 'after_setup_theme', array( $this, 'do_ajax' ), 99 );

        if ( is_ap_popular_posts_ajax_update_views() ) {
            add_action( 'ap_popular_posts_ajax_update_views', array( $this, 'update_views' ) );
        }

        if ( is_ap_popular_posts_ajax_refresh_fragments() ) {
            add_action( 'ap_popular_posts_ajax_refresh_fragments', array( $this, 'get_refreshed_fragments' ) );
        }
    }

    /**
     * Get Ajax url.
     *
     * @since   1.0.0
     * @access  public
     * @param   string $request Optional.
     * @return  string
     */
    public function get_url( $request = '%%action%%' ) {
        return esc_url_raw( add_query_arg( 'ap-popular-posts-ajax', $request, home_url( '/', 'relative' ) ) );
    }

    /**
     * Send headers for Ajax Requests.
     *
     * @since   1.0.0
     * @access  public
     */
    private function ajax_headers() {

        ap_popular_posts_define_constant( 'DOING_AJAX', true );

        send_origin_headers();

        @header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        @header( 'X-Robots-Tag: noindex' );

        send_nosniff_header();
        nocache_headers();

        ap_popular_posts_define_constant( 'DONOTCACHEPAGE', true );
        ap_popular_posts_define_constant( 'DONOTCACHEOBJECT', true );
        ap_popular_posts_define_constant( 'DONOTCACHEDB', true );

        status_header( 200 );
    }

    /**
     * Check for Ajax request and fire action.
     *
     * @since   1.0.0
     * @access  public
     */
    public function do_ajax() {

        if ( ! empty( $_GET['ap-popular-posts-ajax'] ) ) {
            $this->ajax_headers();
            $action = sanitize_text_field( wp_unslash( $_GET['ap-popular-posts-ajax'] ) );
            $action = str_replace( '-', '_', $action );

            // If no action is registered, return a Bad Request response.
            if ( ! has_action( 'ap_popular_posts_ajax_' . $action ) ) {
                wp_die( '', 400 );
            }

            do_action( 'ap_popular_posts_ajax_' . $action );
            wp_die();
        }
    }

    /**
     * Get fragments.
     *
     * @since   1.0.0
     * @access  public
     * @return  array $fragments
     */
    public function get_fragments() {

        /**
         * Filters the fragments so custom fragments can be added.
         *
         * The array key is the HTML class of the fragment and the value
         * is a callback function that is called to refresh the fragment.
         *
         * function my_custom_app_fragments( $fragments ) {
         *      $fragments['.my-custom-html-class'] = 'my_custom_app_callback_function';
         *      return $fragments;
         * }
         * add_filter( 'ap_popular_posts_fragments', 'my_custom_app_fragments' );
         *
         * function my_custom_app_callback_function( $args ) {
         *      // $args are your options from the data-options attribute
         *      // if it is set on your HTML tag with custom HTML class
         *      echo 'fragment is refreshed';
         * }
         *
         * @since   1.0.0
         */
        $fragments = apply_filters( 'ap_popular_posts_fragments', array(
            '.ap-popular-posts-widget-content' => array( $this, 'refresh_widgets' ),
        ) );

        return $fragments;
    }

    /**
     * Get refreshed fragments.
     *
     * @since   1.0.0
     * @access  public
     */
    public function get_refreshed_fragments() {

        if ( ! isset( $_POST['fragments'] ) ) {
            wp_send_json_error();
        }

        $fragments = $_POST['fragments'];
        $_fragments = $this->get_fragments();
        $data = array();

        foreach ( $fragments as $key => $fragment ) {
            ob_start();
            call_user_func( $_fragments[$fragment['key']], $fragment['args'] );
            $data[$fragment['instance']] = ob_get_clean();
        }

        wp_send_json_success( $data );
    }

    /**
     * Refresh widgets.
     *
     * @since   1.0.0
     * @access  public
     * @param   array $args
     * @return  string html
     */
    public function refresh_widgets( $args ) {

        $instances = get_option( 'widget_ap-popular-posts' );

        if ( ! isset( $instances[$args['widget_id']] ) ) {
            return false;
        }

        $instance = $instances[$args['widget_id']];
        $interval = absint( $instance['interval'] );
        $number = absint( $instance['number'] );
        $ids = ap_popular_posts_get_ids( $interval, $number );

        $query = new WP_Query( apply_filters( 'ap_popular_posts_widget_query_args', array(
            'post_type' => 'post',
            'posts_per_page'=> $number,
            'post_status' => 'publish',
            'post__in' => $ids,
            'orderby' => 'post__in',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ), $instance ) );

        ap_popular_posts_widget_content( array( 'query' => $query ) );
    }

    /**
     * Update views count.
     *
     * @since   1.0.0
     * @access  public
     */
    public function update_views() {
        if ( ap_popular_posts()->views->trigger_post_view( $_POST['post_id'] ) ) {
            wp_send_json_success();
        }
        wp_send_json_error();
    }

}
