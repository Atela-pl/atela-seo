<?php
/**
 * Prosty autoloader dla klas wtyczki.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( function( $class ) {
    // Sprawdzamy czy klasa należy do naszego pluginu
    if ( strpos( $class, 'Atela_SEO_' ) !== 0 ) {
        return;
    }

    $class_name = str_replace( 'Atela_SEO_', '', $class );
    $class_name = strtolower( str_replace( '_', '-', $class_name ) );
    
    // Szukamy w głównym includes
    $file = ALPHA_SEO_DIR . 'includes/class-atela-seo-' . $class_name . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
        return;
    }

    // Szukamy w podfolderach
    $subdirs = array( 'admin', 'frontend', 'integrations', 'social', 'schema', 'sitemaps', 'redirects', 'analysis' );
    foreach ( $subdirs as $dir ) {
        $file = ALPHA_SEO_DIR . 'includes/' . $dir . '/class-atela-seo-' . $class_name . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
});
