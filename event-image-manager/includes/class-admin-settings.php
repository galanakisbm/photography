<?php
/**
 * Class EIM_Admin_Settings
 *
 * Provides a global settings page for the Event Image Manager plugin
 * under Settings → Event Image Manager.
 *
 * Settings managed here:
 *  - Watermark style and options (text, position, color, opacity, logo).
 *  - Default download permission level.
 *  - Comments: allow guests, auto-approve.
 *  - Image optimization: JPEG quality, PNG compression.
 *  - EXIF stripping on upload.
 */
class EIM_Admin_Settings {

    /** Option group name (used with register_setting / settings_fields). */
    const OPTION_GROUP = 'eim_settings';

    /** Settings page slug. */
    const PAGE_SLUG = 'eim-settings';

    public function __construct() {
        add_action( 'admin_menu',    array( $this, 'add_settings_page' ) );
        add_action( 'admin_init',    array( $this, 'register_settings' ) );
    }

    // -------------------------------------------------------------------------
    // Menu / page registration
    // -------------------------------------------------------------------------

    /** Register the settings page in the WP admin menu. */
    public function add_settings_page() {
        add_options_page(
            __( 'Event Image Manager Settings', 'event-image-manager' ),
            __( 'Event Image Manager', 'event-image-manager' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /** Register all plugin option fields. */
    public function register_settings() {
        // ── Watermark section ─────────────────────────────────────────────────
        add_settings_section(
            'eim_watermark_section',
            __( 'Watermark Settings', 'event-image-manager' ),
            null,
            self::PAGE_SLUG
        );

        $watermark_fields = array(
            array( 'eim_watermark_style',         __( 'Style', 'event-image-manager' ),           'render_watermark_style' ),
            array( 'eim_watermark_text',          __( 'Watermark Text', 'event-image-manager' ),  'render_text_field' ),
            array( 'eim_watermark_color',         __( 'Color (hex)', 'event-image-manager' ),     'render_color_field' ),
            array( 'eim_watermark_opacity',       __( 'Opacity (0–127)', 'event-image-manager' ), 'render_opacity_field' ),
            array( 'eim_watermark_font_size_pct', __( 'Font Size %', 'event-image-manager' ),     'render_font_size_field' ),
            array( 'eim_watermark_position',      __( 'Position', 'event-image-manager' ),        'render_position_field' ),
            array( 'eim_watermark_logo_path',     __( 'Logo Path', 'event-image-manager' ),       'render_logo_path_field' ),
        );

        foreach ( $watermark_fields as $field ) {
            register_setting( self::OPTION_GROUP, $field[0], array( 'sanitize_callback' => 'sanitize_text_field' ) );
            add_settings_field( $field[0], $field[1], array( $this, $field[2] ), self::PAGE_SLUG, 'eim_watermark_section', array( 'option' => $field[0] ) );
        }

        // ── Download section ──────────────────────────────────────────────────
        add_settings_section(
            'eim_download_section',
            __( 'Download Settings', 'event-image-manager' ),
            null,
            self::PAGE_SLUG
        );

        register_setting( self::OPTION_GROUP, 'eim_default_download_permission', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        add_settings_field(
            'eim_default_download_permission',
            __( 'Default Download Permission', 'event-image-manager' ),
            array( $this, 'render_download_permission_field' ),
            self::PAGE_SLUG,
            'eim_download_section'
        );

        // ── Comments section ──────────────────────────────────────────────────
        add_settings_section(
            'eim_comments_section',
            __( 'Comments Settings', 'event-image-manager' ),
            null,
            self::PAGE_SLUG
        );

        register_setting( self::OPTION_GROUP, 'eim_comments_allow_guests', array( 'sanitize_callback' => 'absint' ) );
        add_settings_field(
            'eim_comments_allow_guests',
            __( 'Allow Guest Comments', 'event-image-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'eim_comments_section',
            array( 'option' => 'eim_comments_allow_guests' )
        );

        // ── Image optimization section ─────────────────────────────────────────
        add_settings_section(
            'eim_optimizer_section',
            __( 'Image Optimization', 'event-image-manager' ),
            null,
            self::PAGE_SLUG
        );

        register_setting( self::OPTION_GROUP, 'eim_jpeg_quality', array( 'sanitize_callback' => 'absint' ) );
        add_settings_field(
            'eim_jpeg_quality',
            __( 'JPEG Quality (1–100)', 'event-image-manager' ),
            array( $this, 'render_number_field' ),
            self::PAGE_SLUG,
            'eim_optimizer_section',
            array( 'option' => 'eim_jpeg_quality', 'default' => 85 )
        );

        register_setting( self::OPTION_GROUP, 'eim_strip_exif', array( 'sanitize_callback' => 'absint' ) );
        add_settings_field(
            'eim_strip_exif',
            __( 'Strip EXIF on Upload', 'event-image-manager' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'eim_optimizer_section',
            array( 'option' => 'eim_strip_exif' )
        );
    }

    // -------------------------------------------------------------------------
    // Page render
    // -------------------------------------------------------------------------

    /** Render the settings page. */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Event Image Manager Settings', 'event-image-manager' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    /** Render the watermark style select. */
    public function render_watermark_style( $args ) {
        $option  = $args['option'];
        $current = get_option( $option, 'text' );
        $styles  = array(
            'text'         => __( 'Text Watermark', 'event-image-manager' ),
            'diagonal'     => __( 'Diagonal Text', 'event-image-manager' ),
            'corner'       => __( 'Corner Placement', 'event-image-manager' ),
            'full-overlay' => __( 'Full Overlay (semi-transparent)', 'event-image-manager' ),
            'logo'         => __( 'Logo / Image Watermark', 'event-image-manager' ),
        );
        echo '<select name="' . esc_attr( $option ) . '">';
        foreach ( $styles as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /** Render a plain text input. */
    public function render_text_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '' );
        echo '<input type="text" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
    }

    /** Render a color (hex) text input. */
    public function render_color_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '#ffffff' );
        echo '<input type="text" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="#ffffff">';
    }

    /** Render an opacity input (0–127). */
    public function render_opacity_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, 60 );
        echo '<input type="number" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" min="0" max="127" class="small-text">';
        echo '<p class="description">' . esc_html__( '0 = fully opaque, 127 = fully transparent.', 'event-image-manager' ) . '</p>';
    }

    /** Render a font-size percentage input. */
    public function render_font_size_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, 5 );
        echo '<input type="number" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" min="1" max="30" class="small-text">';
        echo '<p class="description">' . esc_html__( 'As a percentage of image width.', 'event-image-manager' ) . '</p>';
    }

