<?php
/**
 * Uninstall: remove option and generated files.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'swds_settings' );

// Remove generated CSS file + directory.
$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'sw-ds';
foreach ( array( 'sw-tokens.css', 'index.html' ) as $f ) {
    $path = trailingslashit( $dir ) . $f;
    if ( file_exists( $path ) ) {
        @unlink( $path );
    }
}
if ( is_dir( $dir ) ) {
    @rmdir( $dir );
}
