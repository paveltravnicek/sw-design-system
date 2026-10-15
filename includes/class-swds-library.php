<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Library
 *
 * Loads the component library, grouped into categories. Two sources:
 *   1. Remote manifest on GitHub (via API, with raw fallback) — add or
 *      recategorize components by committing to the repo, NO plugin release.
 *   2. Local bundled components — always-present fallback / offline.
 *
 * Result cached in a transient (6h); "Načíst z GitHubu" forces a refresh.
 *
 * Manifest format (components/components.json):
 * {
 *   "categories": [
 *     { "key": "hero",    "label": "Hero sekce" },
 *     { "key": "trust",   "label": "Důvěryhodnost" }
 *   ],
 *   "components": [
 *     { "title": "Hero (s mockupem)", "file": "component-hero.html", "category": "hero" }
 *   ]
 * }
 * - "category" on a component references a category "key".
 * - Components with an unknown/empty category land in "Ostatní".
 * - Category order in the UI follows the "categories" array order.
 */
class SWDS_Library {

    const TRANSIENT = 'swds_library_cache';
    const TTL       = 6 * HOUR_IN_SECONDS;

    /** Cache for the lc-block / editable map derived from the bundled components. */
    const HINTS_TRANSIENT = 'swds_lc_hints';

    // Repo coordinates — adjust if repo/branch changes.
    const REPO   = 'paveltravnicek/sw-design-system';
    const BRANCH = 'main';

    const API_BASE     = 'https://api.github.com/repos/';
    const RAW_BASE     = 'https://raw.githubusercontent.com/paveltravnicek/sw-design-system/main/';
    const MANIFEST_PATH = 'components/components.json';

    /** Fallback key/label for anything without a valid category. */
    const OTHER_KEY   = 'other';
    const OTHER_LABEL = 'Ostatní';

