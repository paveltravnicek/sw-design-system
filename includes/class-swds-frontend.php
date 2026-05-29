<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Frontend
 *
 * Enqueues the static component CSS, the generated tokens CSS (after it),
 * and the runtime JS. All real files — nothing inline.
 */
class SWDS_Frontend {

    public function hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function enqueue() {
        // 1) Static component stylesheet (contains default :root as fallback).
        wp_enqueue_style(
            'sw-design-system',
            SWDS_URL . 'assets/sw-design-system.css',
            array(),
            SWDS_VERSION
        );

        // 2) Generated tokens — loaded AFTER, so it overrides the defaults.
        if ( SWDS_Generator::exists() ) {
            wp_enqueue_style(
                'sw-design-tokens',
                SWDS_Generator::url(),
                array( 'sw-design-system' ),
                SWDS_Generator::version()
            );
        }

        // 3) Runtime (reveal + parallax). Deferred, footer.
        wp_enqueue_script(
            'sw-design-system',
            SWDS_URL . 'assets/sw-design-system.js',
            array(),
            SWDS_VERSION,
            true
        );
    }
}
