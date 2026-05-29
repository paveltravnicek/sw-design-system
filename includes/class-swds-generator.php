<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Generator
 *
 * Translates stored tokens into a real CSS file written to
 * wp-content/uploads/sw-ds/sw-tokens.css. This file only contains the
 * :root{} (and optional [data-bs-theme=dark]{}) custom-property overrides.
 * The component rules live in the static assets/sw-design-system.css.
 *
 * Nothing is printed inline into <head>.
 */
class SWDS_Generator {

    const FILENAME = 'sw-tokens.css';

    /** Absolute path to the generated file. */
    public static function path() {
        $up = wp_upload_dir();
        return trailingslashit( $up['basedir'] ) . SWDS_UPLOAD_SUBDIR . '/' . self::FILENAME;
    }

    /** Public URL of the generated file. */
    public static function url() {
        $up = wp_upload_dir();
        return trailingslashit( $up['baseurl'] ) . SWDS_UPLOAD_SUBDIR . '/' . self::FILENAME;
    }

    public static function exists() {
        return file_exists( self::path() );
    }

    /** Cache-busting version = file modification time. */
    public static function version() {
        return self::exists() ? (string) filemtime( self::path() ) : SWDS_VERSION;
    }

    /**
     * Build the CSS string from a settings array.
     */
    public static function build_css( array $s ) {
        $v = self::vars( $s );

        $css  = "/* SW Design System — generated tokens. Do not edit by hand; */\n";
        $css .= "/* this file is regenerated from Appearance → SW Design System. */\n";
        $css .= ":root{\n";
        foreach ( $v as $name => $value ) {
            $css .= "  {$name}: {$value};\n";
        }
        $css .= "}\n";

        // Dark mode block (only if enabled).
        if ( ! empty( $s['dark_enabled'] ) ) {
            $css .= "[data-bs-theme=\"dark\"]{\n";
            $css .= '  --sw-text: ' . self::sanitize_color( $s['dark_text'] ) . ";\n";
            $css .= '  --sw-bg-1: ' . self::sanitize_color( $s['dark_bg'] ) . ";\n";
            // Derive a slightly darker second/third stop from the dark bg for depth.
            $css .= '  --sw-bg-2: ' . self::shade( $s['dark_bg'], -0.18 ) . ";\n";
            $css .= '  --sw-bg-3: ' . self::shade( $s['dark_bg'], -0.4 ) . ";\n";
            $css .= "}\n";
        }

        return $css;
    }

