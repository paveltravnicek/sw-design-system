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
        // Priority 999: after the stylesheets, so the overrides win.
        add_action( 'wp_head', array( $this, 'kill_css' ), 999 );
    }

    /**
     * Diagnostic switch: ?swds-kill=anim|grad|canvas|hero|reveal|type
     *
     * Neutralises one group of rules from the stylesheet, for one request,
     * by printing overrides after it. Where ?swds-off=css answers "is it
     * the stylesheet at all", this answers "which part of it".
     *
     * Same reasoning as off(): it only ever takes styling away, so it
     * needs no capability check and works for a logged-out measuring tool.
     * Temporary — remove once the question it was added for is answered.
     */
    public function kill_css() {
        if ( empty( $_GET['swds-kill'] ) ) {
            return;
        }
        $raw   = sanitize_text_field( wp_unslash( $_GET['swds-kill'] ) );
        $parts = array_map( 'trim', explode( ',', $raw ) );
        $all   = in_array( 'all', $parts, true );
        $want  = function ( $k ) use ( $parts, $all ) {
            return $all || in_array( $k, $parts, true );
        };

        $css = '';

        // Entrance animations — the hero copy and the gallery cards.
        if ( $want( 'anim' ) ) {
            $css .= '.sw-fade-in,.sw-fade-in-2,.sw-fade-in-3,.sw-shot{animation:none!important;opacity:1!important;transform:none!important;scale:1!important;filter:none!important}';
        }
        // Gradient headline text (painted through background-clip, so the
        // text itself is transparent).
        if ( $want( 'grad' ) ) {
            $css .= '.sw-h-gradient span{background:none!important;-webkit-background-clip:border-box!important;background-clip:border-box!important;color:inherit!important;-webkit-text-fill-color:currentColor!important}';
        }
        // The page-wide wrapper: its gradient background and stacking context.
        if ( $want( 'canvas' ) ) {
            $css .= '.sw-canvas{background:none!important;isolation:auto!important;position:static!important}.sw-canvas::before,.sw-canvas::after{display:none!important}';
        }
        // The hero gallery's absolute positioning and rotation.
        if ( $want( 'hero' ) ) {
            $css .= '.sw-hero-gallery{height:auto!important}.sw-shot{position:static!important;width:auto!important;rotate:none!important;animation:none!important;opacity:1!important;scale:1!important}';
        }
        // Scroll-in reveal: show everything straight away.
        if ( $want( 'reveal' ) ) {
            $css .= '[data-sw-reveal] .sw-reveal-item{opacity:1!important;transform:none!important}';
        }
        // Headline sizing and letter-spacing.
        if ( $want( 'type' ) ) {
            $css .= '.sw-h,.sw-h-display,.sw-h-large,.sw-h-display--wide{font-size:2rem!important;letter-spacing:normal!important;line-height:1.2!important}';
        }

        if ( $css ) {
            echo "<style id=\"swds-kill\">" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    /**
     * Diagnostic switch: ?swds-off=css|tokens|js|flag (comma-separated, or "all").
     *
     * Leaves out one piece of what this plugin puts on the page, for one
     * request, so a measuring tool can be pointed at a URL instead of the
     * plugin having to be edited or deactivated. It only ever REMOVES
     * things — it cannot change or expose anything — which is why it needs
     * no capability check and works for a logged-out tool like PageSpeed.
     *
     * Only for narrowing down a problem. Nothing links to these URLs and
     * a normal visit is untouched.
     */
    private function off( $part ) {
        if ( empty( $_GET['swds-off'] ) ) {
            return false;
        }
        $raw   = sanitize_text_field( wp_unslash( $_GET['swds-off'] ) );
        $parts = array_map( 'trim', explode( ',', $raw ) );

        return in_array( 'all', $parts, true ) || in_array( $part, $parts, true );
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
        if ( $this->off( 'flag' ) ) { return; }

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
        if ( ! $this->off( 'css' ) ) {
            wp_enqueue_style(
                'sw-design-system',
                SWDS_URL . 'assets/sw-design-system.css',
                array(),
                SWDS_VERSION
            );
        }

        // 2) Generated tokens — loaded AFTER, so it overrides the defaults.
        // Dropping the main stylesheet drops these with it: they are
        // declared dependent on it, and on their own they would only set
        // variables nothing reads.
        if ( ! $this->off( 'tokens' ) && ! $this->off( 'css' ) && SWDS_Generator::exists() ) {
            wp_enqueue_style(
                'sw-design-tokens',
                SWDS_Generator::url(),
                array( 'sw-design-system' ),
                SWDS_Generator::version()
            );
        }

        // 3) Runtime (reveal + parallax). Deferred, footer.
        if ( $this->off( 'js' ) ) { return; }

        wp_enqueue_script(
            'sw-design-system',
            SWDS_URL . 'assets/sw-design-system.js',
            array(),
            SWDS_VERSION,
            true
        );
    }
}
