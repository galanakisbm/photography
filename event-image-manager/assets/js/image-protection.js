/**
 * Event Image Manager – Image Protection & Lightbox
 *
 * Disables right-click context menu and drag-drop on protected gallery images,
 * and powers the lightbox viewer.
 */
( function ( $ ) {
    'use strict';

    // -------------------------------------------------------------------------
    // Image protection
    // -------------------------------------------------------------------------

    /**
     * Prevent the browser context menu on gallery images.
     * Attach to the document so dynamically inserted images are also covered.
     */
    $( document ).on( 'contextmenu', '.eim-gallery img, .eim-lightbox-inner img', function ( e ) {
        e.preventDefault();
        return false;
    } );

    /**
     * Prevent drag-start on gallery images.
     */
    $( document ).on( 'dragstart', '.eim-gallery img, .eim-lightbox-inner img', function ( e ) {
        e.preventDefault();
        return false;
    } );

    /**
     * Prevent keyboard shortcuts that could trigger a "save image" action.
     * (Ctrl/Cmd + S, Ctrl/Cmd + Shift + S)
     */
    $( document ).on( 'keydown', function ( e ) {
        if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
            // Only suppress when a gallery image is in focus / visible.
            if ( $( '.eim-gallery' ).length ) {
                e.preventDefault();
            }
        }
    } );

    // -------------------------------------------------------------------------
    // Lightbox
    // -------------------------------------------------------------------------

    var $overlay   = $( '#eim-lightbox-overlay' );
    var $imgEl     = $overlay.find( '.eim-lightbox-img' );
    var $caption   = $overlay.find( '.eim-lightbox-caption' );
    var currentIdx = 0;
    var items      = [];   // Array of { src, caption } collected from the gallery.

    /** Open the lightbox at the given index. */
    function openLightbox( idx ) {
        if ( ! items.length ) {
            return;
        }
        currentIdx = ( idx + items.length ) % items.length;
        $imgEl.attr( 'src', items[ currentIdx ].src );
        $caption.text( items[ currentIdx ].caption );
        $overlay.addClass( 'is-open' );
        $( 'body' ).css( 'overflow', 'hidden' );
    }

    /** Close the lightbox. */
    function closeLightbox() {
        $overlay.removeClass( 'is-open' );
        $imgEl.attr( 'src', '' );
        $( 'body' ).css( 'overflow', '' );
    }

    /** Refresh the items array from the current DOM. */
    function collectItems() {
        items = [];
        $( '.eim-gallery-item' ).each( function () {
            var $img = $( this ).find( 'img' );
            items.push( {
                src:     $img.data( 'full' ) || $img.attr( 'src' ),
                caption: $img.attr( 'alt' ) || '',
            } );
        } );
    }

    // Collect items on DOM ready and whenever the gallery changes.
    $( function () {
        collectItems();
    } );

    // Open on thumbnail click.
    $( document ).on( 'click', '.eim-gallery-item', function () {
        collectItems();
        var idx = $( '.eim-gallery-item' ).index( this );
        openLightbox( idx );
    } );

    // Close button.
    $( document ).on( 'click', '.eim-lightbox-close', closeLightbox );

    // Click outside the image to close.
    $( document ).on( 'click', '#eim-lightbox-overlay', function ( e ) {
        if ( $( e.target ).is( '#eim-lightbox-overlay' ) ) {
            closeLightbox();
        }
    } );

    // Previous / next navigation.
    $( document ).on( 'click', '.eim-lightbox-prev', function () {
        openLightbox( currentIdx - 1 );
    } );

    $( document ).on( 'click', '.eim-lightbox-next', function () {
        openLightbox( currentIdx + 1 );
    } );

    // Keyboard navigation.
    $( document ).on( 'keydown', function ( e ) {
        if ( ! $overlay.hasClass( 'is-open' ) ) {
            return;
        }
        if ( e.key === 'ArrowLeft' )  { openLightbox( currentIdx - 1 ); }
        if ( e.key === 'ArrowRight' ) { openLightbox( currentIdx + 1 ); }
        if ( e.key === 'Escape' )     { closeLightbox(); }
    } );

}( jQuery ) );