    /**
     * Map tokens -> CSS custom properties. This is where "personality"
     * switches expand into concrete values.
     */
    public static function vars( array $s ) {
        $brand   = self::sanitize_color( $s['brand'] );
        $brand2  = self::sanitize_color( $s['brand_2'] );
        $rgb     = self::hex_to_rgb( $brand );
        $rgbStr  = $rgb ? "{$rgb[0]}, {$rgb[1]}, {$rgb[2]}" : '0, 150, 226';

        $vars = array();

        // Colors
        $vars['--sw-blue']        = $brand;
        $vars['--sw-blue-2']      = $brand2;
        $vars['--sw-bg-1']        = self::sanitize_color( $s['bg_1'] );
        $vars['--sw-bg-2']        = self::sanitize_color( $s['bg_2'] );
        $vars['--sw-bg-3']        = self::sanitize_color( $s['bg_3'] );
        $vars['--sw-text']        = self::sanitize_color( $s['text'] );
        $vars['--sw-text-muted']  = self::sanitize_color( $s['text_muted'] );

        // Atmosphere — background gradient angle
        $vars['--sw-bg-angle']    = intval( $s['bg_angle'] ) . 'deg';

        // Atmosphere — spotlights
        $spot = array(
            'off'    => array( '0',   '0' ),
            'subtle' => array( '.20', '.10' ),
            'normal' => array( '.38', '.20' ),
            'strong' => array( '.55', '.32' ),
        );
        list( $sp1, $sp2 ) = $spot[ $s['spot_intensity'] ] ?? $spot['normal'];
        $vars['--sw-spot-1'] = "rgba({$rgbStr}, {$sp1})";
        $vars['--sw-spot-2'] = "rgba(92, 200, 255, {$sp2})";

        // Atmosphere — grid overlay opacity
        $grid = array( 'off' => '0', 'faint' => '.06', 'on' => '.12' );
        $vars['--sw-grid-opacity'] = $grid[ $s['grid_overlay'] ] ?? '.12';

        // Shape — radius scale
        $radius = array(
            'sharp'  => array( '0',      '0' ),
            'subtle' => array( '.6rem',  '.4rem' ),
            'soft'   => array( '1.4rem', '1.2rem' ),
            'round'  => array( '2rem',   '1.6rem' ),
        );
        list( $rCard, $rImg ) = $radius[ $s['radius'] ] ?? $radius['soft'];
        $vars['--sw-radius-card'] = $rCard;
        $vars['--sw-radius-cta']  = $rCard;
        $vars['--sw-radius-img']  = $rImg;

        // Shape — button shape
        $btn = array( 'pill' => '999px', 'rounded' => '.7rem', 'square' => '0' );
        $vars['--sw-radius-pill'] = $btn[ $s['button_shape'] ] ?? '999px';

        // Shape — card style
        switch ( $s['card_style'] ) {
            case 'solid':
                $vars['--sw-card-bg']     = '#0b1730';
                $vars['--sw-card-border'] = 'rgba(255,255,255,.10)';
                $vars['--sw-card-blur']   = '0px';
                break;
            case 'outline':
                $vars['--sw-card-bg']     = 'transparent';
                $vars['--sw-card-border'] = 'rgba(255,255,255,.22)';
                $vars['--sw-card-blur']   = '0px';
                break;
            case 'glass':
            default:
                $vars['--sw-card-bg']     = 'linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04))';
                $vars['--sw-card-border'] = 'rgba(255,255,255,.13)';
                $vars['--sw-card-blur']   = '14px';
                break;
        }

        // Depth — shadows
        $shadow = array(
            'flat'     => array( 'none', 'none', 'none' ),
            'soft'     => array(
                '0 24px 80px rgba(0,0,0,.25)',
                '0 40px 120px rgba(0,0,0,.55), 0 0 80px rgba(' . $rgbStr . ',.18)',
                '0 32px 110px rgba(0,0,0,.3)',
            ),
            'dramatic' => array(
                '0 32px 90px rgba(0,0,0,.45)',
                '0 50px 150px rgba(0,0,0,.7), 0 0 100px rgba(' . $rgbStr . ',.3)',
                '0 40px 130px rgba(0,0,0,.5)',
            ),
        );
        list( $shCard, $shDeep, $shCta ) = $shadow[ $s['shadow'] ] ?? $shadow['soft'];
        $vars['--sw-shadow-card'] = $shCard;
        $vars['--sw-shadow-deep'] = $shDeep;
        $vars['--sw-shadow-cta']  = $shCta;

        // Depth — glow on buttons
        $glow = array(
            'off'    => 'none',
            'subtle' => '0 10px 30px rgba(' . $rgbStr . ',.20)',
            'normal' => '0 18px 50px rgba(' . $rgbStr . ',.32)',
            'neon'   => '0 0 24px rgba(' . $rgbStr . ',.6), 0 18px 50px rgba(' . $rgbStr . ',.45)',
        );
        $vars['--sw-shadow-btn'] = $glow[ $s['glow'] ] ?? $glow['normal'];

        // Typography — heading scale (clamp bounds)
        $scale = array(
            'compact' => array( 'clamp(2.2rem,5vw,4.6rem)', 'clamp(1.8rem,4vw,3.4rem)', 'clamp(1.7rem,4vw,3.2rem)' ),
            'normal'  => array( 'clamp(3rem,7vw,6.8rem)',   'clamp(2.2rem,5vw,4.8rem)', 'clamp(2rem,5vw,4.4rem)' ),
            'large'   => array( 'clamp(3.4rem,8vw,8rem)',   'clamp(2.6rem,6vw,5.6rem)', 'clamp(2.4rem,6vw,5.2rem)' ),
            'huge'    => array( 'clamp(3.8rem,9vw,9.5rem)',  'clamp(3rem,7vw,6.6rem)',   'clamp(2.8rem,7vw,6rem)' ),
        );
        list( $hD, $hL, $hC ) = $scale[ $s['heading_scale'] ] ?? $scale['normal'];
        $vars['--sw-h-display'] = $hD;
        $vars['--sw-h-large']   = $hL;
        $vars['--sw-h-cta']     = $hC;

        // Typography — tracking
        $track = array( 'tight' => '-.065em', 'normal' => '-.02em', 'wide' => '0' );
        $vars['--sw-h-tracking'] = $track[ $s['heading_tracking'] ] ?? '-.065em';

        // Typography — heading gradient on/off
        if ( empty( $s['heading_gradient'] ) ) {
            // Solid: span shows plain text color (fallback var defeats transparent fill).
            $vars['--sw-h-grad']          = 'none';
            $vars['--sw-h-grad-fallback'] = self::sanitize_color( $s['text'] );
        } else {
            $vars['--sw-h-grad']          = 'linear-gradient(90deg, #fff, #a9e6ff 42%, ' . $brand2 . ')';
            $vars['--sw-h-grad-fallback'] = 'transparent';
        }

        // Motion — distances/durations
        $motionOn = ! empty( $s['motion_enabled'] );
        $reveal = array(
            'subtle' => array( '16px', '.55s' ),
            'normal' => array( '28px', '.8s' ),
            'bold'   => array( '48px', '1.05s' ),
        );
        list( $rDist, $rDur ) = $reveal[ $s['reveal_style'] ] ?? $reveal['normal'];
        $vars['--sw-reveal-distance'] = $motionOn ? $rDist : '0px';
        $vars['--sw-reveal-duration'] = $motionOn ? $rDur  : '.001ms';

        // Screenshot scroll: animate only when motion is on (paused = 0s).
        $vars['--sw-scroll-play']     = $motionOn ? 'running' : 'paused';
        $vars['--sw-scroll-duration'] = '15s';

        // Layout
        $spacing = array(
            'compact' => 'clamp(2rem,3.5vw,3rem)',
            'normal'  => 'clamp(2.75rem,5vw,4.25rem)',
            'airy'    => 'clamp(4rem,7vw,7rem)',
        );
        $vars['--sw-section-py'] = $spacing[ $s['section_spacing'] ] ?? $spacing['normal'];

        return $vars;
    }