    /** Render a position select. */
    public function render_position_field( $args ) {
        $option    = $args['option'];
        $current   = get_option( $option, 'bottom-right' );
        $positions = array(
            'bottom-right' => __( 'Bottom Right', 'event-image-manager' ),
            'bottom-left'  => __( 'Bottom Left', 'event-image-manager' ),
            'top-right'    => __( 'Top Right', 'event-image-manager' ),
            'top-left'     => __( 'Top Left', 'event-image-manager' ),
            'center'       => __( 'Center', 'event-image-manager' ),
        );
        echo '<select name="' . esc_attr( $option ) . '">';
        foreach ( $positions as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /** Render a file path text field for the logo. */
    public function render_logo_path_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '' );
        echo '<input type="text" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__( 'Absolute server path to the logo image (PNG recommended).', 'event-image-manager' ) . '</p>';
    }

    /** Render the download permission select. */
    public function render_download_permission_field() {
        $option  = 'eim_default_download_permission';
        $current = get_option( $option, 'none' );
        $options = array(
            'none'        => __( 'No Download', 'event-image-manager' ),
            'watermarked' => __( 'Download with Watermark', 'event-image-manager' ),
            'original'    => __( 'Download without Watermark', 'event-image-manager' ),
            'selective'   => __( 'Selective Download', 'event-image-manager' ),
        );
        echo '<select name="' . esc_attr( $option ) . '">';
        foreach ( $options as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /** Render a checkbox field. */
    public function render_checkbox_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, 0 );
        echo '<input type="checkbox" name="' . esc_attr( $option ) . '" value="1" ' . checked( $value, 1, false ) . '>';
    }

    /** Render a number input field. */
    public function render_number_field( $args ) {
        $option  = $args['option'];
        $default = isset( $args['default'] ) ? $args['default'] : 0;
        $value   = get_option( $option, $default );
        echo '<input type="number" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" min="1" max="100" class="small-text">';
    }
}
