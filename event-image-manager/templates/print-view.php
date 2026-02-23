<?php
/**
 * Template: Print-Friendly Gallery View
 *
 * Clean HTML for printing the event gallery.
 * PHP variables available: $post_id, $images (array of image data).
 */

$post_id  = isset( $post_id ) ? absint( $post_id ) : get_the_ID();
$images   = isset( $images ) && is_array( $images ) ? $images : array();
$event    = get_post( $post_id );
$title    = $event ? get_the_title( $event ) : '';
$date_raw = $event ? get_the_date( 'F j, Y', $event ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $title ); ?> &mdash; <?php esc_html_e( 'Print Gallery', 'event-image-manager' ); ?></title>
    <style>
        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Georgia, serif; color: #111; background: #fff; padding: 20px; }

        /* ── Header ── */
        .eim-print-header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #111; padding-bottom: 12px; }
        .eim-print-header h1 { font-size: 24px; margin-bottom: 4px; }
        .eim-print-header .eim-print-date { font-size: 14px; color: #555; }

        /* ── Grid ── */
        .eim-print-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .eim-print-grid-item img { width: 100%; height: auto; display: block; }

        /* ── Footer ── */
        .eim-print-footer { margin-top: 24px; text-align: center; font-size: 12px; color: #888; }

        /* ── Print styles ── */
        @media print {
            body { padding: 0; }
            .eim-print-no-print { display: none !important; }
            .eim-print-grid { grid-template-columns: repeat(4, 1fr); gap: 4px; }
            .eim-print-grid-item { page-break-inside: avoid; }
            a { text-decoration: none; color: inherit; }
        }

        @media screen and (max-width: 600px) {
            .eim-print-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="eim-print-header">
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php if ( $date_raw ) : ?>
        <p class="eim-print-date"><?php echo esc_html( $date_raw ); ?></p>
        <?php endif; ?>
    </div>

    <button class="eim-print-no-print" onclick="window.print();" style="margin-bottom:16px;padding:8px 16px;cursor:pointer;">
        <?php esc_html_e( 'Print', 'event-image-manager' ); ?>
    </button>

    <?php if ( ! empty( $images ) ) : ?>
    <div class="eim-print-grid">
        <?php foreach ( $images as $idx => $img ) :
            $src = is_array( $img ) ? ( $img['url'] ?? '' ) : $img;
            $alt = is_array( $img ) ? ( $img['alt'] ?? sprintf( __( 'Image %d', 'event-image-manager' ), $idx + 1 ) ) : sprintf( __( 'Image %d', 'event-image-manager' ), $idx + 1 );
        ?>
        <div class="eim-print-grid-item">
            <img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy">
        </div>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p><?php esc_html_e( 'No images found for this event.', 'event-image-manager' ); ?></p>
    <?php endif; ?>

    <div class="eim-print-footer">
        <p><?php echo esc_html( get_bloginfo( 'name' ) ); ?> &mdash; <?php echo esc_html( $title ); ?></p>
    </div>
</body>
</html>