    /**
     * Local bundled definition (fallback + offline).
     * Mirrors the manifest structure: categories + components.
     */
    public static function local_definition() {
        /* components.json is the manifest that ships with the plugin and
           the one every other part of this file already reads. Keeping a
           second copy of it written out in PHP meant two sources of truth,
           and the PHP one quietly fell behind: components added to the
           JSON — the header, the compact reviews — never appeared in the
           library, never got a catalogue entry, and so the converter could
           not stamp them and the usage screen could not report them.
           Read the manifest; the array below is only the parachute for a
           missing or corrupt file. */
        $manifest = SWDS_DIR . self::MANIFEST_PATH;
        if ( is_readable( $manifest ) ) {
            $json = json_decode( (string) file_get_contents( $manifest ), true );
            if ( is_array( $json ) && ! empty( $json['components'] ) ) {
                return $json;
            }
        }

        return array(
            'categories' => array(
                array( 'key' => 'hero',          'label' => 'Hero sekce' ),
                array( 'key' => 'trust',         'label' => 'Prvky důvěryhodnosti' ),
                array( 'key' => 'content',       'label' => 'Obsah' ),
                array( 'key' => 'process',       'label' => 'Postup / proces' ),
                array( 'key' => 'hub',           'label' => 'Rozcestník služeb' ),
                array( 'key' => 'cta',           'label' => 'Výzvy k akci (CTA)' ),
                array( 'key' => 'faq',           'label' => 'Časté dotazy (FAQ)' ),
                array( 'key' => 'contact',       'label' => 'Kontakt' ),
                array( 'key' => 'pricing',       'label' => 'Ceníky' ),
                array( 'key' => 'testimonials',  'label' => 'Reference' ),
                array( 'key' => 'news',          'label' => 'Aktuality / blog' ),
                array( 'key' => 'footer',        'label' => 'Zápatí' ),
            ),
            'components' => array(
                array( 'title' => 'Hero s mockupem',                             'file' => 'component-hero.html',                     'category' => 'hero' ),
                array( 'title' => 'Hero se statickým obrázkem',                  'file' => 'component-hero-static.html',              'category' => 'hero' ),
                array( 'title' => 'Hero s mini galerií',                         'file' => 'component-hero-gallery.html',             'category' => 'hero' ),
                array( 'title' => 'Hero jen s textem',                           'file' => 'component-hero-text.html',                'category' => 'hero' ),
                array( 'title' => '3 prvky důvěryhodnosti',                      'file' => 'component-trust-3col.html',               'category' => 'trust' ),
                array( 'title' => '4 prvky důvěryhodnosti',                      'file' => 'component-trust-mini-4col.html',          'category' => 'trust' ),
                array( 'title' => 'Obsah a 2 obrázky vlevo',                     'file' => 'component-content-2img-left.html',        'category' => 'content' ),
                array( 'title' => 'Obsah a 2 obrázky vpravo',                    'file' => 'component-content-2img-right.html',       'category' => 'content' ),
                array( 'title' => 'Postup spolupráce',                           'file' => 'component-journey.html',                  'category' => 'process' ),
                array( 'title' => 'Seznam kroků s čísly',                        'file' => 'component-process-steps.html',            'category' => 'process' ),
                array( 'title' => 'Seznam kroků s ikonami',                      'file' => 'component-process-steps-icons.html',      'category' => 'process' ),
                array( 'title' => 'Rozcestník služeb',                           'file' => 'component-service-hub.html',              'category' => 'hub' ),
                array( 'title' => 'CTA box',                                     'file' => 'component-cta.html',                      'category' => 'cta' ),
                array( 'title' => 'Časté dotazy',                                'file' => 'component-faq.html',                      'category' => 'faq' ),
                array( 'title' => 'Kontaktní sekce',                             'file' => 'component-contact.html',                  'category' => 'contact' ),
                array( 'title' => '3sloupcová tabulka s cenami',                 'file' => 'component-pricing-3col.html',             'category' => 'pricing' ),
                array( 'title' => '4sloupcová tabulka s cenami',                 'file' => 'component-pricing-4col.html',             'category' => 'pricing' ),
                array( 'title' => 'Reference',                                   'file' => 'component-testimonials.html',             'category' => 'testimonials' ),
                array( 'title' => 'Kompletní reference',                         'file' => 'component-references-full.html',          'category' => 'testimonials' ),
                array( 'title' => 'Aktuality (dynamický výpis příspěvků)',       'file' => 'component-news.html',                     'category' => 'news' ),
                array( 'title' => 'Zápatí webu',                                 'file' => 'component-footer.html',                   'category' => 'footer' ),
            ),
        );
    }

    /**
     * Return components grouped by category, ready for the UI:
     * array of [ 'key', 'label', 'items' => [ [title, code, source], ... ] ]
     * Only non-empty categories are returned, in defined order, "Ostatní" last.
     */
    public static function grouped( $force_refresh = false ) {
        $flat = self::components( $force_refresh );

        // Determine category order + labels from the active definition.
        $def        = self::active_definition();
        $cat_order  = array();
        $cat_labels = array();
        foreach ( $def['categories'] as $c ) {
            if ( ! empty( $c['key'] ) ) {
                $cat_order[]            = $c['key'];
                $cat_labels[ $c['key'] ] = isset( $c['label'] ) ? $c['label'] : $c['key'];
            }
        }
        $cat_order[]                      = self::OTHER_KEY;
        $cat_labels[ self::OTHER_KEY ]    = self::OTHER_LABEL;

        // Bucket components.
        $buckets = array();
        foreach ( $flat as $comp ) {
            $cat = ! empty( $comp['category'] ) && isset( $cat_labels[ $comp['category'] ] )
                ? $comp['category']
                : self::OTHER_KEY;
            $buckets[ $cat ][] = $comp;
        }

        // Emit in order, skipping empties.
        $out = array();
        foreach ( $cat_order as $key ) {
            if ( empty( $buckets[ $key ] ) ) {
                continue;
            }
            $out[] = array(
                'key'   => $key,
                'label' => $cat_labels[ $key ],
                'items' => $buckets[ $key ],
            );
        }
        return $out;
    }

