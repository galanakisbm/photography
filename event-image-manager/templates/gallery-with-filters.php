<?php
/**
 * Template: Gallery with Search & Filters
 *
 * Renders a gallery for the given event with a search bar, filter controls,
 * sort selector, lightbox, download modal, favorites and comments support.
 *
 * Variables available (set by the shortcode callback or direct include):
 *  @var int    $post_id  Event post ID.
 *  @var array  $images   Gallery meta array.
 */

if ( empty( $images ) ) {
    return;
}

$download_manager = new EIM_Download_Manager();
$permission       = $download_manager->get_effective_permission( $post_id );
$show_download    = ( 'none' !== $permission );
?>

<div class="eim-gallery-wrap" data-post-id="<?php echo esc_attr( $post_id ); ?>">

    <!-- ── Search & Filter bar ─────────────────────────────────────────── -->
    <div class="eim-filter-bar">
        <input type="search" id="eim-search-input" placeholder="<?php esc_attr_e( 'Search images…', 'event-image-manager' ); ?>">

        <div class="eim-filter-controls">
            <label>
                <?php esc_html_e( 'From:', 'event-image-manager' ); ?>
                <input type="date" id="eim-filter-date-from">
            </label>
            <label>
                <?php esc_html_e( 'To:', 'event-image-manager' ); ?>
                <input type="date" id="eim-filter-date-to">
            </label>

            <select id="eim-filter-category">
                <option value=""><?php esc_html_e( 'All categories', 'event-image-manager' ); ?></option>
            </select>

            <select id="eim-sort-order">
                <option value="date_desc"><?php esc_html_e( 'Newest first', 'event-image-manager' ); ?></option>
                <option value="date_asc"><?php esc_html_e( 'Oldest first', 'event-image-manager' ); ?></option>
                <option value="name_asc"><?php esc_html_e( 'Name A–Z', 'event-image-manager' ); ?></option>
                <option value="name_desc"><?php esc_html_e( 'Name Z–A', 'event-image-manager' ); ?></option>
                <option value="popularity"><?php esc_html_e( 'Most popular', 'event-image-manager' ); ?></option>
            </select>

            <button type="button" id="eim-apply-filters" class="button">
                <?php esc_html_e( 'Apply Filters', 'event-image-manager' ); ?>
            </button>
            <button type="button" id="eim-reset-filters" class="button">
                <?php esc_html_e( 'Reset', 'event-image-manager' ); ?>
            </button>
        </div>

        <?php if ( 'selective' === $permission ) : ?>
            <div class="eim-batch-bar">
                <button type="button" id="eim-select-all" class="button">
                    <?php esc_html_e( 'Select All', 'event-image-manager' ); ?>
                </button>
                <button type="button" id="eim-download-selected" class="button button-primary" disabled>
                    <?php esc_html_e( 'Download Selected', 'event-image-manager' ); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Gallery grid ────────────────────────────────────────────────── -->
    <div class="eim-gallery" id="eim-gallery-grid">
        <?php foreach ( $images as $index => $img ) :
            $protected_url = Image_Protector::get_protected_url( $post_id, $index );
            $meta          = EIM_Categories::get_metadata( $post_id, $index );
            $alt           = esc_attr( $meta['title'] ?: ( get_the_title( $post_id ) . ' – ' . ( $index + 1 ) ) );
            $tags_json     = esc_attr( wp_json_encode( $meta['tags'] ) );
            $cats_json     = esc_attr( wp_json_encode( $meta['categories'] ) );
        ?>
            <div class="eim-gallery-item"
                 data-index="<?php echo esc_attr( $index ); ?>"
                 data-tags="<?php echo $tags_json; ?>"
                 data-categories="<?php echo $cats_json; ?>"
            >
                <?php if ( 'selective' === $permission ) : ?>
                    <label class="eim-select-checkbox">
                        <input type="checkbox" class="eim-img-select" value="<?php echo esc_attr( $index ); ?>">
                    </label>
                <?php endif; ?>

                <img
                    src="<?php echo esc_url( $protected_url ); ?>"
                    data-full="<?php echo esc_url( $protected_url ); ?>"
                    alt="<?php echo $alt; ?>"
                    loading="lazy"
                    draggable="false"
                >

                <div class="eim-item-overlay">
                    <!-- Favorite button -->
                    <button type="button" class="eim-favorite-btn"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>"
                            data-img-index="<?php echo esc_attr( $index ); ?>"
                            title="<?php esc_attr_e( 'Favorite', 'event-image-manager' ); ?>">
                        <span class="eim-favorite-icon">♡</span>
                        <span class="eim-favorite-count">0</span>
                    </button>

                    <!-- Download button -->
                    <?php if ( $show_download ) : ?>
                        <button type="button" class="eim-download-btn"
                                data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                data-img-index="<?php echo esc_attr( $index ); ?>"
                                title="<?php esc_attr_e( 'Download', 'event-image-manager' ); ?>">
                            ⬇
                        </button>
                    <?php endif; ?>

                    <!-- Comment button -->
                    <button type="button" class="eim-comment-btn"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>"
                            data-img-index="<?php echo esc_attr( $index ); ?>"
                            title="<?php esc_attr_e( 'Comments', 'event-image-manager' ); ?>">
                        💬
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── No-results message ──────────────────────────────────────────── -->
    <p class="eim-no-results" style="display:none;">
        <?php esc_html_e( 'No images match your search.', 'event-image-manager' ); ?>
    </p>
</div>

<!-- ── Lightbox ────────────────────────────────────────────────────────── -->
<div id="eim-lightbox-overlay" class="eim-lightbox-overlay" role="dialog" aria-modal="true">
    <div class="eim-lightbox-inner">
        <button class="eim-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'event-image-manager' ); ?>">&times;</button>
        <button class="eim-lightbox-nav eim-lightbox-prev" aria-label="<?php esc_attr_e( 'Previous', 'event-image-manager' ); ?>">&#8249;</button>
        <button class="eim-lightbox-nav eim-lightbox-next" aria-label="<?php esc_attr_e( 'Next', 'event-image-manager' ); ?>">&#8250;</button>
        <img class="eim-lightbox-img" src="" alt="" draggable="false">
    </div>
    <p class="eim-lightbox-caption"></p>
</div>

<!-- ── Download modal ──────────────────────────────────────────────────── -->
<?php if ( $show_download ) : ?>
    <?php include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/download-modal.php'; ?>
<?php endif; ?>

<!-- ── Comments modal ──────────────────────────────────────────────────── -->
<?php include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/comments-section.php'; ?>
