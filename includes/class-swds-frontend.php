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
        add_action( 'wp_head', array( $this, 'partial_css' ), 2 );
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
        // Candidate fix 1 — keep the gradient exactly as it looks, but give
        // the glyphs a hundredth of a percent of fill instead of none at
        // all, so they count as painted text. Same idea as --sw-fade-floor.
        if ( $want( 'grad2' ) ) {
            $css .= '.sw-h-gradient span{color:rgba(255,255,255,.01)!important;-webkit-text-fill-color:rgba(255,255,255,.01)!important}';
        }
        // Candidate fix 2 — drop the clipping altogether and colour the
        // span with a solid accent. Visibly different, but nothing about
        // it is transparent.
        if ( $want( 'grad3' ) ) {
            $css .= '.sw-h-gradient span{background:none!important;-webkit-background-clip:border-box!important;background-clip:border-box!important;color:var(--sw-blue-2,#5cc8ff)!important;-webkit-text-fill-color:var(--sw-blue-2,#5cc8ff)!important}';
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
        // The never-ending ones: the floating gallery cards and the panning
        // screenshots. Dropped rather than paused, because pausing would
        // freeze them at their first frame.
        if ( $want( 'float' ) ) {
            $css .= '.sw-shot{animation-name:swShotIn!important}'
                 . '.sw-browser-page img,.sw-phone-screen img{animation:none!important}';
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
     * Diagnostic switch: ?swds-css=<from>-<to> — serve only part of the
     * stylesheet, as a percentage range of its top-level rules.
     *
     *   ?swds-css=0-50    first half
     *   ?swds-css=50-100  second half
     *
     * The point is a bisection that does not depend on guessing which
     * rules matter: halve, measure, halve the failing half again. Six or
     * seven runs land on the rule whatever it turns out to be.
     *
     * The :root block always comes along, whatever the range, so that
     * custom properties keep their values and a missing variable never
     * looks like a finding.
     */
    public function partial_css() {
        $range = $this->css_range();
        if ( ! $range ) {
            return;
        }
        list( $from, $to ) = $range;

        $file = SWDS_DIR . 'assets/sw-design-system.css';
        if ( ! is_readable( $file ) ) {
            return;
        }
        $rules = self::split_rules( (string) file_get_contents( $file ) );
        $total = count( $rules );
        if ( ! $total ) {
            return;
        }

        $start = (int) floor( $total * $from / 100 );
        $end   = (int) ceil( $total * $to / 100 );
        $slice = array_slice( $rules, $start, max( 0, $end - $start ) );

        // Keep :root wherever it lives, so variables always resolve.
        $roots = array();
        foreach ( $rules as $i => $r ) {
            if ( $i >= $start && $i < $end ) { continue; }
            if ( false !== strpos( $r, ':root' ) && false !== strpos( $r, '--sw-' ) ) {
                $roots[] = $r;
            }
        }

        printf(
            "<!-- swds-css: pravidel celkem %d, posílám %d–%d -->\n<style id=\"swds-partial\">%s</style>\n",
            $total,
            $start + 1,
            $end,
            implode( '', $roots ) . implode( '', $slice ) // phpcs:ignore WordPress.Security.EscapeOutput
        );
    }

    /** Parse ?swds-css into a clamped [from, to] percentage pair, or false. */
    private function css_range() {
        if ( empty( $_GET['swds-css'] ) ) {
            return false;
        }
        $raw = sanitize_text_field( wp_unslash( $_GET['swds-css'] ) );
        if ( ! preg_match( '/^(\d{1,3})\s*-\s*(\d{1,3})$/', $raw, $m ) ) {
            return false;
        }
        $from = max( 0, min( 100, (int) $m[1] ) );
        $to   = max( 0, min( 100, (int) $m[2] ) );

        return ( $to > $from ) ? array( $from, $to ) : false;
    }

    /**
     * Split a stylesheet into top-level rules.
     *
     * Brace counting, with comments and strings skipped so a brace inside
     * either cannot throw the depth off. An @media block counts as one
     * rule, which is what we want — a range must never cut a block open.
     *
     * @return array<int,string>
     */
    private static function split_rules( $css ) {
        $out   = array();
        $len   = strlen( $css );
        $depth = 0;
        $start = 0;
        $i     = 0;

        while ( $i < $len ) {
            $c = $css[ $i ];

            if ( '/' === $c && $i + 1 < $len && '*' === $css[ $i + 1 ] ) {
                $close = strpos( $css, '*/', $i + 2 );
                $i     = ( false === $close ) ? $len : $close + 2;
                continue;
            }
            if ( '"' === $c || "'" === $c ) {
                $q = $c;
                $i++;
                while ( $i < $len && $css[ $i ] !== $q ) {
                    $i += ( '\\' === $css[ $i ] ) ? 2 : 1;
                }
                $i++;
                continue;
            }
            if ( '{' === $c ) { $depth++; }
            if ( '}' === $c ) {
                $depth--;
                if ( $depth <= 0 ) {
                    $out[] = substr( $css, $start, $i - $start + 1 );
                    $start = $i + 1;
                    $depth = 0;
                }
            }
            $i++;
        }
        return $out;
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
        // A requested slice replaces the stylesheet entirely (printed inline
        // by partial_css), so the full file must not load alongside it.
        if ( ! $this->off( 'css' ) && ! $this->css_range() ) {
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
