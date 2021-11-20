(function( $ ) {
    'use strict';

    // ap_popular_posts_vars is required to continue, ensure the object exists
    if ( typeof ap_popular_posts_vars === 'undefined' ) {
        return false;
    }

    function get_fragments() {

        var fragments = [],
            instance = 1;

        $.each( ap_popular_posts_vars.fragments, function( key, fragment ) {
            var $fragment = $( fragment );

            $fragment.each( function( index, element ) {

                var $element = $( element ),
                    args = $element.data( 'options' ),
                    args = args ? args : 'false';

                $element.attr( 'data-ap-popular-posts-fragment', instance );

                fragments.push({
                    key: fragment,
                    instance: instance,
                    args: args,
                });

                instance++;
            });
        });

        return fragments;
    }

    function refresh_fragments() {

        var fragments = get_fragments();

        if ( ! fragments ) {
            return false;
        }

        $.ajax({
            url: ap_popular_posts_vars.ajax_url.toString().replace( '%%action%%', 'refresh-fragments' ),
            type: 'POST',
            data: { fragments: fragments },
            success: function( response ) {

                if ( response.success ) {

                    $.each( response.data, function( instance, fragment ) {
                        $( '[data-ap-popular-posts-fragment="'+ instance +'"]' ).empty().append( fragment );
                    });

                    $( document ).trigger( 'ap_popular_posts_fragments_refreshed' );
                }
            }
        });
    }

    function update_views() {
        var url = ap_popular_posts_vars.ajax_url.toString().replace( '%%action%%', 'update-views' );
        $.post( url, { post_id: ap_popular_posts_vars.post_id } );
    }

    if ( 'true' === ap_popular_posts_vars.ajax_refresh_fragments ) {
        refresh_fragments();
    }

    if ( 'true' === ap_popular_posts_vars.ajax_update_views && 'true' === ap_popular_posts_vars.is_single ) {
        update_views();
    }

    // $( document ).on( 'ap_popular_posts_fragments_refreshed', function() {
    //     console.log( 'event triggered' );
    // } );

})( jQuery );
