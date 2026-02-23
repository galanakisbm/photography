/**
 * Event Image Manager – Download Export
 *
 * Handles advanced export: full ZIP, batch queue, and progress polling.
 *
 * Expects localised object `eimExport` with:
 *   - ajaxUrl
 *   - nonce
 *   - postId
 *   - i18n  { selecting, preparing, queued, error, selected }
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimExport === 'undefined' ) {
        return;
    }

    var $modal        = $( '#eim-download-advanced-modal' );
    var $status       = $modal.find( '.eim-adv-status' );
    var $progressWrap = $( '#eim-adv-progress-wrap' );
    var $progressFill = $progressWrap.find( '.eim-progress-fill' );
    var $progressBar  = $progressWrap.find( '.eim-progress-bar' );
    var $progressLbl  = $progressWrap.find( '.eim-progress-label' );

    var pollTimer = null;

    // ── Select-all checkbox ───────────────────────────────────────────────────
    $( '#eim-adv-select-all' ).on( 'change', function () {
        $( '.eim-adv-img-check' ).prop( 'checked', this.checked );
        updateSelectedCount();
    } );

    $( document ).on( 'change', '.eim-adv-img-check', function () {
        var total   = $( '.eim-adv-img-check' ).length;
        var checked = $( '.eim-adv-img-check:checked' ).length;
        $( '#eim-adv-select-all' ).prop( 'indeterminate', checked > 0 && checked < total )
                                  .prop( 'checked', checked === total && total > 0 );
        updateSelectedCount();
    } );

    function updateSelectedCount() {
        var count = $( '.eim-adv-img-check:checked' ).length;
        var label = count > 0
            ? eimExport.i18n.selected.replace( '%d', count )
            : eimExport.i18n.selecting;
        $( '#eim-adv-selected-count' ).text( label );

        $( '#eim-adv-download-zip, #eim-adv-add-to-queue' ).prop( 'disabled', count === 0 );
    }

    // ── Download ZIP ──────────────────────────────────────────────────────────
    $( '#eim-adv-download-zip' ).on( 'click', function () {
        var indices  = getSelectedIndices();
        var noWmark  = $( '#eim-adv-no-watermark' ).is( ':checked' ) ? 1 : 0;

        if ( ! indices.length ) {
            return;
        }

        showStatus( eimExport.i18n.preparing );
        showProgress( 0 );

        $.post(
            eimExport.ajaxUrl,
            {
                action:      'eim_export_zip',
                nonce:       eimExport.nonce,
                post_id:     eimExport.postId,
                indices:     indices.join( ',' ),
                no_watermark: noWmark,
            },
            function ( res ) {
                hideProgress();
                if ( res.success && res.data && res.data.url ) {
                    showStatus( '' );
                    window.location.href = res.data.url;
                } else {
                    showStatus( res.data && res.data.message ? res.data.message : eimExport.i18n.error, true );
                }
            }
        ).fail( function () {
            hideProgress();
            showStatus( eimExport.i18n.error, true );
        } );
    } );

    // ── Add to queue ──────────────────────────────────────────────────────────
    $( '#eim-adv-add-to-queue' ).on( 'click', function () {
        var indices = getSelectedIndices();
        if ( ! indices.length ) {
            return;
        }

        $.post(
            eimExport.ajaxUrl,
            {
                action:  'eim_add_to_queue',
                nonce:   eimExport.nonce,
                post_id: eimExport.postId,
                indices: indices.join( ',' ),
            },
            function ( res ) {
                if ( res.success ) {
                    showStatus( eimExport.i18n.queued );
                    startQueuePolling( res.data && res.data.queue_id ? res.data.queue_id : null );
                } else {
                    showStatus( res.data && res.data.message ? res.data.message : eimExport.i18n.error, true );
                }
            }
        ).fail( function () {
            showStatus( eimExport.i18n.error, true );
        } );
    } );

    // ── Queue status polling ──────────────────────────────────────────────────
    function startQueuePolling( queueId ) {
        if ( ! queueId ) {
            return;
        }
        showProgress( 0 );
        pollTimer = setInterval( function () {
            $.post(
                eimExport.ajaxUrl,
                {
                    action:   'eim_get_queue_status',
                    nonce:    eimExport.nonce,
                    queue_id: queueId,
                },
                function ( res ) {
                    if ( res.success && res.data ) {
                        var pct = parseInt( res.data.progress, 10 ) || 0;
                        updateProgress( pct );
                        if ( pct >= 100 || 'complete' === res.data.status ) {
                            clearInterval( pollTimer );
                            hideProgress();
                            if ( res.data.url ) {
                                window.location.href = res.data.url;
                            }
                        }
                    } else {
                        clearInterval( pollTimer );
                        hideProgress();
                    }
                }
            ).fail( function () {
                clearInterval( pollTimer );
                hideProgress();
            } );
        }, 2000 );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function getSelectedIndices() {
        return $( '.eim-adv-img-check:checked' )
            .map( function () { return $( this ).val(); } )
            .get();
    }

    function showStatus( msg, isError ) {
        $status.css( 'color', isError ? '#c00' : '' ).text( msg );
    }

    function showProgress( pct ) {
        $progressWrap.show();
        updateProgress( pct );
    }

    function updateProgress( pct ) {
        pct = Math.min( 100, Math.max( 0, pct ) );
        $progressFill.css( 'width', pct + '%' );
        $progressBar.attr( 'aria-valuenow', pct );
        $progressLbl.text( pct + '%' );
    }

    function hideProgress() {
        $progressWrap.hide();
        updateProgress( 0 );
    }

    // Initialise button state.
    updateSelectedCount();

}( jQuery ) );
