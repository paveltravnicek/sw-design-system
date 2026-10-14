<?php
/**
 * Plugin Name:       SW Design System
 * Plugin URI:        https://smart-websites.cz/
 * Description:       Konfigurovatelný design systém pro weby Smart Websites. Nastavte barvy, dark mode, tvarosloví, stíny a animace v jednom místě — plugin z toho vygeneruje CSS. Obsahuje knihovnu komponent a nápovědu.
 * Version:            2.30.0
 * Author:            Smart Websites
 * Author URI:        https://smart-websites.cz/
 * License:           GPL-2.0-or-later
 * Text Domain:       sw-design-system
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/paveltravnicek/sw-design-system/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

/**
 * GitHub self-update via plugin-update-checker.
 * The library folder is shipped in the production zip on GitHub.
 */
if ( file_exists( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
    require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
    if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
        $swdsUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/paveltravnicek/sw-design-system/',
            __FILE__,
            'sw-design-system'
        );
        $swdsUpdateChecker->setBranch( 'main' );
        $swdsUpdateChecker->getVcsApi()->enableReleaseAssets( '/\.zip$/i' );
    }
}

define( 'SWDS_VERSION', '2.30.0' );
define( 'SWDS_FILE', __FILE__ );
define( 'SWDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWDS_URL', plugin_dir_url( __FILE__ ) );
define( 'SWDS_OPTION', 'swds_settings' );        // wp_options key
define( 'SWDS_UPLOAD_SUBDIR', 'sw-ds' );          // wp-content/uploads/sw-ds/

/**
 * Lazy class loading.
 *
 * A normal front-end request only ever touches SWDS_Frontend and a few
 * cheap SWDS_Generator methods (exists/url/version). The remaining
 * classes — tokens, presets, the component library and the settings
 * screen — are admin-only, together roughly 60 kB of PHP that used to be
 * require_once'd on every single page load for nothing.
 *
 * An autoloader is used rather than wrapping the requires in is_admin(),
 * because some paths legitimately need an "admin" class outside the admin
 * screen (SWDS_Generator::generate() needs SWDS_Tokens for its defaults,
 * and that can run on any request right after a version bump). This way
 * each class loads exactly when first referenced, with no call site left
 * to overlook.
 */
spl_autoload_register( function ( $class ) {
    static $map = array(
        'SWDS_Tokens'    => 'class-swds-tokens.php',
        'SWDS_Presets'   => 'class-swds-presets.php',
        'SWDS_Generator' => 'class-swds-generator.php',
        'SWDS_Library'   => 'class-swds-library.php',
        'SWDS_Settings'  => 'class-swds-settings.php',
        'SWDS_Frontend'  => 'class-swds-frontend.php',
        'SWDS_Usage'     => 'class-swds-usage.php',
    );
    if ( isset( $map[ $class ] ) ) {
        require_once SWDS_DIR . 'includes/' . $map[ $class ];
    }
} );

/**
 * Boot the plugin.
 */
function swds_init() {
    // Admin settings screen (under Appearance, admins only — capability enforced inside).
    if ( is_admin() ) {
        ( new SWDS_Settings() )->hooks();
    }
    // Frontend asset enqueue (always).
    ( new SWDS_Frontend() )->hooks();

    // Auto-regenerate the tokens CSS after a plugin update, so new defaults
    // (spacing, reveal vars, etc.) take effect without a manual "Save".
    swds_maybe_regenerate();
}
add_action( 'plugins_loaded', 'swds_init' );

/**
 * Regenerate the generated CSS if the stored version differs from the current
 * plugin version, or if the file is missing. Cheap: runs the check every load,
 * regenerates only when needed.
 */
function swds_maybe_regenerate() {
    $stored_ver = get_option( 'swds_version' );
    if ( $stored_ver === SWDS_VERSION && SWDS_Generator::exists() ) {
        return; // up to date
    }
    SWDS_Generator::generate( get_option( SWDS_OPTION ) );
    // The converter's lc-block/editable map is derived from the bundled
    // component files, so a new version can mean new slots to learn.
    delete_transient( 'swds_lc_hints' );
    delete_transient( 'swds_usage_scan' );   // component versions may have moved
    // The component library is cached for 6 hours. Without this, a freshly
    // installed version kept showing the previous one's components until the
    // cache happened to expire — the update looked like it had done nothing.
    delete_transient( 'swds_library_cache' );
    delete_transient( 'swds_library_cache_cats' );
    update_option( 'swds_version', SWDS_VERSION );
}

/**
 * On activation: store defaults if absent, then generate the CSS file once.
 */
function swds_activate() {
    if ( false === get_option( SWDS_OPTION ) ) {
        add_option( SWDS_OPTION, SWDS_Tokens::defaults() );
    }
    SWDS_Generator::generate( get_option( SWDS_OPTION ) );
    update_option( 'swds_version', SWDS_VERSION );
}
register_activation_hook( __FILE__, 'swds_activate' );

/**
 * On deactivation: nothing destructive (keep settings & file).
 * Real cleanup happens in uninstall.php.
 */
