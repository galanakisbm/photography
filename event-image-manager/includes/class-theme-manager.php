<?php
/**
 * Class EIM_Theme_Manager
 *
 * Theme customization for the gallery frontend.
 *
 * Options (stored with prefix `eim_theme_`):
 *   - `eim_theme_primary_color`   : primary brand colour (hex).
 *   - `eim_theme_secondary_color` : secondary colour (hex).
 *   - `eim_theme_font_family`     : CSS font-family string.
 *   - `eim_theme_layout`          : gallery layout (grid|masonry|carousel).
 */
class EIM_Theme_Manager {

    /** Default theme options. */
    const DEFAULTS = array(
        'primary_color'   => '#0073aa',
        'secondary_color' => '#23282d',
        'font_family'     => 'sans-serif',
        'layout'          => 'grid',
    );

    public function __construct() {
        add_action( 'admin_menu',        array( $this, 'add_admin_menu' ) );
        add_action( 'admin_post_eim_save_theme', array( $this, 'save_theme_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_css' ) );
    }

    // -------------------------------------------------------------------------
    // Admin menu
    // -------------------------------------------------------------------------

    /** Register the Theme Builder submenu under Events. */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            __( 'Theme Builder', 'event-image-manager' ),
            __( 'Theme Builder', 'event-image-manager' ),
            'manage_options',
            'eim-theme-builder',
            array( $this, 'render_theme_builder' )
        );
    }

    // -------------------------------------------------------------------------
    // Admin UI
    // -------------------------------------------------------------------------

    /** Render the theme builder admin page. */
    public function render_theme_builder() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'event-image-manager' ) );
        }

        $opts = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'EIM Theme Builder', 'event-image-manager' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'eim_save_theme', 'eim_theme_nonce' ); ?>
                <input type="hidden" name="action" value="eim_save_theme">
                <table class="form-table">
                    <tr>
                        <th><label for="eim_primary_color"><?php esc_html_e( 'Primary Color', 'event-image-manager' ); ?></label></th>
                        <td><input type="color" id="eim_primary_color" name="eim_primary_color" value="<?php echo esc_attr( $opts['primary_color'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="eim_secondary_color"><?php esc_html_e( 'Secondary Color', 'event-image-manager' ); ?></label></th>
                        <td><input type="color" id="eim_secondary_color" name="eim_secondary_color" value="<?php echo esc_attr( $opts['secondary_color'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="eim_font_family"><?php esc_html_e( 'Font Family', 'event-image-manager' ); ?></label></th>
                        <td><input type="text" id="eim_font_family" name="eim_font_family" value="<?php echo esc_attr( $opts['font_family'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="eim_layout"><?php esc_html_e( 'Gallery Layout', 'event-image-manager' ); ?></label></th>
                        <td>
                            <select id="eim_layout" name="eim_layout">
                                <?php foreach ( array( 'grid', 'masonry', 'carousel' ) as $layout ) : ?>
                                    <option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $opts['layout'], $layout ); ?>>
                                        <?php echo esc_html( ucfirst( $layout ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Theme', 'event-image-manager' ) ); ?>
            </form>
        </div>
        <?php
    }

    /** Handle the theme save form submission. */
    public function save_theme_options() {
        if ( ! current_user_can( 'manage_options' )
            || ! isset( $_POST['eim_theme_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['eim_theme_nonce'] ) ),
                'eim_save_theme'
            )
        ) {
            wp_die( esc_html__( 'Security check failed.', 'event-image-manager' ) );
        }

        $allowed_layouts = array( 'grid', 'masonry', 'carousel' );

        $primary   = isset( $_POST['eim_primary_color'] )   ? sanitize_hex_color( wp_unslash( $_POST['eim_primary_color'] ) )         : self::DEFAULTS['primary_color'];
        $secondary = isset( $_POST['eim_secondary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['eim_secondary_color'] ) )       : self::DEFAULTS['secondary_color'];
        $font      = isset( $_POST['eim_font_family'] )     ? sanitize_text_field( wp_unslash( $_POST['eim_font_family'] ) )           : self::DEFAULTS['font_family'];
        $layout    = isset( $_POST['eim_layout'] )          ? sanitize_text_field( wp_unslash( $_POST['eim_layout'] ) )                : 'grid';

        if ( ! in_array( $layout, $allowed_layouts, true ) ) {
            $layout = 'grid';
        }

        update_option( 'eim_theme_primary_color',   $primary );
        update_option( 'eim_theme_secondary_color', $secondary );
        update_option( 'eim_theme_font_family',     $font );
        update_option( 'eim_theme_layout',          $layout );

        wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Frontend
    // -------------------------------------------------------------------------

    /** Enqueue inline CSS based on current theme settings. */
    public function enqueue_custom_css() {
        $opts = $this->get_options();
        $css  = sprintf(
            ':root { --eim-primary: %1$s; --eim-secondary: %2$s; --eim-font: %3$s; } .eim-gallery { font-family: %3$s; }',
            esc_attr( $opts['primary_color'] ),
            esc_attr( $opts['secondary_color'] ),
            esc_attr( $opts['font_family'] )
        );
        wp_register_style( 'eim-theme', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_style( 'eim-theme' );
        wp_add_inline_style( 'eim-theme', $css );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the current theme options merged with defaults.
     *
     * @return array
     */
    private function get_options() {
        return array(
            'primary_color'   => get_option( 'eim_theme_primary_color',   self::DEFAULTS['primary_color'] ),
            'secondary_color' => get_option( 'eim_theme_secondary_color', self::DEFAULTS['secondary_color'] ),
            'font_family'     => get_option( 'eim_theme_font_family',     self::DEFAULTS['font_family'] ),
            'layout'          => get_option( 'eim_theme_layout',          self::DEFAULTS['layout'] ),
        );
    }
}
