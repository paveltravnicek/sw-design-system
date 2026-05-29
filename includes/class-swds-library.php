<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Library
 *
 * Loads the component library. Two sources, in order:
 *   1. Remote manifest on GitHub (raw JSON) — lets you add components by
 *      committing files to the repo, with NO plugin release.
 *   2. Local bundled components in /components/ — always-present fallback,
 *      used if the remote is unreachable or returns nothing.
 *
 * The remote result is cached in a transient (default 6h) so we don't hit
 * GitHub on every page load. A "Načíst znovu" button clears the cache.
 *
 * Manifest format (components.json in the repo):
 * {
 *   "components": [
 *     { "title": "Hero (s mockupem)", "file": "component-hero.html" },
 *     { "title": "Nová sekce",         "file": "component-nova.html" }
 *   ]
 * }
 * Each "file" is fetched from the same repo path: /components/<file>.
 */
class SWDS_Library {

    const TRANSIENT = 'swds_library_cache';
    const TTL       = 6 * HOUR_IN_SECONDS;

    // Raw GitHub base (branch main). Adjust if repo/branch changes.
    const RAW_BASE      = 'https://raw.githubusercontent.com/paveltravnicek/sw-design-system/main/';
    const MANIFEST_PATH = 'components/components.json';

    /** Local bundled components (fallback + offline). title => filename */
    public static function local() {
        return array(
            'Hero (s mockupem)'        => 'component-hero.html',
            'Hero (statický obrázek)'  => 'component-hero-static.html',
            'Prvky důvěryhodnosti (3)' => 'component-trust-3col.html',
            'Prvky důvěryhodnosti (4)' => 'component-trust-4col.html',
            'Obsah + 2 obrázky'        => 'component-content-2img.html',
            'CTA box'                  => 'component-cta.html',
        );
    }

    /**
     * Return the list of components as array of [title, code, source].
     * source = 'remote' | 'local'.
     */
    public static function components( $force_refresh = false ) {
        if ( $force_refresh ) {
            delete_transient( self::TRANSIENT );
        }

        // Try cached remote first.
        $cached = get_transient( self::TRANSIENT );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $remote = self::fetch_remote();
        if ( ! empty( $remote ) ) {
            set_transient( self::TRANSIENT, $remote, self::TTL );
            return $remote;
        }

        // Fallback: local bundled files.
        return self::load_local();
    }

    /**
     * Fetch the manifest + each component file from GitHub.
     * Returns array of components or empty array on any failure.
     */
    private static function fetch_remote() {
        $res = wp_remote_get(
            self::RAW_BASE . self::MANIFEST_PATH,
            array( 'timeout' => 8 )
        );
        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
            return array();
        }
        $manifest = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( empty( $manifest['components'] ) || ! is_array( $manifest['components'] ) ) {
            return array();
        }

        $out = array();
        foreach ( $manifest['components'] as $item ) {
            if ( empty( $item['file'] ) || empty( $item['title'] ) ) {
                continue;
            }
            $file = ltrim( (string) $item['file'], '/' );
            // Only allow our component filenames (no path traversal).
            if ( ! preg_match( '/^[a-z0-9\-]+\.html$/i', $file ) ) {
                continue;
            }
            $cres = wp_remote_get( self::RAW_BASE . 'components/' . $file, array( 'timeout' => 8 ) );
            if ( is_wp_error( $cres ) || 200 !== wp_remote_retrieve_response_code( $cres ) ) {
                continue;
            }
            $code = wp_remote_retrieve_body( $cres );
            if ( '' === trim( $code ) ) {
                continue;
            }
            $out[] = array(
                'title'  => sanitize_text_field( $item['title'] ),
                'code'   => $code,
                'source' => 'remote',
            );
        }
        return $out;
    }

    /** Load local bundled component files. */
    private static function load_local() {
        $out = array();
        foreach ( self::local() as $title => $file ) {
            $path = SWDS_DIR . 'components/' . $file;
            if ( file_exists( $path ) ) {
                $out[] = array(
                    'title'  => $title,
                    'code'   => file_get_contents( $path ),
                    'source' => 'local',
                );
            }
        }
        return $out;
    }
}
