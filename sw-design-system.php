<?php
/**
 * Plugin Name:       SW Design System
 * Plugin URI:        https://smart-websites.cz/
 * Description:       Konfigurovatelný design systém pro weby Smart Websites. Nastavte barvy, dark mode, tvarosloví, stíny a animace v jednom místě — plugin z toho vygeneruje CSS. Obsahuje knihovnu komponent a nápovědu.
 * Version:           1.1.3
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

define( 'SWDS_VERSION', '1.1.3' );
define( 'SWDS_FILE', __FILE__ );
define( 'SWDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWDS_URL', plugin_dir_url( __FILE__ ) );
define( 'SWDS_OPTION', 'swds_settings' );        // wp_options key
define( 'SWDS_UPLOAD_SUBDIR', 'sw-ds' );          // wp-content/uploads/sw-ds/

require_once SWDS_DIR . 'includes/class-swds-tokens.php';
require_once SWDS_DIR . 'includes/class-swds-presets.php';
require_once SWDS_DIR . 'includes/class-swds-generator.php';
require_once SWDS_DIR . 'includes/class-swds-library.php';
require_once SWDS_DIR . 'includes/class-swds-settings.php';
require_once SWDS_DIR . 'includes/class-swds-frontend.php';

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
