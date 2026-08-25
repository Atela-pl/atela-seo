<?php
/**
 * Plugin Name:       Atela SEO
 * Description:       Professional SEO plugin with Elementor support, live previews, XML sitemaps, redirects, Schema.org, and automatic social media image letterboxing.
 * Version:           1.2.1
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

define( 'ALPHA_SEO_VERSION', '1.2.1' );
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
				'has_premium_version' => false,
				'has_addons'          => true,
				'has_paid_plans'      => false,
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

/**
 * Baner "Połącz z Freemius", gdy witryna utknęła w trybie anonimowym.
 *
 * Stan opt-inu (połączona / anonimowa / pominięta) Freemius trzyma w wp_options,
 * niezależnie od plików wtyczki - ponowne wgranie tych samych plików niczego nie
 * resetuje. Gdy witryna raz trafi w tryb anonimowy (np. kliknięte "Skip" przy
 * jednej z wcześniejszych, testowych instalacji), is_site_activation_mode() w SDK
 * przestaje uznawać ją za "w trakcie aktywacji" i przestaje pokazywać opt-in w
 * ogóle - potwierdzone na żywo. get_reconnect_url() jawnie resetuje ten stan.
 */
function atela_seo_reconnect_notice() {
	$fs = function_exists( 'atela_seo_fs' ) ? atela_seo_fs() : null;
	// Pokazujemy zawsze, gdy site nie jest połączony - nie tylko w potwierdzonym
	// trybie anonimowym (is_anonymous()). "Nigdy nie podjęto decyzji" i "kiedyś
	// kliknięto Skip" to różne stany wewnętrzne SDK, a użytkownik zgłosił brak
	// baneru mimo świeżej, nigdy wcześniej nie łączonej instalacji - węższy
	// warunek (tylko is_anonymous()) najwyraźniej nie łapał tego przypadku.
	if ( ! $fs || $fs->is_registered() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || false === strpos( (string) $screen->id, 'atela-seo' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'Atela SEO nie jest połączone z Freemius (potrzebne do licencji, aktualizacji i dodatków Premium).', 'atela-seo' ); ?>
			<a href="<?php echo esc_url( $fs->get_reconnect_url() ); ?>" class="button button-primary" style="margin-left:8px;"><?php esc_html_e( 'Połącz z Freemius', 'atela-seo' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'atela_seo_reconnect_notice' );

/**
 * Ochrona przed wyłączeniem darmowej wersji, gdy aktywne jest Premium.
 *
 * Premium ładuje SDK Freemius z folderu tej wtyczki (dirname(dirname(__FILE__))
 * . '/atela-seo/freemius/start.php') - to zwykły require_once po fizycznej
 * ścieżce, więc działa niezależnie od tego, czy ta wtyczka jest "aktywna" w
 * WordPressie, dopóki pliki fizycznie tu leżą. Realny problem to reszta stanu:
 * darmowa wtyczka w ogóle się nie ładuje (plugins_loaded nigdy nie odpala
 * atela-seo.php), więc jej instancja Freemius (moduł-rodzic 35699) nigdy nie
 * powstaje w danym żądaniu - a Premium (dodatek 35700) zakłada, że rodzic
 * już istnieje. Stąd krytyczny błąd po wyłączeniu darmowej wersji przy
 * aktywnym Premium - potwierdzone na żywo. Blokada lustrzana do tej w
 * atela-seo-premium.php (atela_seo_premium_check_dependency): szarzy link
 * "Wyłącz" na liście wtyczek i twardo blokuje próbę wyłączenia.
 */
function atela_seo_is_premium_active() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active( 'atela-seo-premium/atela-seo-premium.php' );
}

function atela_seo_action_links( $actions ) {
	if ( atela_seo_is_premium_active() && isset( $actions['deactivate'] ) ) {
		$actions['deactivate'] = '<span style="color:#a0a5aa; cursor:not-allowed;" title="' . esc_attr__( 'Wymaga wcześniejszego wyłączenia Atela SEO Premium', 'atela-seo' ) . '">' . esc_html__( 'Wyłącz (wymaga wyłączenia Premium)', 'atela-seo' ) . '</span>';
	}
	return $actions;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'atela_seo_action_links' );

function atela_seo_block_deactivation_with_premium() {
	if ( ! atela_seo_is_premium_active() ) {
		return;
	}

	$this_plugin = plugin_basename( __FILE__ );

	// Read-only guard that blocks before the real (nonce-checked) deactivation
	// runs - no state is changed here, so there is nothing a forged request
	// could do beyond what the genuine nonce-verified handler already re-checks.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_single_deactivate = (
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		isset( $_GET['action'], $_GET['plugin'] ) &&
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'deactivate' === $_GET['action'] &&
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this_plugin === $_GET['plugin']
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_bulk_deactivate = (
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		isset( $_REQUEST['action'] ) &&
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'deactivate-selected' === $_REQUEST['action'] &&
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		! empty( $_REQUEST['checked'] ) &&
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		in_array( $this_plugin, (array) $_REQUEST['checked'], true )
	);

	if ( $is_single_deactivate || $is_bulk_deactivate ) {
		wp_die(
			'<strong>Atela SEO</strong> nie może zostać wyłączone, dopóki aktywna jest <strong>Atela SEO Premium</strong> (Premium wymaga tej wtyczki do działania). Najpierw wyłącz Atela SEO Premium. <br><br><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">&laquo; Wróć do listy wtyczek</a>'
		);
	}
}
add_action( 'admin_init', 'atela_seo_block_deactivation_with_premium' );
