/**
 * Event Image Manager – Comments
 *
 * Handles opening the comments modal, loading existing comments via AJAX,
 * and submitting new comments.
 *
 * Expects a localised object `eimComments` with:
 *  - ajaxUrl
 *  - nonce         (eim_comments_nonce)
 *  - postId
 *  - loggedIn      (bool)
 *  - i18n.loading
 *  - i18n.noComments
 *  - i18n.error
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimComments === 'undefined' ) {
        return;
    }

    var $modal     = $( '#eim-comments-modal' );
    var $list      = $( '#eim-comments-list' );
    var $form      = $( '#eim-comment-form' );
    var $postId    = $( '#eim-comment-post-id' );
    var $imgIndex  = $( '#eim-comment-img-index' );
    var $statusMsg = $form.find( '.eim-comment-status' );

    var currentImgIndex = 0;

    // ── Open modal when the comment button is clicked ─────────────────────────
    $( document ).on( 'click', '.eim-comment-btn', function ( e ) {
        e.stopPropagation();

        var $btn   = $( this );
        var postId = $btn.data( 'post-id' );
        currentImgIndex = parseInt( $btn.data( 'img-index' ), 10 );

        $postId.val( postId );
        $imgIndex.val( currentImgIndex );
        $statusMsg.text( '' ).hide();
        $form.find( '#eim-comment-content' ).val( '' );

        loadComments( postId, currentImgIndex );
        $modal.show();
    } );

    // ── Close modal ───────────────────────────────────────────────────────────
    $( document ).on( 'click', '.eim-modal-close, .eim-modal-overlay', function () {
        $modal.hide();
    } );

    // ── Load comments ─────────────────────────────────────────────────────────
    function loadComments( postId, imgIndex ) {
        $list.html( '<p class="eim-comments-loading">' + eimComments.i18n.loading + '</p>' );

        $.get(
            eimComments.ajaxUrl,
            {
                action:    'eim_get_comments',
                nonce:     eimComments.nonce,
                post_id:   postId,
                img_index: imgIndex,
            },
            function ( res ) {
                $list.empty();

                if ( ! res.success || ! res.data.length ) {
                    $list.html( '<p>' + eimComments.i18n.noComments + '</p>' );
                    return;
                }

                $.each( res.data, function ( i, comment ) {
                    var $c = $( '<div class="eim-comment">' );
                    $c.append(
                        $( '<strong class="eim-comment-author">' ).text( comment.author )
                    );
                    $c.append(
                        $( '<span class="eim-comment-date">' ).text( ' · ' + comment.created_at )
                    );
                    $c.append(
                        $( '<p class="eim-comment-text">' ).text( comment.content )
                    );
                    $list.append( $c );
                } );
            }
        ).fail( function () {
            $list.html( '<p>' + eimComments.i18n.error + '</p>' );
        } );
    }

    // ── Post a comment ────────────────────────────────────────────────────────
    $form.on( 'submit', function ( e ) {
        e.preventDefault();

        var data = {
            action:    'eim_post_comment',
            nonce:     $form.find( '[name="eim_comments_nonce"]' ).val(),
            post_id:   $postId.val(),
            img_index: $imgIndex.val(),
            content:   $form.find( '#eim-comment-content' ).val(),
        };

        var $authorField = $form.find( '#eim-comment-author' );
        if ( $authorField.length ) {
            data.author = $authorField.val();
        }

        $statusMsg.text( '' ).hide();

        $.post( eimComments.ajaxUrl, data, function ( res ) {
            if ( res.success ) {
                $statusMsg.css( 'color', 'green' ).text( res.data.message ).show();
                $form.find( '#eim-comment-content' ).val( '' );
                loadComments( data.post_id, data.img_index );
            } else {
                $statusMsg.css( 'color', '#c00' )
                    .text( res.data && res.data.message ? res.data.message : eimComments.i18n.error )
                    .show();
            }
        } ).fail( function () {
            $statusMsg.css( 'color', '#c00' ).text( eimComments.i18n.error ).show();
        } );
    } );

}( jQuery ) );
