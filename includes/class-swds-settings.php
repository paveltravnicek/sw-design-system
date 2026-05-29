<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Settings
 *
 * Registers the admin screen under Appearance (themes.php), restricted to
 * administrators (manage_options). Handles saving, preset application,
 * export/import, and renders the tabbed UI + help + component library.
 */
class SWDS_Settings {

    const CAP  = 'manage_options'; // administrators only
    const SLUG = 'sw-design-system';

    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'handle_post' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }

    public function menu() {
        add_theme_page(
            'SW Design System',
            'SW Design System',
            self::CAP,
            self::SLUG,
            array( $this, 'render' )
        );
    }

    public function assets( $hook ) {
        if ( 'appearance_page_' . self::SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'swds-admin', SWDS_URL . 'assets/admin.css', array(), SWDS_VERSION );
        wp_enqueue_script( 'swds-admin', SWDS_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), SWDS_VERSION, true );
    }

    /**
     * Handle all POST actions: save, apply preset, import, reset.
     */
    public function handle_post() {
        if ( empty( $_POST['swds_action'] ) || ! current_user_can( self::CAP ) ) {
            return;
        }
        check_admin_referer( 'swds_save', 'swds_nonce' );

        $action  = sanitize_key( wp_unslash( $_POST['swds_action'] ) );
        $current = SWDS_Tokens::get();
        $notice  = 'saved';

        switch ( $action ) {

            case 'apply_preset':
                $preset  = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : 'ocean';
                $current = SWDS_Presets::apply( $preset );
                $notice  = 'preset';
                break;

            case 'reset':
                $current = SWDS_Tokens::defaults();
                $notice  = 'reset';
                break;

            case 'import':
                $raw = isset( $_POST['import_json'] ) ? wp_unslash( $_POST['import_json'] ) : '';
                $dec = json_decode( $raw, true );
                if ( is_array( $dec ) ) {
                    $current = array_merge( SWDS_Tokens::defaults(), $this->sanitize_all( $dec ) );
                    $notice  = 'imported';
                } else {
                    $notice = 'import_error';
                }
                break;

            case 'refresh_library':
                SWDS_Library::components( true ); // force refresh transient
                $redirect = add_query_arg(
                    array( 'page' => self::SLUG, 'swds_notice' => 'library' ),
                    admin_url( 'themes.php' )
                );
                wp_safe_redirect( $redirect );
                exit;

            case 'save':
            default:
                $input   = isset( $_POST['swds'] ) && is_array( $_POST['swds'] ) ? wp_unslash( $_POST['swds'] ) : array();
                $current = array_merge( $current, $this->sanitize_all( $input ) );
                $current['_preset'] = 'custom';
                $notice  = 'saved';
                break;
        }

        update_option( SWDS_OPTION, $current );
        SWDS_Generator::generate( $current );

        $redirect = add_query_arg(
            array( 'page' => self::SLUG, 'swds_notice' => $notice ),
            admin_url( 'themes.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Sanitize a raw input array against the schema field types.
     */
    private function sanitize_all( array $input ) {
        $clean = array();
        foreach ( SWDS_Tokens::schema() as $group ) {
            foreach ( $group['fields'] as $f ) {
                $key = $f['key'];
                if ( ! array_key_exists( $key, $input ) ) {
                    // toggles submit nothing when off
                    if ( 'toggle' === $f['type'] ) {
                        $clean[ $key ] = false;
                    }
                    continue;
                }
                $val = $input[ $key ];
                switch ( $f['type'] ) {
                    case 'color':
                        $clean[ $key ] = SWDS_Generator::sanitize_color( $val );
                        break;
                    case 'range':
                        $n = intval( $val );
                        $n = max( $f['min'], min( $f['max'], $n ) );
                        $clean[ $key ] = $n;
                        break;
                    case 'select':
                        $opts = array_keys( $f['options'] );
                        $clean[ $key ] = in_array( $val, $opts, true ) ? $val : $f['default'];
                        break;
                    case 'toggle':
                        $clean[ $key ] = (bool) $val;
                        break;
                    default:
                        $clean[ $key ] = sanitize_text_field( $val );
                }
            }
        }
        return $clean;
    }

    /**
     * Render the whole screen. Delegates body to the view partial.
     */
    public function render() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Nemáte oprávnění k této stránce.', 'sw-design-system' ) );
        }
        $s       = SWDS_Tokens::get();
        $schema  = SWDS_Tokens::schema();
        $presets = SWDS_Presets::all();
        require SWDS_DIR . 'includes/view-settings.php';
    }
}
