<?php
/**
 * Plugin Name:       Atela SEO
 * Description:       Profesjonalna wtyczka SEO dla WordPress z zaawansowaną integracją z edytorami i analizą treści.
 * Version:           1.0.0
 * Author:            Atela
 * Author URI:        https://atela.pl
 * Text Domain:       atela-seo
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ALPHA_SEO_VERSION', '1.0.0' );
define( 'ALPHA_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALPHA_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once ALPHA_SEO_DIR . 'includes/class-atela-seo-autoloader.php';

// Inicjalizacja głównej klasy wtyczki.
if ( ! function_exists( 'atela_seo_run' ) ) {
	function atela_seo_run() {
		$plugin = new Atela_SEO_Core();
		$plugin->run();
	}
}
add_action( 'plugins_loaded', 'atela_seo_run' );

/**
 * Globalna funkcja pomocnicza do wyświetlania okruszków w motywie.
 * Użycie: <?php if ( function_exists('atela_seo_breadcrumbs') ) atela_seo_breadcrumbs(); ?>
 */
if ( ! function_exists( 'atela_seo_breadcrumbs' ) ) {
	function atela_seo_breadcrumbs() {
		if ( class_exists( 'Atela_SEO_Breadcrumbs' ) ) {
			echo wp_kses_post( Atela_SEO_Breadcrumbs::render() );
		}
	}
}
