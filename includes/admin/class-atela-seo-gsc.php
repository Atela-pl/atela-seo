<?php
/**
 * Moduł Atela GSC - integracja z Google Search Console przez Google Site Kit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_GSC {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_gsc_menu' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_gsc_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Zwraca true jeśli Google Site Kit jest aktywny.
	 *
	 * @return bool
	 */
	private function is_site_kit_active() {
		return is_plugin_active( 'google-site-kit/google-site-kit.php' );
	}

	/**
	 * Dodaje stronę "Atela GSC" do menu Atela SEO.
	 */
	public function add_gsc_menu() {
		add_submenu_page(
			'atela-seo',
			__( 'Dashboard', 'atela-seo' ),
			__( '📊 Dashboard', 'atela-seo' ),
			'manage_options',
			'atela-seo-dashboard',
			array( $this, 'render_gsc_page' )
		);
	}

	/**
	 * Renderuje stronę główną raportu GSC w WP Admin.
	 */
	public function render_gsc_page() {
		?>
		<div class="wrap" style="background:#f1f5f9; padding:20px; border-radius:8px; margin-top:20px; max-width:1200px;">
			<h1 style="font-size:24px; font-weight:600; margin-bottom:20px;">📊 <?php esc_html_e( 'SEO Dashboard', 'atela-seo' ); ?></h1>
			<p style="max-width:800px; color:#555; line-height:1.6;">
				<?php esc_html_e( 'This module fetches data from your Google Search Console account, using authorization from the', 'atela-seo' ); ?> <strong>Google Site Kit</strong> <?php esc_html_e( 'plugin.', 'atela-seo' ); ?>
			</p>

			<?php if ( ! $this->is_site_kit_active() ) : ?>
				<div class="notice notice-error" style="max-width:800px;">
					<p><strong><?php esc_html_e( 'Note:', 'atela-seo' ); ?></strong> <?php esc_html_e( 'The Google Site Kit by Google plugin must be installed and active for this module to work correctly.', 'atela-seo' ); ?> <br><a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=search&type=term&s=Google+Site+Kit' ) ); ?>" class="button button-primary" style="margin-top:10px;"><?php esc_html_e( 'Install Google Site Kit', 'atela-seo' ); ?></a></p>
				</div>
			<?php else : ?>
				<div class="notice notice-info" style="max-width:800px; border-left-color:#34a853;">
					<p>💡 <?php esc_html_e( 'Successfully connected with the Google Site Kit plugin. Displaying statistics from the last 28 days for the entire domain.', 'atela-seo' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Kontener dla JS (Raport ogólny) -->
			<div id="atela-gsc-global-dashboard" style="margin-top: 20px;">
				<div id="atela-gsc-global-dates" style="display:none;"></div> <!-- Wykres dat (ukryty dla czystszego widoku) -->
				<div id="atela-gsc-global-queries"></div>
			</div>

			<!-- Dashboard: Ocena SEO -->
			<?php $this->render_seo_score_widget(); ?>
		</div>
		<?php
	}

	/**
	 * Renderuje widżet Oceny SEO
	 */
	private function render_seo_score_widget() {
		global $wpdb;

		// Pobieranie wybranego typu postu (domyślnie 'post')
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pt = isset( $_GET['atela_pt'] ) && $_GET['atela_pt'] === 'page' ? 'page' : 'post';

		// Pobranie statystyk z bazy dla wybranego typu postu
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$good = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s AND p.post_status = 'publish'
			AND pm.meta_key = '_atela_seo_score_status' AND pm.meta_value = 'good'
		", $pt ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s AND p.post_status = 'publish'
			AND pm.meta_key = '_atela_seo_score_status' AND pm.meta_value = 'ok'
		", $pt ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$poor = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s AND p.post_status = 'publish'
			AND pm.meta_key = '_atela_seo_score_status' AND pm.meta_value = 'poor'
		", $pt ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $pt ) );
		$analyzed = $good + $ok + $poor;
		$none = max( 0, $total_posts - $analyzed );

		// Renderowanie UI wzorowanego na screenshot (Ocena SEO)
		?>
		<div style="background:#fff; border:1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); padding: 24px; margin-top: 20px; max-width: 600px; display: inline-block; vertical-align: top;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
				<h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'SEO Score', 'atela-seo' ); ?></h3>
				<select onchange="window.location.href='?page=atela-seo-dashboard&atela_pt='+this.value" style="padding:4px 8px; font-size:13px; border-radius:4px; border:1px solid #cbd5e1; color:#334155;">
					<?php
					$public_pts = get_post_types( array( 'public' => true ), 'objects' );
					unset( $public_pts['attachment'] );
					foreach ( $public_pts as $pt_obj ) {
						$is_selected = selected( $pt, $pt_obj->name, false );
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo '<option value="' . esc_attr( $pt_obj->name ) . '" ' . $is_selected . '>' . esc_html( $pt_obj->labels->name ) . '</option>';
					}
					?>
				</select>
			</div>

			<p style="color:#64748b; font-size:13px; line-height:1.5; margin-bottom:24px;">
				<?php esc_html_e( 'Some content has not been analyzed yet. Open them in the editor, ensure the focus keyword is entered, and save it so we can start the analysis.', 'atela-seo' ); ?>
			</p>

			<div style="display:flex; justify-content: space-between; align-items: center;">
				<!-- Lista z lewej -->
				<div style="flex:1;">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $pt . '&atela_seo_filter=good' ) ); ?>" style="display:flex; align-items:center; margin-bottom:12px; text-decoration:none; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
						<div style="width:12px; height:12px; border-radius:50%; background:#22c55e; margin-right:10px;"></div>
						<span style="width:120px; color:#334155;"><?php esc_html_e( 'Good', 'atela-seo' ); ?></span>
						<span style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; color:#475569;">
							<?php echo esc_html( $good ); ?>
						</span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $pt . '&atela_seo_filter=ok' ) ); ?>" style="display:flex; align-items:center; margin-bottom:12px; text-decoration:none; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
						<div style="width:12px; height:12px; border-radius:50%; background:#f97316; margin-right:10px;"></div>
						<span style="width:120px; color:#334155;"><?php esc_html_e( 'OK', 'atela-seo' ); ?></span>
						<span style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; color:#475569;">
							<?php echo esc_html( $ok ); ?>
						</span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $pt . '&atela_seo_filter=poor' ) ); ?>" style="display:flex; align-items:center; margin-bottom:12px; text-decoration:none; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
						<div style="width:12px; height:12px; border-radius:50%; background:#ef4444; margin-right:10px;"></div>
						<span style="width:120px; color:#334155;"><?php esc_html_e( 'Needs improvement', 'atela-seo' ); ?></span>
						<span style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; color:#475569;">
							<?php echo esc_html( $poor ); ?>
						</span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $pt . '&atela_seo_filter=none' ) ); ?>" style="display:flex; align-items:center; margin-bottom:12px; text-decoration:none; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
						<div style="width:12px; height:12px; border-radius:50%; background:#cbd5e1; margin-right:10px;"></div>
						<span style="width:120px; color:#334155;"><?php esc_html_e( 'Not analyzed', 'atela-seo' ); ?></span>
						<span style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; color:#475569;">
							<?php echo esc_html( $none ); ?>
						</span>
					</a>
				</div>

				<!-- Donut Chart -->
				<div style="width: 150px; height: 150px; position:relative;">
					<?php
					$total = $total_posts > 0 ? $total_posts : 1;
					$deg_good = ($good / $total) * 360;
					$deg_ok = ($ok / $total) * 360;
					$deg_poor = ($poor / $total) * 360;
					$deg_none = ($none / $total) * 360;

					// CSS Conic Gradient for the Donut Chart
					$gradient = "conic-gradient(
						#22c55e 0deg {$deg_good}deg,
						#f97316 {$deg_good}deg " . ($deg_good + $deg_ok) . "deg,
						#ef4444 " . ($deg_good + $deg_ok) . "deg " . ($deg_good + $deg_ok + $deg_poor) . "deg,
						#cbd5e1 " . ($deg_good + $deg_ok + $deg_poor) . "deg 360deg
					)";
					?>
					<div style="width: 100%; height: 100%; border-radius: 50%; background: <?php echo esc_attr( $gradient ); ?>; display:flex; align-items:center; justify-content:center;">
						<div style="width: 75%; height: 75%; background: #fff; border-radius: 50%;"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Dodaje Meta Boxa w edycji posta
	 */
	public function add_gsc_meta_box() {
		// Pokazuj meta box dla wpisów i stron, ale tylko jeśli Site Kit jest aktywny, by nie straszyć błędem.
		if ( ! $this->is_site_kit_active() ) {
			return;
		}

		add_meta_box(
			'atela_seo_gsc_post_meta',
			__( '📈 Atela SEO - Google Stats (Live)', 'atela-seo' ),
			array( $this, 'render_gsc_meta_box' ),
			array( 'post', 'page', 'product' ),
			'normal',
			'high'
		);
	}

	/**
	 * Renderuje zawartość Meta Boxa
	 */
	public function render_gsc_meta_box( $post ) {
		// Trzeba upewnić się, że post jest zapisany, żeby miał URL
		if ( $post->post_status === 'auto-draft' || empty( $post->post_name ) ) {
			echo '<p style="color:#666;">' . esc_html__( 'Save or publish the post to see GSC stats.', 'atela-seo' ) . '</p>';
			return;
		}

		$post_url = get_permalink( $post->ID );
		// Pobieramy ew. Focus Keyword
		$focus_keyword = get_post_meta( $post->ID, '_atela_seo_focus_keyword', true );
		if ( empty( $focus_keyword ) ) {
			$focus_keyword = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
		}
		if ( empty( $focus_keyword ) ) {
			$focus_keyword = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
		}

		?>
		<div id="atela-gsc-post-dashboard" data-url="<?php echo esc_url( $post_url ); ?>" data-focus="<?php echo esc_attr( $focus_keyword ); ?>">
			<p><?php esc_html_e( 'Fetching detailed statistics for this URL from GSC...', 'atela-seo' ); ?></p>
			<!-- Tutaj JS zrenderuje wynik -->
			<div id="atela-gsc-post-dates" style="margin-bottom:20px;"></div>
			<div id="atela-gsc-post-queries"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue skryptów z dodaniem wp-api-fetch
	 */
	public function enqueue_scripts( $hook ) {
		// Wgrywaj skrypty tylko na stronach GSC lub w edycji posta
		if ( $hook !== 'atela-seo_page_atela-seo-dashboard' && $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}

		// Dodajemy skrypt tylko, gdy wp.apiFetch jest dołączone lub dołączamy to bezpośrednio.
		// WordPress dostarcza bibliotekę `wp-api-fetch`.
		wp_enqueue_script(
			'atela-seo-gsc',
			ALPHA_SEO_URL . 'assets/js/atela-gsc.js',
			array( 'jquery', 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ),
			ALPHA_SEO_VERSION,
			true
		);
		wp_set_script_translations( 'atela-seo-gsc', 'atela-seo', ALPHA_SEO_DIR . 'languages' );

		// wp_set_script_translations() has no generated .json catalog for this plugin
		// (and the string in atela-gsc.js used the wrong textdomain, 'atela-seo-premium',
		// which would never have matched the 'atela-seo' domain registered above anyway),
		// so wp.i18n.__() always silently fell back to English. Pass the translated
		// string in directly instead.
		wp_localize_script( 'atela-seo-gsc', 'atelaGscL10n', array(
			'fetchError' => __( 'An error occurred while fetching data.', 'atela-seo' ),
		) );
	}
}
