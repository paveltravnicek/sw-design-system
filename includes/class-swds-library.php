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
        return array(
            'categories' => array(
                array( 'key' => 'hero',    'label' => 'Hero sekce' ),
                array( 'key' => 'trust',   'label' => 'Prvky důvěryhodnosti' ),
                array( 'key' => 'content', 'label' => 'Obsah' ),
                array( 'key' => 'cta',     'label' => 'Výzvy k akci (CTA)' ),
            ),
            'components' => array(
                array( 'title' => 'Hero (s mockupem)',        'file' => 'component-hero.html',        'category' => 'hero' ),
                array( 'title' => 'Hero (statický obrázek)',  'file' => 'component-hero-static.html', 'category' => 'hero' ),
                array( 'title' => 'Prvky důvěryhodnosti (3)', 'file' => 'component-trust-3col.html',  'category' => 'trust' ),
                array( 'title' => 'Prvky důvěryhodnosti (4)', 'file' => 'component-trust-4col.html',  'category' => 'trust' ),
                array( 'title' => 'Obsah + 2 obrázky',        'file' => 'component-content-2img.html','category' => 'content' ),
                array( 'title' => 'CTA box',                  'file' => 'component-cta.html',         'category' => 'cta' ),
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

        $remote = self::fetch_remote();
        if ( ! empty( $remote ) ) {
            set_transient( self::TRANSIENT, $remote, self::TTL );
            return $remote;
        }

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
}
