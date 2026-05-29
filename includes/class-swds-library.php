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

    // Repo coordinates — adjust if repo/branch changes.
    const REPO   = 'paveltravnicek/sw-design-system';
    const BRANCH = 'main';

    // GitHub API contents endpoint — reacts to changes immediately (ETag-based),
    // unlike raw.githubusercontent.com which is held on a ~5 min CDN cache.
    const API_BASE = 'https://api.github.com/repos/';
    // Raw is the fallback (subject to up-to-5-min CDN delay).
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
     *
     * On force-refresh we fetch BEFORE clearing the cache, so a failed
     * fetch (e.g. GitHub rate limit) doesn't wipe a good cached list.
     */
    public static function components( $force_refresh = false ) {
        if ( $force_refresh ) {
            $remote = self::fetch_remote();
            if ( ! empty( $remote ) ) {
                set_transient( self::TRANSIENT, $remote, self::TTL );
                return $remote;
            }
            // Fetch failed — keep whatever we had; if nothing, fall through to local.
            $cached = get_transient( self::TRANSIENT );
            return ( is_array( $cached ) && ! empty( $cached ) ) ? $cached : self::load_local();
        }

        // Normal load: cached remote first.
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
     * Fetch one path from the repo as a string.
     * Tries the GitHub API contents endpoint first (reacts to commits
     * immediately), then falls back to the raw CDN. Returns string|null.
     */
    private static function fetch_file( $path ) {
        // 1) GitHub API contents — base64 in JSON, ETag-cached (fresh).
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

        // 2) Fallback: raw CDN (may lag up to ~5 min). Cache-bust query param
        //    doesn't help raw (keys by path only), but the request is harmless.
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
     * Fetch the manifest + each component file from GitHub.
     * Returns array of components or empty array on any failure.
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
            $code = self::fetch_file( 'components/' . $file );
            if ( null === $code ) {
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
