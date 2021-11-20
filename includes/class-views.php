<?php
/**
 * Manage post views.
 *
 * @package     AP_Popular_Posts/Classes
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_Views class.
 */
class AP_Popular_Posts_Views {

    /**
     * Constructor.
     *
     * @since   1.0.0
     * @access  public
     */
    public function __construct() {
        add_action( 'wp_footer', array( $this, 'wp_footer' ), 99 );
        add_action( 'after_delete_post', array( $this, 'delete_views_on_post_delete' ) );
        add_action( 'trashed_post', array( $this, 'delete_views_on_post_delete' ) );
    }

    /**
     * Hook into wp_footer to trigger post view on single post
     * if ajax update views is disabled.
     *
     * @since   1.0.0
     * @access  public
     */
    public function wp_footer() {
        if ( is_singular( 'post' ) && ! is_ap_popular_posts_ajax_update_views() && ! is_customize_preview() ) {
            $this->trigger_post_view( get_the_ID() );
        }
    }

    /**
     * When the post is deleted or trashed delete it from views.
     *
     * @since   1.0.0
     * @access  public
     * @param   int $post_id Post ID.
     */
    public function delete_views_on_post_delete( $post_id ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "
                DELETE FROM {$wpdb->prefix}ap_popular_posts
                WHERE post_id = %d
                ",
                $post_id
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "
                DELETE FROM {$wpdb->prefix}ap_popular_posts_cache
                WHERE post_id = %d
                ",
                $post_id
            )
        );

        ap_popular_posts_delete_transients();
    }

    /**
     * Return true if bot is detected. (Not implemented yet)
     *
     * @since   1.0.0
     * @access  public
     * @return  bool
     */
    private function is_bot() {
        if (preg_match('/bot|crawl|curl|dataprovider|search|get|spider|find|java|majesticsEO|google|yahoo|teoma|contaxe|yandex|libwww-perl|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT'])) {
            // is bot
        }

        if (preg_match('/apple|baidu|bingbot|facebookexternalhit|googlebot|-google|ia_archiver|msnbot|naverbot|pingdom|seznambot|slurp|teoma|twitter|yandex|yeti/i', $_SERVER['HTTP_USER_AGENT'])) {
            // allowed bot
        }

        if ( preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT'] ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return true if it everything is ok to trigger post view.
     *
     * Theme developers can use filter ap_popular_posts_trigger_view
     * to disable constant post views updates on theme demo sites.
     *
     * @since   1.0.0
     * @access  private
     * @param   int     $post_id
     * @return  bool    True on success
     */
    private function can_trigger_post_view( $post_id ) {
        if (
            $post_id
            && 'post' == get_post_type( $post_id )
            && apply_filters( 'ap_popular_posts_trigger_view', true, $post_id )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Trigger post view.
     *
     * @since   1.0.0
     * @access  public
     * @param   int     $post_id
     * @return  bool    True on success
     */
    public function trigger_post_view( $post_id ) {
        global $wpdb;

        $post_id = absint( $post_id );

        if ( $this->can_trigger_post_view( $post_id ) ) {

            $time = time();
            $use_object_cache = get_option( 'ap_popular_posts_use_object_cache' );
            $sampling_rate = (int) get_option( 'ap_popular_posts_data_sampling_rate' );

            if ( wp_using_ext_object_cache() && $use_object_cache == '1' ) {

                $view = sprintf( '%d,%d', $post_id, $time );

                if ( $sampling_rate > 0 ) {

                    if ( mt_rand( 0, $sampling_rate ) === 0 ) {

                        $sviews = array();

                        for ( $i = 0; $i < $sampling_rate; $i++ ) {
                            $sviews[] = $view;
                        }

                        $sviews = implode( '|', $sviews );

                        if ( false === ( $views = wp_cache_get( 'views', 'ap_popular_posts' ) ) ) {
                            $views = $sviews;
                        } else {
                            $views = $views . '|' . $sviews;
                        }

                        if ( wp_cache_set( 'views', $views, 'ap_popular_posts' ) ) {
                            $this->transfer_object_cache_views( $views );
                            return true;
                        }
                    }

                } else {

                    if ( false === ( $views = wp_cache_get( 'views', 'ap_popular_posts' ) ) ) {
                        $views = $view;
                    } else {
                        $views = $views . '|' . $view;
                    }

                    if ( wp_cache_set( 'views', $views, 'ap_popular_posts' ) ) {
                        $this->transfer_object_cache_views( $views );
                        return true;
                    }
                }

            } else {

                if ( $sampling_rate > 0 ) {

                    if ( mt_rand( 0, $sampling_rate ) === 0 ) {

                        $prepared_values = array();

                        for ( $i = 0; $i < $sampling_rate; $i++ ) {
                            $prepared_values[] = $wpdb->prepare( '(%d,%d)', $post_id, $time );
                        }

                        $prepared_values = implode( ',', $prepared_values );

                        if ( $wpdb->query( "INSERT INTO {$wpdb->prefix}ap_popular_posts_cache (post_id, view_time) VALUES $prepared_values" ) ) {
                            $cache_count = $wpdb->insert_id + $sampling_rate - 1;
                            $this->transfer_cache_views( $cache_count );
                            return true;
                        }
                    }

                } else {

                    if ( $wpdb->insert(
                        "{$wpdb->prefix}ap_popular_posts_cache",
                        array(
                            'post_id' => $post_id,
                            'view_time' => $time,
                        ),
                        array(
                            '%d',
                            '%d'
                        )
                    ) ) {
                        $this->transfer_cache_views( $wpdb->insert_id );
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Transfer post views from object cache to cache table.
     *
     * If the object cache has more than 350 views or 5 min is expired.
     *
     * @since   1.0.0
     * @access  private
     * @param   string $views Views
     */
    private function transfer_object_cache_views( $views ) {
        global $wpdb;

        $views = explode( '|', $views );
        $cache_count = count( $views );

        if ( $cache_count > 350 || false === wp_cache_get( 'transfer_object_cache_views', 'ap_popular_posts' ) ) {

            $prepared_values = array();

            foreach ( $views as $key => $view ) {
                $view = explode( ',', $view );
                if ( isset( $view[1] ) ) {
                    $prepared_values[] = $wpdb->prepare( '(%d,%d)', $view[0], $view[1] );
                }
            }

            $prepared_values = implode( ',', $prepared_values );

            $wpdb->query( "INSERT INTO {$wpdb->prefix}ap_popular_posts_cache (post_id, view_time) VALUES $prepared_values" );

            $count = $wpdb->insert_id + $wpdb->rows_affected - 1;
            $this->transfer_cache_views( $count );

            wp_cache_delete( 'views', 'ap_popular_posts' );

            /**
             * Filters the expiration time for a transfer_object_cache_views cache.
             *
             * @since   1.2.0
             */
            $expiration = apply_filters( 'ap_popular_posts_transfer_object_cache_views_interval', 5 * MINUTE_IN_SECONDS );
            wp_cache_set( 'transfer_object_cache_views', 'transferred', 'ap_popular_posts', $expiration );
        }
    }

    /**
     * Transfer post views from cache table to main table.
     *
     * If the cache has more than 4700 views or 20 min is expired.
     *
     * @since   1.0.0
     * @access  private
     * @param   int $cache_count Number of view in cache
     */
    private function transfer_cache_views( $cache_count ) {
        global $wpdb;

        if ( $cache_count > 4700 || false === ap_popular_posts_get_transient( 'transfer_cache_views' ) ) {

            $wpdb->query( "INSERT INTO {$wpdb->prefix}ap_popular_posts (post_id, view_time) SELECT post_id, view_time FROM {$wpdb->prefix}ap_popular_posts_cache" );

            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}ap_popular_posts_cache" );

            /**
             * Filters the expiration time for a transfer_cache_views transient/cache.
             *
             * @since   1.2.0.
             */
            $expiration = apply_filters( 'ap_popular_posts_transfer_cache_views_interval', 20 * MINUTE_IN_SECONDS );
            ap_popular_posts_set_transient( 'transfer_cache_views', 'transferred', $expiration );

        } else {
            $this->delete_old_views( $cache_count );
        }
    }

    /**
     * Delete 12500 views older than 7 days.
     *
     * If the cache is bigger than 4600 views or 20 min is expired.
     *
     * It's limited to 12500 rows because it's better to delete fewer rows at time
     * than several tens of thousands that a high traffic site can get.
     *
     * @since   1.0.0
     * @access  private
     * @param   int $cache_count Number of views in cache
     */
    private function delete_old_views( $cache_count ) {
        global $wpdb;

        if ( $cache_count > 4600 || false === ap_popular_posts_get_transient( 'delete_old_views' ) ) {

            $max_interval = ap_popular_posts()->get_max_interval();

            /**
             * Filters the number of days that are considered old views.
             *
             * By default the largest interval time is used.
             *
             * @since   1.0.0.
             */
            $days = apply_filters( 'ap_popular_posts_old_views_days', $max_interval );

            /**
             * Filters the SQL query limit for deleting old views.
             *
             * @since   1.0.0.
             */
            $limit = apply_filters( 'ap_popular_posts_delete_old_views_limit', 12500 );

            $wpdb->query(
                $wpdb->prepare(
                    "
                    DELETE FROM {$wpdb->prefix}ap_popular_posts
                    WHERE view_time < %d
                    LIMIT %d
                    ",
                    ap_popular_posts()->get_timestamp( $days ),
                    $limit
                )
            );

            /**
             * Filters the expiration time for a delete_old_views transient.
             *
             * @since   1.2.0.
             */
            $expiration = apply_filters( 'ap_popular_posts_delete_old_views_interval', 20 * MINUTE_IN_SECONDS );
            ap_popular_posts_set_transient( 'delete_old_views', 'deleted', $expiration );
        }
    }

    /**
     * Populate with dummy views to test queries.
     *
     * @since   1.0.0
     * @access  public
     * @param   int     $number_of_rows  Number of rows to generate
     * @param   string  $period          In what time period
     * @param   bool    $truncate        If true truncate tables
     */
    public function populate_dummy_views( $number_of_rows, $period = '-10 day', $truncate = false ) {
        global $wpdb;

        if ( $truncate ) {

            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}ap_popular_posts" );
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}ap_popular_posts_cache" );

        } else {

            $values = array();
            $ids = get_posts( array(
                'post_type' => 'post',
                'posts_per_page' => 50,
                'post_status' => 'publish',
                'fields' => 'ids',
            ) );

            for ( $i = 0; $i < $number_of_rows; $i++ ) {

                $rand = array_rand( $ids );
                $id = $ids[$rand];

                $start_time = strtotime( $period );
                $end_time = time();
                $time = mt_rand( $start_time, $end_time );

                $values[] = $wpdb->prepare( '(%d,%d)', $id, $time );
            }

            $values = implode( ',', $values );

            $wpdb->query( "INSERT INTO {$wpdb->prefix}ap_popular_posts_cache (post_id, view_time) VALUES $values" );
            $wpdb->query( "INSERT INTO {$wpdb->prefix}ap_popular_posts (post_id, view_time) SELECT post_id, view_time FROM {$wpdb->prefix}ap_popular_posts_cache" );
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}ap_popular_posts_cache" );
        }
    }

}
