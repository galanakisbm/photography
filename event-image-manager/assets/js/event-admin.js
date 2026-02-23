/**
 * Event Image Manager – Admin JS
 *
 * Powers the media-uploader integration in the event gallery metabox.
 */
( function ( $ ) {
    'use strict';

    var mediaUploader;

    $( '#eim-add-images' ).on( 'click', function ( e ) {
        e.preventDefault();

        if ( mediaUploader ) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media( {
            title:    eimAdmin.selectImagesTitle,
            button:   { text: eimAdmin.addImagesButton },
            multiple: true,
            library:  { type: 'image' },
        } );

        mediaUploader.on( 'select', function () {
            var selection = mediaUploader.state().get( 'selection' );
            var newIds    = [];

            selection.each( function ( attachment ) {
                var data     = attachment.toJSON();
                var thumbUrl = ( data.sizes && data.sizes.thumbnail )
                    ? data.sizes.thumbnail.url
                    : data.url;

                // Preview in the metabox.
                var $item = $( '<div class="eim-image-item">' );
                $item.append( $( '<img>' ).attr( { src: thumbUrl, style: 'max-width:120px;max-height:90px;' } ) );
                $item.append(
                    $( '<button type="button" class="button eim-remove-new-image">' )
                        .text( eimAdmin.removeLabel )
                        .data( 'id', data.id )
                );
                $( '#eim-image-list' ).append( $item );

                newIds.push( data.id );
            } );

            // Append to the hidden field.
            var existing = $( '#eim-new-attachment-ids' ).val();
            var merged   = existing ? existing.split( ',' ).concat( newIds ) : newIds;
            $( '#eim-new-attachment-ids' ).val( merged.join( ',' ) );
        } );

        mediaUploader.open();
    } );

    // Remove a newly added (not yet saved) image from the preview.
    $( document ).on( 'click', '.eim-remove-new-image', function () {
        var removeId = String( $( this ).data( 'id' ) );
        $( this ).closest( '.eim-image-item' ).remove();

        var ids     = $( '#eim-new-attachment-ids' ).val().split( ',' ).filter( Boolean );
        var updated = ids.filter( function ( id ) { return id !== removeId; } );
        $( '#eim-new-attachment-ids' ).val( updated.join( ',' ) );
    } );

    // Remove a previously saved image via AJAX.
    $( document ).on( 'click', '.eim-remove-image', function () {
        var $btn             = $( this );
        var watermarkedPath  = $btn.data( 'path' );
        var originalPath     = $btn.data( 'original' );

        $.post(
            eimAdmin.ajaxUrl,
            {
                action:           'eim_delete_image',
                nonce:            eimAdmin.deleteNonce,
                watermarked_path: watermarkedPath,
                original_path:    originalPath,
            },
            function ( response ) {
                if ( response.success ) {
                    $btn.closest( '.eim-image-item' ).remove();
                }
            }
        );
    } );

}( jQuery ) );
