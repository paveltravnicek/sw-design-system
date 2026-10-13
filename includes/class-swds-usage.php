<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Usage
 *
 * Finds where each component is actually used across the site.
 *
 * Why this exists: a component is pasted into a page as plain HTML, and
 * from that moment the copy has no link back to the library. Change a
 * component and there is no way to know which pages still carry the old
 * shape — which is exactly what made people avoid changing components at
 * all. This screen answers the question the plugin could not answer:
 * where is it, and is it current.
 *
 * Strictly read-only. It runs one query, parses the matching content and
 * reports. It never writes to a post. Converting stays a manual act done
 * one section at a time, because a pasted copy may have been edited by
 * hand and no rule can tell a deliberate edit from drift.
 */
class SWDS_Usage {

    const TRANSIENT = 'swds_usage_scan';
    const TTL       = 30 * MINUTE_IN_SECONDS;

    /** Never look at more than this many posts in one scan. */
    const MAX_POSTS = 800;

    /**
     * Runtime state that leaked into saved markup. Harmless to render,
     * but data-sw-init actively disables the widget it sits on, so a page
     * carrying it is worth flagging.
     */
    const JUNK = array( 'sw-anim-on', 'is-visible', 'sw-rv', 'data-sw-init' );

    /** Layout wrappers replaced by Bootstrap row/col; see the compat block in the CSS. */
    const LEGACY_GRIDS = array(
        'sw-hero-grid', 'sw-hub-grid', 'sw-content2img-grid',
        'sw-trust-grid', 'sw-process-grid', 'sw-pricing-grid', 'sw-faq-layout',
    );

    /**
     * Component catalogue, read out of the bundled files.
     *
     * Returns id => array( title, section_class, version ), where
     * section_class is the component family (.sw-trust, .sw-pricing, …).
     * Several components can share a family — the four heroes do — which
     * is the whole reason for the data-sw-c stamp: with it the match is
     * exact, without it we can only name the family.
     */
    public static function catalogue() {
        $def     = SWDS_Library::local_definition();
        $out     = array();
        $classes = array();

        foreach ( $def['components'] as $item ) {
            $path = SWDS_DIR . 'components/' . $item['file'];
            if ( ! file_exists( $path ) ) { continue; }

            $html = file_get_contents( $path );
            $id   = preg_replace( '#^component-|\.html$#', '', $item['file'] );

            $section = '';
            $version = 1;
            if ( preg_match( '#<section\b[^>]*class="([^"]*)"#', $html, $m ) ) {
                foreach ( preg_split( '#\s+#', $m[1], -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
                    if ( 'sw-section' === $c || 0 === strpos( $c, 'sw-surface' ) ) { continue; }
                    if ( 0 === strpos( $c, 'sw-' ) ) { $section = $c; break; }
                }
            }
            if ( preg_match( '#data-sw-c="' . preg_quote( $id, '#' ) . '@(\d+)"#', $html, $m ) ) {
                $version = (int) $m[1];
            }

            // Every sw- class anywhere in the component, and how many
            // lc-block elements it has. Both are used below to tell apart
            // components that share a family.
            $all = array();
            if ( preg_match_all( '#class="([^"]*)"#', $html, $cm ) ) {
                foreach ( $cm[1] as $cl ) {
                    foreach ( preg_split( '#\s+#', $cl, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
                        if ( 0 === strpos( $c, 'sw-' ) ) { $all[ $c ] = true; }
                    }
                }
            }
            $classes[ $id ] = $all;

            $out[ $id ] = array(
                'title'   => $item['title'],
                'section' => $section,
                'version' => $version,
                'cards'   => preg_match_all( '#class="[^"]*\blc-block\b#', $html ),
                'mark'    => '',
            );
        }

        /*
         * Four components answer to .sw-hero, two to .sw-pricing, and so
         * on — the family alone is not an identity. For each component,
         * find a class it has that no sibling in its family has; that
         * single class then identifies it. Worked out from the files
         * rather than written down, so it stays true as components change.
         *
         * .sw-pricing has no such class (the 3- and 4-column tables are
         * built from exactly the same parts), which is what the card
         * count is for. .sw-content2img has neither — the left and right
         * variants differ only in Bootstrap order classes — so those stay
         * unidentified and the screen says so instead of guessing.
         */
        foreach ( $out as $id => $c ) {
            $siblings = array();
            foreach ( $out as $oid => $oc ) {
                if ( $oid !== $id && $oc['section'] === $c['section'] ) { $siblings[] = $oid; }
            }
            if ( ! $siblings ) { continue; }

            $rest = array();
            foreach ( $siblings as $oid ) { $rest += $classes[ $oid ]; }

            foreach ( array_keys( $classes[ $id ] ) as $cl ) {
                if ( ! isset( $rest[ $cl ] ) ) { $out[ $id ]['mark'] = $cl; break; }
            }
        }

        return $out;
    }

    /**
     * Which component is this section? Returns a list of candidate ids —
     * one when it could be identified, several when the markup genuinely
     * does not say, empty when it is not ours.
     */
    public static function identify( $html, $cat ) {
        if ( ! preg_match( '#<section\b[^>]*class="([^"]*)"#i', $html, $m ) ) { return array(); }
        $classes = preg_split( '#\s+#', $m[1], -1, PREG_SPLIT_NO_EMPTY );

        $hits = array();
        foreach ( $cat as $id => $c ) {
            if ( $c['section'] && in_array( $c['section'], $classes, true ) ) { $hits[] = $id; }
        }
        if ( count( $hits ) < 2 ) { return $hits; }

        // Narrow by the distinguishing class, then by how many blocks there are.
        $byMark = array();
        foreach ( $hits as $id ) {
            $mk = $cat[ $id ]['mark'];
            if ( $mk && false !== strpos( $html, 'sw-' === substr( $mk, 0, 3 ) ? $mk : $mk ) ) {
                $byMark[] = $id;
            }
        }
        if ( 1 === count( $byMark ) ) { return $byMark; }
        if ( $byMark ) { $hits = $byMark; }

        $cards  = preg_match_all( '#class="[^"]*\blc-block\b#', $html );
        $byCard = array();
        foreach ( $hits as $id ) {
            if ( (int) $cat[ $id ]['cards'] === (int) $cards ) { $byCard[] = $id; }
        }
        return ( 1 === count( $byCard ) ) ? $byCard : $hits;
    }

    /**
     * Scan every post whose content looks like it contains a component.
     *
     * @param bool $force Skip the cache.
     * @return array {
     *     @type array $rows    One entry per component occurrence.
     *     @type int   $scanned How many posts were parsed.
     *     @type bool  $capped  True if MAX_POSTS cut the scan short.
     *     @type int   $time    Unix time of the scan.
     * }
     */
    public static function scan( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( self::TRANSIENT );
            if ( is_array( $cached ) ) { return $cached; }
        }

        global $wpdb;
        $cat = self::catalogue();

        // One query. Only posts that could plausibly hold a component, and
        // only the columns we need — post_content is the expensive part, so
        // the LIKE keeps the result set to pages actually built with this.
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_status, post_content
                   FROM {$wpdb->posts}
                  WHERE post_content LIKE %s
                    AND post_status IN ('publish','draft','pending','private','future')
                    AND post_type NOT IN ('revision','attachment','nav_menu_item')
               ORDER BY post_title ASC
                  LIMIT %d",
                '%' . $wpdb->esc_like( 'sw-section' ) . '%',
                self::MAX_POSTS + 1
            )
        );