    /**
     * Flat list of components: [ title, code, source, category ].
     * source = 'remote' | 'local'.
     */
    public static function components( $force_refresh = false ) {
        if ( $force_refresh ) {
            $remote = self::fetch_remote();
            if ( ! empty( $remote ) ) {
                set_transient( self::TRANSIENT, $remote, self::TTL );
                return $remote;
            }
            $cached = get_transient( self::TRANSIENT );
            return ( is_array( $cached ) && ! empty( $cached ) ) ? $cached : self::load_local();
        }

        $cached = get_transient( self::TRANSIENT );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        /* The files bundled in this plugin version win by default.
         *
         * This used to fetch GitHub here and only fall back to the local
         * files when the network failed. That was backwards: installing a
         * new plugin zip put new component files on disk, and the library
         * went on serving whatever was on the GitHub branch — so a fixed
         * component kept coming out of the library broken, with nothing in
         * the UI to suggest why.
         *
         * Pulling from GitHub is still available, but only when it is
         * actually asked for: the "Načíst z GitHubu" button passes
         * $force_refresh and takes the branch above. */
        return self::load_local();
    }

    /**
     * The category definition currently in effect. Prefer the remote
     * manifest's categories (cached alongside components); fall back to local.
     */
    private static function active_definition() {
        $cats = get_transient( self::TRANSIENT . '_cats' );
        if ( is_array( $cats ) && ! empty( $cats ) ) {
            return array( 'categories' => $cats );
        }
        return self::local_definition();
    }

