<?php
/**
 * Template: Password Protection Form
 *
 * Displayed when a gallery is password-protected and the current
 * session / user has not yet been authorised.
 *
 * Variables available:
 *  @var int $post_id Event post ID.
 */
?>
<div class="eim-password-form-wrap">
    <h2><?php esc_html_e( 'Protected Gallery', 'event-image-manager' ); ?></h2>
    <p><?php esc_html_e( 'This gallery is password-protected. Please enter the password to view the photos.', 'event-image-manager' ); ?></p>

    <form id="eim-password-form" class="eim-password-form" novalidate>
        <?php wp_nonce_field( 'eim_verify_password_nonce', 'eim_verify_password_nonce' ); ?>
        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">

        <label for="eim-gallery-password"><?php esc_html_e( 'Password:', 'event-image-manager' ); ?></label>
        <input
            type="password"
            id="eim-gallery-password"
            name="password"
            required
            autocomplete="current-password"
        >

        <button type="submit" class="button button-primary">
            <?php esc_html_e( 'Enter Gallery', 'event-image-manager' ); ?>
        </button>

        <p class="eim-password-error" role="alert" aria-live="polite" style="display:none;color:#c00;"></p>
    </form>
</div>

<script>
( function () {
    var form  = document.getElementById( 'eim-password-form' );
    var error = form ? form.querySelector( '.eim-password-error' ) : null;
    if ( ! form ) { return; }

    form.addEventListener( 'submit', function ( e ) {
        e.preventDefault();

        var data = new FormData( form );
        data.append( 'action', 'eim_verify_gallery_password' );
        data.append( 'nonce',  form.querySelector( '[name="eim_verify_password_nonce"]' ).value );

        fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
            method: 'POST',
            body:   data,
        } )
        .then( function ( r ) { return r.json(); } )
        .then( function ( res ) {
            if ( res.success ) {
                window.location.reload();
            } else {
                if ( error ) {
                    error.textContent = res.data && res.data.message
                        ? res.data.message
                        : <?php echo wp_json_encode( __( 'Incorrect password.', 'event-image-manager' ) ); ?>;
                    error.style.display = 'block';
                }
            }
        } )
        .catch( function () {
            if ( error ) {
                error.textContent = <?php echo wp_json_encode( __( 'An error occurred. Please try again.', 'event-image-manager' ) ); ?>;
                error.style.display = 'block';
            }
        } );
    } );
}() );
</script>
