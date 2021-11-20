<?php
/**
 * Template for displaying widget content.
 *
 * @package     AP_Popular_Posts/Templates
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$query = $args['query'];

if ( $query->have_posts() ) :
    ?>
    <ul>
    <?php
    while ( $query->have_posts() ) :
        $query->the_post();
        ?>
        <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
        <?php
    endwhile;
    ?>
    </ul>
    <?php
else :
    ?>
    <p><?php esc_html_e( 'Currently there are no popular posts.', 'ap-popular-posts' ); ?></p>
    <?php
endif;
