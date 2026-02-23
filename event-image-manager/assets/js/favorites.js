/**
 * Event Image Manager – Favorites
 *
 * Handles the favorite / like toggle on gallery images.
 *
 * Expects a localised object `eimFavorites` with:
 *  - ajaxUrl
 *  - nonce
 *  - postId
 *  - favorites  (array of img indices already favorited by the current user)
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimFavorites === 'undefined' ) {
        return;
    }

    // Mark images already favorited by the current user.
    function initFavorites() {
        if ( eimFavorites.favorites && eimFavorites.favorites.length ) {
            $.each( eimFavorites.favorites, function ( i, idx ) {
                markFavorited( idx, true );
            } );
        }
    }

    /** Update the UI for a single image's favorite state. */
    function markFavorited( imgIndex, favorited ) {
        var $btn = $( '.eim-favorite-btn[data-img-index="' + imgIndex + '"]' );
        $btn.toggleClass( 'is-favorited', favorited );
        $btn.find( '.eim-favorite-icon' ).text( favorited ? '♥' : '♡' );
    }

    /** Update the displayed count for a single image. */
    function updateCount( imgIndex, count ) {
        $( '.eim-favorite-btn[data-img-index="' + imgIndex + '"] .eim-favorite-count' ).text( count );
    }

    // ── Toggle favorite on button click ───────────────────────────────────────
    $( document ).on( 'click', '.eim-favorite-btn', function ( e ) {
        e.stopPropagation();

        var $btn     = $( this );
        var postId   = $btn.data( 'post-id' );
        var imgIndex = $btn.data( 'img-index' );

        // Optimistic UI update.
        var wasFavorited = $btn.hasClass( 'is-favorited' );
        markFavorited( imgIndex, ! wasFavorited );

        $.post(
            eimFavorites.ajaxUrl,
            {
                action:    'eim_toggle_favorite',
                nonce:     eimFavorites.nonce,
                post_id:   postId,
                img_index: imgIndex,
            },
            function ( res ) {
                if ( res.success ) {
                    markFavorited( imgIndex, res.data.favorited );
                    updateCount( imgIndex, res.data.count );
                } else {
                    // Revert optimistic update on failure.
                    markFavorited( imgIndex, wasFavorited );
                }
            }
        ).fail( function () {
            markFavorited( imgIndex, wasFavorited );
        } );
    } );

    // ── Load initial favorite counts ──────────────────────────────────────────
    function loadCounts() {
        $( '.eim-favorite-btn' ).each( function () {
            var $btn     = $( this );
            var postId   = $btn.data( 'post-id' );
            var imgIndex = $btn.data( 'img-index' );

            // Counts are loaded lazily per-image via IntersectionObserver when available.
            if ( 'IntersectionObserver' in window ) {
                var observer = new IntersectionObserver( function ( entries ) {
                    entries.forEach( function ( entry ) {
                        if ( entry.isIntersecting ) {
                            fetchCount( postId, imgIndex );
                            observer.disconnect();
                        }
                    } );
                }, { rootMargin: '200px' } );
                observer.observe( $btn[0] );
            } else {
                fetchCount( postId, imgIndex );
            }
        } );
    }

    function fetchCount( postId, imgIndex ) {
        // Count fetching reuses the get_favorites response (favorites array length).
        // For simplicity, the count is updated server-side on toggle; here we just
        // read the data attribute if present.
        var count = $( '.eim-favorite-btn[data-img-index="' + imgIndex + '"]' ).data( 'count' ) || 0;
        updateCount( imgIndex, count );
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    $( function () {
        initFavorites();
        loadCounts();
    } );

}( jQuery ) );
