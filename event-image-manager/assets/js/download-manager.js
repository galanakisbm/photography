/**
 * Event Image Manager – Download Manager
 *
 * Handles the download button, download modal, and ZIP download for
 * selectively chosen images.
 *
 * Expects a localised object `eimDownload` with:
 *  - ajaxUrl
 *  - nonce
 *  - postId
 *  - permission  ('none' | 'watermarked' | 'original' | 'selective')
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimDownload === 'undefined' || 'none' === eimDownload.permission ) {
        return;
    }

    var $modal         = $( '#eim-download-modal' );
    var $status        = $modal.find( '.eim-download-status' );
    var $postIdField   = $( '#eim-download-post-id' );
    var $imgIndexField = $( '#eim-download-img-index' );

    /** Show an error message in the modal status area. */
    function showError( msg ) {
        $status.css( 'color', '#c00' ).text( msg ).show();
    }

    // ── Open modal on download-button click ───────────────────────────────────
    $( document ).on( 'click', '.eim-download-btn', function ( e ) {
        e.stopPropagation();
        var $btn     = $( this );
        var postId   = $btn.data( 'post-id' );
        var imgIndex = $btn.data( 'img-index' );

        $postIdField.val( postId );
        $imgIndexField.val( imgIndex );
        $status.text( '' ).hide();
        $modal.show();
    } );

    // ── Close modal ───────────────────────────────────────────────────────────
    $( document ).on( 'click', '.eim-modal-close, .eim-modal-overlay', function () {
        $modal.hide();
    } );

    // ── Single image download ─────────────────────────────────────────────────
    $( document ).on( 'click', '.eim-do-download', function () {
        var postId   = $postIdField.val();
        var imgIndex = $imgIndexField.val();
        var type     = $( this ).data( 'type' ); // 'watermarked' | 'original'

        if ( ! postId ) { return; }

        var url = eimDownload.ajaxUrl + '?action=eim_download_image' +
            '&nonce='    + encodeURIComponent( eimDownload.nonce ) +
            '&post_id='  + encodeURIComponent( postId ) +
            '&img='      + encodeURIComponent( imgIndex ) +
            '&dl_type='  + encodeURIComponent( type );

        $status.text( eimDownload.i18n.preparing ).show();

        // Create a temporary anchor to trigger the download without navigating away.
        var a    = document.createElement( 'a' );
        a.href   = url;
        a.style.display = 'none';
        document.body.appendChild( a );
        a.click();
        document.body.removeChild( a );

        setTimeout( function () { $status.text( '' ).hide(); }, 2000 );
        $modal.hide();
    } );

    // ── Selective download (ZIP) ──────────────────────────────────────────────
    if ( 'selective' === eimDownload.permission ) {
        var $selectAll    = $( '#eim-select-all' );
        var $dlSelected   = $( '#eim-download-selected' );

        // Toggle select-all.
        $selectAll.on( 'click', function () {
            var $checkboxes = $( '.eim-img-select' );
            var allChecked  = $checkboxes.filter( ':not(:checked)' ).length === 0;
            $checkboxes.prop( 'checked', ! allChecked );
            updateSelectionState();
        } );

        // Update button state when checkboxes change.
        $( document ).on( 'change', '.eim-img-select', updateSelectionState );

        function updateSelectionState() {
            var selected = $( '.eim-img-select:checked' ).length;
            $dlSelected.prop( 'disabled', selected === 0 );
            $dlSelected.text(
                selected > 0
                    ? eimDownload.i18n.downloadSelected.replace( '%d', selected )
                    : eimDownload.i18n.downloadSelectedEmpty
            );
        }

        // Trigger ZIP download.
        $dlSelected.on( 'click', function () {
            var indices = $( '.eim-img-select:checked' )
                .map( function () { return $( this ).val(); } )
                .get()
                .join( ',' );

            if ( ! indices ) { return; }

            $dlSelected.prop( 'disabled', true ).text( eimDownload.i18n.preparing );

            $.post(
                eimDownload.ajaxUrl,
                {
                    action:   'eim_download_zip',
                    nonce:    eimDownload.nonce,
                    post_id:  eimDownload.postId,
                    indices:  indices,
                },
                function ( res ) {
                    $dlSelected.prop( 'disabled', false );
                    updateSelectionState();

                    if ( res.success && res.data.url ) {
                        window.location.href = res.data.url;
                    } else if ( ! res.success ) {
                        showError( res.data && res.data.message ? res.data.message : eimDownload.i18n.error );
                        $modal.show();
                    }
                }
            ).fail( function () {
                $dlSelected.prop( 'disabled', false );
                updateSelectionState();
                showError( eimDownload.i18n.error );
                $modal.show();
            } );
        } );
    }

}( jQuery ) );