    /**
     * Fetch one path from the repo as a string (API first, raw fallback).
     */
    private static function fetch_file( $path ) {
        $api_url = self::API_BASE . self::REPO . '/contents/' . $path . '?ref=' . self::BRANCH;
        $res = wp_remote_get( $api_url, array(
            'timeout' => 8,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'SW-Design-System',
            ),
        ) );
        if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
            $json = json_decode( wp_remote_retrieve_body( $res ), true );
            if ( ! empty( $json['content'] ) ) {
                $decoded = base64_decode( str_replace( "\n", '', $json['content'] ), true );
                if ( false !== $decoded && '' !== trim( $decoded ) ) {
                    return $decoded;
                }
            }
        }

        $raw = wp_remote_get( self::RAW_BASE . $path, array(
            'timeout' => 8,
            'headers' => array( 'User-Agent' => 'SW-Design-System' ),
        ) );
        if ( ! is_wp_error( $raw ) && 200 === wp_remote_retrieve_response_code( $raw ) ) {
            $body = wp_remote_retrieve_body( $raw );
            if ( '' !== trim( $body ) ) {
                return $body;
            }
        }

        return null;
    }

    /**
     * Fetch manifest + component files from GitHub. Also caches the category
     * definition. Returns flat component array or empty on failure.
     */
    private static function fetch_remote() {
        $manifest_raw = self::fetch_file( self::MANIFEST_PATH );
        if ( null === $manifest_raw ) {
            return array();
        }
        $manifest = json_decode( $manifest_raw, true );
        if ( empty( $manifest['components'] ) || ! is_array( $manifest['components'] ) ) {
            return array();
        }

        // Cache category definition (sanitized) for grouping.
        if ( ! empty( $manifest['categories'] ) && is_array( $manifest['categories'] ) ) {
            $cats = array();
            foreach ( $manifest['categories'] as $c ) {
                if ( empty( $c['key'] ) ) { continue; }
                $cats[] = array(
                    'key'   => sanitize_key( $c['key'] ),
                    'label' => isset( $c['label'] ) ? sanitize_text_field( $c['label'] ) : $c['key'],
                );
            }
            if ( ! empty( $cats ) ) {
                set_transient( self::TRANSIENT . '_cats', $cats, self::TTL );
            }
        }

        $out = array();
        foreach ( $manifest['components'] as $item ) {
            if ( empty( $item['file'] ) || empty( $item['title'] ) ) {
                continue;
            }
            $file = ltrim( (string) $item['file'], '/' );
            if ( ! preg_match( '/^[a-z0-9\-]+\.html$/i', $file ) ) {
                continue;
            }
            $code = self::fetch_file( 'components/' . $file );
            if ( null === $code ) {
                continue;
            }
            $out[] = array(
                'title'    => sanitize_text_field( $item['title'] ),
                'code'     => $code,
                'source'   => 'remote',
                'category' => ! empty( $item['category'] ) ? sanitize_key( $item['category'] ) : self::OTHER_KEY,
            );
        }
        return $out;
    }

    /** Load local bundled component files (with categories). */
    private static function load_local() {
        $def = self::local_definition();
        $out = array();
        foreach ( $def['components'] as $item ) {
            $path = SWDS_DIR . 'components/' . $item['file'];
            if ( file_exists( $path ) ) {
                $out[] = array(
                    'title'    => $item['title'],
                    'code'     => file_get_contents( $path ),
                    'source'   => 'local',
                    'category' => ! empty( $item['category'] ) ? $item['category'] : self::OTHER_KEY,
                );
            }
        }
        return $out;
    }

    /**
     * Which classes carry lc-block, and which carry editable="inline|rich".
     *
     * Read out of the bundled component files rather than written down by
     * hand, so the converter in the admin keeps matching the components as
     * they change. A hand-kept list would silently rot the first time a
     * component gained a new editable slot.
     *
     * A class only counts as a rule when it appears on the same kind of
     * element across the library — .sw-reveal-item sits on almost every
     * card, so it says nothing about that card being a block, and a bare
     * tag name (p, span, h2) is far too broad to act on.
     *
     * Returns array( 'block' => array<string>, 'editable' => array<class,mode> ).
     */
    public static function lc_hints() {
        $cached = get_transient( self::HINTS_TRANSIENT );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $skip  = array( 'sw-reveal-item', 'sw-anim-on', 'sw-rv' );
        $block = array();
        $edit  = array();

        foreach ( glob( SWDS_DIR . 'components/*.html' ) as $file ) {
            $html = file_get_contents( $file );
            if ( ! $html ) { continue; }

            if ( ! preg_match_all( '#<([a-z0-9]+)\b([^>]*)>#i', $html, $tags, PREG_SET_ORDER ) ) {
                continue;
            }

            foreach ( $tags as $tag ) {
                $attrs = $tag[2];

                if ( ! preg_match( '#class="([^"]*)"#', $attrs, $cm ) ) { continue; }
                $classes = preg_split( '#\s+#', trim( $cm[1] ), -1, PREG_SPLIT_NO_EMPTY );

                $sw = array();
                foreach ( $classes as $c ) {
                    if ( 0 === strpos( $c, 'sw-' ) && ! in_array( $c, $skip, true ) ) {
                        $sw[] = $c;
                    }
                }
                if ( ! $sw ) { continue; }

                if ( in_array( 'lc-block', $classes, true ) ) {
                    // The first sw- class is the component's own name for
                    // this element; the rest are modifiers of it.
                    $block[ $sw[0] ] = isset( $block[ $sw[0] ] ) ? $block[ $sw[0] ] + 1 : 1;
                }

                if ( preg_match( '#editable="(inline|rich)"#', $attrs, $em ) ) {
                    $edit[ $sw[0] ] = $em[1];
                }
            }
        }

        arsort( $block );
        $out = array(
            'block'    => array_keys( $block ),
            'editable' => $edit,
            'reveal'   => self::reveal_map(),
        );

        set_transient( self::HINTS_TRANSIENT, $out, self::TTL );
        return $out;
    }

    /**
     * Which containers carry data-sw-reveal, and which of their children
     * carry .sw-reveal-item — read out of the components, like every
     * other converter rule.
     *
     * A container class only becomes a rule when EVERY occurrence of it
     * across the whole library carries the attribute. .sw-testimonials-wall
     * is the reason: the compact reviews use it twice, once revealed and
     * once — inside the <details> — deliberately not, because an element
     * that starts out display:none is never seen by the observer and would
     * stay invisible. Wiring that one up automatically would hide content
     * for good, so an ambiguous class is left alone and reported instead.
     *
     * @return array<string,array{items:array<string>,ambiguous:bool}>
     */
    private static function reveal_map() {
        $seen = array();   // class => array( revealed count, total count, items )

        foreach ( glob( SWDS_DIR . 'components/*.html' ) as $file ) {
            $html = file_get_contents( $file );
            if ( ! $html ) { continue; }

            $doc = new DOMDocument();
            libxml_use_internal_errors( true );
            $doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
            libxml_clear_errors();

            $xp = new DOMXPath( $doc );
            foreach ( $xp->query( '//*[@class]' ) as $el ) {
                $cls = preg_split( '#\s+#', trim( $el->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
                $own = '';
                foreach ( $cls as $c ) {
                    if ( 0 === strpos( $c, 'sw-' ) && 'sw-reveal-item' !== $c ) { $own = $c; break; }
                }
                if ( ! $own ) { continue; }

                // Does this element hold reveal items as direct children?
                $items = array();
                foreach ( $el->childNodes as $kid ) {
                    if ( XML_ELEMENT_NODE !== $kid->nodeType || ! $kid->hasAttribute( 'class' ) ) { continue; }
                    $kc = preg_split( '#\s+#', trim( $kid->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
                    if ( ! in_array( 'sw-reveal-item', $kc, true ) ) { continue; }
                    foreach ( $kc as $c ) {
                        if ( 0 === strpos( $c, 'sw-' ) && 'sw-reveal-item' !== $c ) { $items[] = $c; break; }
                    }
                }
                /* Count EVERY occurrence of the class, including ones with
                   no reveal items at all — that is exactly the case the
                   ambiguity check exists for. Skipping them here was the
                   first version's mistake: the second .sw-testimonials-wall
                   holds no items, so it never got counted and the class came
                   out looking unanimous. */
                if ( ! isset( $seen[ $own ] ) ) {
                    $seen[ $own ] = array( 'revealed' => 0, 'total' => 0, 'items' => array(), 'bare_in_details' => 0 );
                }
                $seen[ $own ]['total']++;
                if ( $el->hasAttribute( 'data-sw-reveal' ) ) {
                    $seen[ $own ]['revealed']++;
                    $seen[ $own ]['items'] = array_values( array_unique( array_merge( $seen[ $own ]['items'], $items ) ) );
                } else {
                    /* Where a class is revealed in one place and not in
                       another, the library's own reason is usually that the
                       bare one sits inside a <details>: closed, it is
                       display:none, the observer never sees it, and an item
                       wired up there would stay invisible. When that holds
                       for every bare occurrence, the exception is not
                       ambiguity, it is the rule — and the converter can
                       apply it safely. */
                    $n = $el->parentNode;
                    $inside = false;
                    while ( $n && XML_ELEMENT_NODE === $n->nodeType ) {
                        if ( 'details' === strtolower( $n->nodeName ) ) { $inside = true; break; }
                        $n = $n->parentNode;
                    }
                    if ( $inside ) { $seen[ $own ]['bare_in_details']++; }
                }
            }
        }

        $out = array();
        foreach ( $seen as $cls => $d ) {
            if ( 0 === $d['revealed'] ) { continue; }   // never a reveal container
            $bare = $d['total'] - $d['revealed'];
            $out[ $cls ] = array(
                'items'      => $d['items'],
                // Bare occurrences that are all inside a <details> are the
                // documented exception, not a contradiction.
                'skipDetails' => ( $bare > 0 && $bare === $d['bare_in_details'] ),
                'ambiguous'  => ( $bare > 0 && $bare !== $d['bare_in_details'] ),
            );
        }
        return $out;
    }
}