    /**
     * Write the CSS file. Creates the subdir and an index.html guard.
     * Returns true on success.
     */
    public static function generate( $settings ) {
        if ( ! is_array( $settings ) ) {
            $settings = SWDS_Tokens::defaults();
        }
        $settings = array_merge( SWDS_Tokens::defaults(), $settings );

        $up  = wp_upload_dir();
        $dir = trailingslashit( $up['basedir'] ) . SWDS_UPLOAD_SUBDIR;

        if ( ! wp_mkdir_p( $dir ) ) {
            return false;
        }
        // Silence directory listing.
        $index = trailingslashit( $dir ) . 'index.html';
        if ( ! file_exists( $index ) ) {
            @file_put_contents( $index, '' );
        }

        $css = self::build_css( $settings );

        // Use WP_Filesystem if available, else fall back.
        $ok = false;
        if ( function_exists( 'WP_Filesystem' ) ) {
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ( $wp_filesystem ) {
                $ok = $wp_filesystem->put_contents( self::path(), $css, FS_CHMOD_FILE );
            }
        }
        if ( ! $ok ) {
            $ok = false !== @file_put_contents( self::path(), $css );
        }
        return $ok;
    }

    /* ---------- helpers ---------- */

    public static function sanitize_color( $c ) {
        $c = trim( (string) $c );
        // Allow hex, rgb(a), and a few keywords.
        if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $c ) ) {
            return $c;
        }
        if ( preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/', $c ) ) {
            return $c;
        }
        if ( in_array( strtolower( $c ), array( 'transparent', 'currentcolor', 'inherit' ), true ) ) {
            return $c;
        }
        return '#000000';
    }

    public static function hex_to_rgb( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) < 6 ) {
            return null;
        }
        return array(
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
        );
    }

    /** Darken (-) or lighten (+) a hex color by a ratio -1..1. */
    public static function shade( $hex, $ratio ) {
        $rgb = self::hex_to_rgb( self::sanitize_color( $hex ) );
        if ( ! $rgb ) {
            return $hex;
        }
        $adj = function( $c ) use ( $ratio ) {
            if ( $ratio < 0 ) {
                return (int) max( 0, round( $c * ( 1 + $ratio ) ) );
            }
            return (int) min( 255, round( $c + ( 255 - $c ) * $ratio ) );
        };
        return sprintf( '#%02x%02x%02x', $adj( $rgb[0] ), $adj( $rgb[1] ), $adj( $rgb[2] ) );
    }
}
