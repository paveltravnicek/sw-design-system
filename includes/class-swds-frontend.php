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
        // Priority 1: must land before the stylesheets so the flag is set
        // on <html> before the browser paints anything.
        add_action( 'wp_head', array( $this, 'reveal_flag' ), 1 );
    }

    /**
     * Marks <html> so reveal items are hidden from the very first paint.
     *
     * Without this the runtime JS (which sits in the footer) was the thing
     * that hid them: the browser painted every item fully visible, then the
     * script added the hiding class and — because that rule carries a
     * transition — the content visibly FADED OUT again before waiting to be
     * scrolled into view. Measured at close to a second on mobile.
     *
     * Setting the flag here instead means the item is never painted visible
     * in the first place. Kept inline and tiny on purpose: an external file
     * could not run early enough to prevent the flash.
     *
     * The flag covers ONLY the gap between first paint and the runtime
     * starting up; the runtime takes it off again the moment it has claimed
     * the items it found. Everything after that point is per-element. See
     * the two rules at the top of assets/sw-design-system.js — this flag
     * living for the whole life of the page is precisely what made a
     * component disappear when LiveCanvas re-rendered it after an edit.
     *
     * Both failsafes below exist because nothing may be left hidden with
     * no JS around to show it:
     *   - the timer drops the flag even if the runtime never arrives
     *     (404, blocked by an optimiser, JS error), and clears any stale
     *     per-item flag that got saved into the page markup;
     *   - the <noscript> block covers JS being off altogether, where
     *     neither the runtime nor the timer can ever run.
     */
    public function reveal_flag() {
        echo "<script>try{(function(){var d=document.documentElement;"
           . "d.classList.add('sw-ds-reveal');"
           . "setTimeout(function(){d.classList.remove('sw-ds-reveal');"
           . "if(!window.__swDS){var n=d.querySelectorAll('.sw-rv'),i=0;"
           . "for(;i<n.length;i++){n[i].classList.remove('sw-rv');}}},3000);"
           . "})();}catch(e){}</script>\n";

        echo "<noscript><style>[data-sw-reveal] .sw-reveal-item"
           . "{opacity:1!important;transform:none!important}</style></noscript>\n";
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
