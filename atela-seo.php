<?php
/**
 * Plugin Name:       Atela SEO
 * Description:       Profesjonalna wtyczka SEO dla WordPress z zaawansowaną integracją z edytorami i analizą treści.
 * Version:           1.2.0
 * Author:            Atela
 * Author URI:        https://atela.pl
 * Text Domain:       atela-seo
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ALPHA_SEO_VERSION', '1.2.0' );
define( 'ALPHA_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALPHA_SEO_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'atela_seo_fs' ) ) {
	// Create a helper function for easy SDK access.
	function atela_seo_fs() {
		global $atela_seo_fs;

		if ( ! isset( $atela_seo_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			$atela_seo_fs = fs_dynamic_init( array(
				'id'                  => '35699',
				'slug'                => 'atela-seo',
				'type'                => 'plugin',
				'public_key'          => 'pk_8db593834fd76dd9135bf82d796a2',
				'is_premium'          => false,
				'has_premium_version' => true,
				'has_addons'          => true,
				'has_paid_plans'      => true,
				'menu'                => array(
					'slug'    => 'atela-seo',
					'support' => false,
				),
			) );
		}

		return $atela_seo_fs;
	}

	// Init Freemius.
	atela_seo_fs();
	// Signal that SDK was initiated.
	do_action( 'atela_seo_fs_loaded' );
}

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