        $capped = count( $posts ) > self::MAX_POSTS;
        if ( $capped ) { array_pop( $posts ); }

        $rows      = array();
        $seen_cls  = array();

        foreach ( $posts as $post ) {
            // Every sw- class that appears anywhere in this page's content,
            // not only inside recognised sections — a class can live in a
            // LiveCanvas dynamic view, a custom block or hand-written
            // markup, and it is still very much in use.
            if ( preg_match_all( '#\bsw-[a-z0-9-]+#', $post->post_content, $cm ) ) {
                foreach ( $cm[0] as $c ) { $seen_cls[ $c ] = true; }
            }

            foreach ( self::sections( $post->post_content ) as $index => $sec ) {
                $row = self::classify( $sec, $cat );
                if ( ! $row ) { continue; }   // a .sw-section we don't recognise
                $row['post_id']    = (int) $post->ID;
                $row['post_title'] = $post->post_title ? $post->post_title : '(bez názvu)';
                $row['post_type']  = $post->post_type;
                $row['status']     = $post->post_status;
                $row['index']      = $index;
                $rows[] = $row;
            }
        }

        $out = array(
            'rows'    => $rows,
            'scanned' => count( $posts ),
            'capped'  => $capped,
            'time'    => time(),
            'unused'  => self::unused_classes( array_keys( $seen_cls ) ),
        );
        set_transient( self::TRANSIENT, $out, self::TTL );
        return $out;
    }

    /**
     * Split post content into top-level <section class="sw-section …"> blocks.
     *
     * Deliberately a scanner rather than a DOM parse: post content is a
     * fragment, often with shortcodes and the odd unclosed tag, and
     * DOMDocument would rewrite it. We only need the boundaries, and we
     * need the bytes back exactly as they were so the converter can be
     * handed the real markup.
     *
     * @return array<int,array{html:string,offset:int}>
     */
    public static function sections( $content ) {
        $out = array();
        $len = strlen( $content );
        $pos = 0;

        while ( $pos < $len ) {
            if ( ! preg_match( '#<section\b[^>]*class="[^"]*\bsw-section\b#i', $content, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
                break;
            }
            $start = $m[0][1];

            // Walk forward counting nested <section> tags to find the close.
            $depth = 0;
            $i     = $start;
            $end   = false;
            while ( preg_match( '#</?section\b#i', $content, $t, PREG_OFFSET_CAPTURE, $i ) ) {
                $at = $t[0][1];
                if ( '/' === $content[ $at + 1 ] ) {
                    $depth--;
                    if ( 0 === $depth ) {
                        $gt  = strpos( $content, '>', $at );
                        $end = ( false === $gt ) ? $len : $gt + 1;
                        break;
                    }
                } else {
                    $depth++;
                }
                $i = $at + 1;
            }
            if ( false === $end ) { break; }   // unbalanced — stop rather than guess

            $out[] = array(
                'html'   => substr( $content, $start, $end - $start ),
                'offset' => $start,
            );
            $pos = $end;
        }
        return $out;
    }

    /**
     * Work out which component a section is, and what is wrong with it.
     */
    private static function classify( $sec, $cat ) {
        $html = $sec['html'];

        $id      = '';
        $version = 0;
        $exact   = false;

        // Stamped: we know exactly what this is and which markup revision.
        if ( preg_match( '#data-sw-c="([a-z0-9-]+)@(\d+)"#i', $html, $m ) ) {
            $id      = $m[1];
            $version = (int) $m[2];
            $exact   = isset( $cat[ $id ] );
        }

        // Unstamped: work it out from the markup. Usually that lands on
        // one component; where it genuinely cannot (the left and right
        // variants of "obsah + 2 obrázky" differ only in Bootstrap order
        // classes) we name all the candidates rather than pick one.
        $family = array();
        if ( ! $exact ) {
            $hits = self::identify( $html, $cat );
            if ( ! $hits ) { return null; }
            foreach ( $hits as $cid ) { $family[ $cid ] = $cat[ $cid ]['title']; }
        }

        $issues = array();

        if ( $exact ) {
            $current = $cat[ $id ]['version'];
            if ( $version < $current ) {
                $issues[] = sprintf( 'starší markup (@%d, aktuální je @%d)', $version, $current );
            }
        } else {
            $issues[] = 'bez razítka — vloženo před verzí 2.20.0';
        }

        foreach ( self::LEGACY_GRIDS as $g ) {
            if ( false !== strpos( $html, $g ) ) {
                $issues[] = 'stará mřížka .' . $g;
            }
        }
        if ( false !== strpos( $html, 'sw-container' ) ) {
            $issues[] = '.sw-container (nikdy neměl žádné CSS — sekce je přes celou šířku)';
        }
        foreach ( self::JUNK as $j ) {
            if ( false !== strpos( $html, $j ) ) {
                $issues[] = 'zanesené ' . $j;
            }
        }

        return array(
            'id'      => $exact ? $id : '',
            'title'   => $exact ? $cat[ $id ]['title'] : implode( ' / ', $family ),
            'version' => $version,
            'exact'   => $exact,
            'issues'  => $issues,
            'bytes'   => strlen( $html ),
        );
    }

    /**
     * Rules in the stylesheet that nothing on this site uses.
     *
     * "Used" means: a bundled component uses it, OR it appears somewhere
     * in a page's content, OR the runtime adds it. That middle one is the
     * reason this lives here and not in a build script — a class can be
     * used by a LiveCanvas dynamic view or by markup written by hand, and
     * a stylesheet-versus-components comparison would call it dead and be
     * wrong. The news-tile skin is exactly that case.
     *
     * Still only a shortlist to review by eye, never something to delete
     * automatically: a class may belong to a page that was not scanned, a
     * draft, or a site that shares this plugin.
     *
     * @param array<int,string> $in_content Classes found in page content.
     * @return array<int,string>
     */
    public static function unused_classes( $in_content ) {
        $css_file = SWDS_DIR . 'assets/sw-design-system.css';
        if ( ! is_readable( $css_file ) ) {
            return array();
        }

        $css = (string) file_get_contents( $css_file );
        if ( ! preg_match_all( '#\.(sw-[a-z0-9-]+)#', $css, $m ) ) {
            return array();
        }
        $in_css = array_unique( $m[1] );

        $used = array_flip( $in_content );

        // Bundled components.
        foreach ( glob( SWDS_DIR . 'components/*.html' ) as $f ) {
            if ( preg_match_all( '#\bsw-[a-z0-9-]+#', (string) file_get_contents( $f ), $cm ) ) {
                foreach ( $cm[0] as $c ) { $used[ $c ] = true; }
            }
        }
        // Added at runtime, so they never appear in saved markup.
        foreach ( array( 'sw-anim-on', 'sw-rv', 'sw-ds-reveal' ) as $c ) {
            $used[ $c ] = true;
        }

        $out = array();
        foreach ( $in_css as $c ) {
            if ( ! isset( $used[ $c ] ) ) { $out[] = $c; }
        }
        sort( $out );
        return $out;
    }

    /** Drop the cached scan — called after the library is refreshed or the plugin updated. */
    public static function flush() {
        delete_transient( self::TRANSIENT );
    }
}
