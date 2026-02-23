<?php
/**
 * Template: Event Gallery
 *
 * Renders a responsive, protected gallery for the given event post.
 * Each image is served through Image_Protector so the real file path is
 * never exposed to the browser.
 *
 * Variables available (set by the shortcode callback):
 *  @var int    $post_id  Event post ID.
 *  @var array  $images   Gallery meta array (each element has 'watermarked_url', etc.).
 */

if ( empty( $images ) ) {
    return;
}
?>

<div class="eim-gallery" data-post-id="<?php echo esc_attr( $post_id ); ?>">
    <?php foreach ( $images as $index => $img ) :
        $protected_url = Image_Protector::get_protected_url( $post_id, $index );
        $alt           = esc_attr( get_the_title( $post_id ) . ' – ' . ( $index + 1 ) );
    ?>
        <div class="eim-gallery-item">
            <img
                src="<?php echo esc_url( $protected_url ); ?>"
                data-full="<?php echo esc_url( $protected_url ); ?>"
                alt="<?php echo $alt; ?>"
                loading="lazy"
                draggable="false"
            >
        </div>
    <?php endforeach; ?>
</div>

<!-- Lightbox -->
<div id="eim-lightbox-overlay" class="eim-lightbox-overlay" role="dialog" aria-modal="true">
    <div class="eim-lightbox-inner">
        <button class="eim-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>
        <button class="eim-lightbox-nav eim-lightbox-prev" aria-label="<?php esc_attr_e( 'Previous', 'event-image-manager' ); ?>">&#8249;</button>
        <button class="eim-lightbox-nav eim-lightbox-next" aria-label="<?php esc_attr_e( 'Next', 'event-image-manager' ); ?>">&#8250;</button>
        <img class="eim-lightbox-img" src="" alt="" draggable="false">
    </div>
    <p class="eim-lightbox-caption"></p>
</div>
