<?php
/**
 * Query.
 *
 * @package     AP_Popular_Posts/Classes
 * @since       1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * AP_Popular_Posts_Query class.
 */
class AP_Popular_Posts_Query {

    /**
     * Constructor.
     *
     * @since   1.2.0
     * @access  public
     */
    public function __construct() {
        add_filter( 'posts_pre_query', array( $this, 'posts_pre_query' ), 10, 2 );
        add_filter( 'posts_join', array( $this, 'posts_join' ), 10, 2 );
        add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 2 );
        add_filter( 'posts_groupby', array( $this, 'posts_groupby' ), 10, 2 );
        add_filter( 'posts_orderby', array( $this, 'posts_orderby' ), 10, 2 );
        add_filter( 'posts_results', array( $this, 'posts_results' ), 10, 2 );
    }

    /**
     * Hook into posts_pre_query to use custom query to get popular posts.
     *
     * @since   1.2.0
     * @access  public
     * @param   array    $ids      Array of post data.
     * @param   WP_Query $query    The WP_Query instance (passed by reference).
     * @return  array    $ids      Array of post data.
     */
    public function posts_pre_query( $ids, $query ) {
        if ( isset( $query->query_vars['ap_popular_posts'] ) ) {
            $posts_per_page = $query->query_vars['posts_per_page'];
            $interval = $query->query_vars['ap_popular_posts_interval'];

            if ( $this->using_wp_query() ) {
                $cached_ids = $this->get_cached_post_ids( $interval, $posts_per_page, 'ids_wp' );

                if ( $cached_ids ) {
                    _prime_post_caches( $cached_ids );
                    $found_posts = count( $cached_ids );
                    $query->found_posts = $found_posts;
                    $query->max_num_pages = ceil( $found_posts / $posts_per_page );
                    $query->set( 'ap_popular_posts_has_posts_pre_query', true );

                    return $cached_ids;
                }

                return $ids;
            }

            $ids = $this->get_ids( $interval, $posts_per_page );

            _prime_post_caches( $ids );
            $found_posts = count( $ids );
            $query->found_posts = $found_posts;
            $query->max_num_pages = ceil( $found_posts / $posts_per_page );

            return $ids;
        }

        return $ids;
    }

    /**
     * Hook into posts_join to load popular posts.
     *
     * @since   1.2.0
     * @access  public
     * @param   string   $join      The JOIN clause of the query.
     * @param   WP_Query $query     The WP_Query instance (passed by reference).
     * @return  string   $join      The JOIN clause of the query.
     */
    public function posts_join( $join, $query ) {
        if ( isset( $query->query_vars['ap_popular_posts'] ) ) {
            global $wpdb;
            $join .= " JOIN {$wpdb->prefix}ap_popular_posts ON ({$wpdb->posts}.ID = {$wpdb->prefix}ap_popular_posts.post_id)";
        }

        return $join;
    }

    /**
     * Hook into posts_where to load popular posts.
     *
     * @since   1.2.0
     * @access  public
     * @param   string   $where     The WHERE clause of the query.
     * @param   WP_Query $query     The WP_Query instance (passed by reference).
     * @return  string   $where     The WHERE clause of the query.
     */
    public function posts_where( $where, $query ) {
        if ( isset( $query->query_vars['ap_popular_posts'] ) ) {
            if ( isset( $query->query_vars['ap_popular_posts_interval'] ) ) {
                global $wpdb;
                $interval = (int) $query->query_vars['ap_popular_posts_interval'];
                $interval = ap_popular_posts()->get_timestamp( $interval );
                $prepared_interval = $wpdb->prepare( '%d', $interval );
                $query->set( 'ap_popular_posts_interval_timestamp', $prepared_interval );
                $where .= " AND {$wpdb->prefix}ap_popular_posts.view_time > {$prepared_interval}";
            }
        }

        return $where;
    }

    /**
     * Hook into posts_groupby to load popular posts.
     *
     * @since   1.2.0
     * @access  public
     * @param   string   $groupby   The GROUP BY clause of the query.
     * @param   WP_Query $query     The WP_Query instance (passed by reference).
     * @return  string   $groupby   The GROUP BY clause of the query.
     */
    public function posts_groupby( $groupby, $query ) {
        if ( isset( $query->query_vars['ap_popular_posts'] ) ) {
            global $wpdb;
            $groupby = "{$wpdb->prefix}posts.ID";
        }

        return $groupby;
    }

    /**
     * Hook into posts_orderby to load popular posts.
     *
     * @since   1.2.0
     * @access  public
     * @param   string   $orderby   The ORDER BY clause of the query.
     * @param   WP_Query $query     The WP_Query instance (passed by reference).
     * @return  string   $orderby   The ORDER BY clause of the query.
     */
    public function posts_orderby( $orderby, $query ) {
        if ( isset( $query->query_vars['ap_popular_posts'] ) ) {
            global $wpdb;
            $countby = "{$wpdb->prefix}posts.ID";
            $orderby = "COUNT({$countby}) DESC";
        }

        return $orderby;
    }

    /**
     * Hook into posts_results to run the query again with larger interval
     * if we got less popular posts than requested.
     *
     * @since   1.2.0
     * @access  public
     * @param   array    $posts     Array of post objects.
     * @param   WP_Query $query     The WP_Query instance (passed by reference).
     * @return  array    $posts     Array of post objects.
     */
    public function posts_results( $posts, $query ) {
        if ( isset( $query->query_vars['ap_popular_posts'] ) ) {
            $posts_per_page = $query->query_vars['posts_per_page'];
            $interval = (int) $query->query_vars['ap_popular_posts_interval'];
            $has_posts_pre_query = isset( $query->query_vars['ap_popular_posts_has_posts_pre_query'] );

            // if we are using our custom query
            if ( ! $this->using_wp_query() ) {
                return $posts;
            }

            // we already got the ids in the posts_pre_query filter
            if ( $has_posts_pre_query ) {
                return $posts;
            }

            // if there is an exact number of posts
            if ( count( $posts ) === $posts_per_page ) {
                $this->cache_post_ids( $posts, $interval, $posts_per_page, 'ids_wp' );
                return $posts;
            }

            $max_interval = ap_popular_posts()->get_max_interval();

            // we already searched the largest interval
            if ( $interval === $max_interval ) {
                $this->cache_post_ids( $posts, $interval, $posts_per_page, 'ids_wp' );
                return $posts;
            }

            $posts = $this->get_extended_interval_ids( $posts, $query );
            $this->cache_post_ids( $posts, $interval, $posts_per_page, 'ids_wp' );

            return $posts;
        }

        return $posts;
    }

    /**
     * Cache post ids.
     *
     * @since   1.2.0
     * @access  public
     * @param   array    $posts         Array of post objects or ids.
     * @param   int      $interval      Interval in days
     * @param   int      $number        Number of posts
     * @param   string   $transient     The transient name
     * @return  void
     */
    public function cache_post_ids( $posts, $interval, $number, $transient ) {
        $expiration = $this->get_ids_expiration( $interval );
        $ids = array_map( function( $post ) {
            return $post instanceof WP_Post ? $post->ID : $post;
        }, $posts );

        if ( $ids ) {
            ap_popular_posts_set_transient( "{$transient}_{$interval}_{$number}", $ids, $expiration );
        }
    }

    /**
     * Get cached post ids.
     *
     * @since   1.2.0
     * @access  public
     * @param   int      $interval      Interval in days
     * @param   int      $number        Number of posts
     * @param   string   $transient     The transient name
     * @return  mixed    $ids           Array of post ids or false.
     */
    public function get_cached_post_ids( $interval, $number, $transient ) {
        return ap_popular_posts_get_transient( "{$transient}_{$interval}_{$number}" );
    }

    /**
     * Modify and run the SQL query to extend the popular posts interval.
     *
     * @since   1.2.0
     * @access  private
     * @param   array    $posts     Array of post objects.
     * @param   WP_Query $query     The WP_Query instance (passed by reference).
     * @return  array    $posts     Array of post objects.
     */
    private function get_extended_interval_ids( $posts, $query ) {
        global $wpdb;

        $ids = wp_list_pluck( $posts, 'ID' );
        $count_ids = count( $ids );
        $posts_per_page = $query->query_vars['posts_per_page'];
        $number = $posts_per_page - $count_ids;
        $prepared_ids = implode( ',', $ids );

        $interval = $query->query_vars['ap_popular_posts_interval_timestamp'];
        $prepared_interval = $wpdb->prepare( '%d', $interval );

        $max_interval = ap_popular_posts()->get_max_interval();
        $max_interval = ap_popular_posts()->get_timestamp( $max_interval );
        $prepared_max_interval = $wpdb->prepare( '%d', $max_interval );

        $search = "{$wpdb->prefix}ap_popular_posts.view_time > {$prepared_interval}";
        $replace = "{$wpdb->prefix}ap_popular_posts.view_time > {$prepared_max_interval}";

        if ( $count_ids > 0 ) {
            $replace .= " AND {$wpdb->prefix}posts.ID NOT IN ({$prepared_ids})";
        }

        $new_request = str_replace( $search, $replace, $query->request );
        $new_posts = $wpdb->get_col( $new_request );

        if ( $new_posts ) {
            $new_posts = array_slice( $new_posts, 0, $number );
            _prime_post_caches( $new_posts );
            $new_posts = array_map( 'get_post', $new_posts );

            return array_merge( $posts, $new_posts );
        }

        return $posts;
    }

    /**
     * Recursive get popular posts ids.
     *
     * @since   1.0.0
     * @access  private
     * @param   int     $interval       Interval in days
     * @param   int     $number         Number of posts to get
     * @param   int     $total_count    Number of posts found
     * @param   array   $ids            Selected ids
     * @return  array   $popular_ids    IDs
     */
    private function get_ids_recursive( $interval, $number, $total_count = 0, $ids = array() ) {
        global $wpdb;

        $additional_ids = array();

        $popular_ids = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT      post_id
                FROM        {$wpdb->prefix}ap_popular_posts
                WHERE       view_time > %d
                GROUP BY    post_id DESC
                ORDER BY    COUNT(post_id) DESC
                LIMIT       %d
                ",
                ap_popular_posts()->get_timestamp( $interval ),
                $number
            )
        );

        $new_ids = array_diff( $popular_ids, $ids );
        $count = count( $new_ids );
        $total_count = $total_count + $count;
        $interval = $interval * 2;
        $has_rows = false;

        if ( $total_count < 1 ) {
            if ( $wpdb->get_var( "SELECT post_id FROM {$wpdb->prefix}ap_popular_posts LIMIT 1" ) ) {
                $has_rows = true;
            }
        } else {
            $has_rows = true;
        }

        $max_interval = ap_popular_posts()->get_max_interval();

        /**
         * If the returned number is lower than what we asked for
         * run the function again with doubled interval
         * but not more than max days interval
         * and if the table has rows.
         */
        if ( $total_count < $number && $interval <= $max_interval && $has_rows ) {
            $additional_ids = $this->get_ids_recursive( $interval, $number, $total_count, $popular_ids );
        }

        $popular_ids = array_merge( $popular_ids, $additional_ids );

        return array_values( array_unique( $popular_ids ) );
    }

    /**
     * Get popular posts ids.
     *
     * @since   1.0.0
     * @access  public
     * @param   int     $interval   Interval in days
     * @param   int     $number     Number of posts
     * @return  array   $ids        IDs
     */
    public function get_ids( $interval, $number ) {
        $number = absint( $number );
        $interval = absint( $interval );
        $intervals = ap_popular_posts()->get_intervals();

        if ( ! $number ) {
            $number = 4;
        }

        if ( $number > 12 ) {
            $number = 12;
        }

        if ( ! $interval ) {
            $interval = 3;
        }

        if ( ! in_array( $interval, array_keys( $intervals ), true ) ) {
            $interval = 3;
        }

        if ( false === ( $ids = $this->get_cached_post_ids( $interval, $number, 'ids' ) ) ) {
            $ids = $this->get_ids_recursive( $interval, $number );
            $this->cache_post_ids( $ids, $interval, $number, 'ids' );
        }

        return $ids;
    }

    /**
     * Get transient ids expiration.
     *
     * @since   1.2.0
     * @access  public
     * @param   int     $interval   Interval in days
     * @return  int     $interval   Expiration as timestamp
     */
    public function get_ids_expiration( $interval ) {
        /**
         * Transient expiration time correlated with interval.
         *
         * Bigger interval sets bigger transient expiration time because
         * if the interval is last 7 days it isn't really necessary
         * to change results every 25 minutes. It won't make any significant
         * difference to the list but it will make more SQL queries through the day.
         *
         * Last 24 hours - 25 min
         * Last 3 days   - 75 min - 1h:15m
         * Last 7 days   - 175 min - 2h:55m
        */
        $expiration = $interval * 25 * MINUTE_IN_SECONDS;

        /**
         * Filters the expiration time for ids_{$interval}_{$number} transient.
         *
         * @since   1.0.0
         * @param   int $interval Selected interval
         */
        $expiration = apply_filters( 'ap_popular_posts_transient_ids_expiration', $expiration, $interval );

        return $expiration;
    }

    /**
     * Checks if we should use default WordPress query to get popular posts.
     *
     * @since   1.2.0
     * @access  public
     * @return  bool
     */
    public function using_wp_query() {
        $use_wp_query = get_option( 'ap_popular_posts_use_wp_query' );
        $is_multilingual_plugin = ap_popular_posts()->is_multilingual_plugin_active();

        return ( $use_wp_query === '1' || $is_multilingual_plugin );
    }

}

