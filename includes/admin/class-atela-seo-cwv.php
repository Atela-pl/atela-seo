<?php
/**
 * Moduł Wydajności (Core Web Vitals za pomocą Google PageSpeed API)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_CWV {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_cwv_menu' ), 25 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_atela_seo_analyze_cwv', array( $this, 'analyze_cwv_ajax' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'atela-seo_page_atela-seo-cwv' ) {
			return;
		}

		wp_enqueue_script(
			'atela-seo-cwv',
			ALPHA_SEO_URL . 'assets/js/atela-cwv.js',
			array( 'jquery' ),
			ALPHA_SEO_VERSION,
			true
		);

		wp_localize_script( 'atela-seo-cwv', 'atelaSeoCwv', array(
			'cwv_nonce' => wp_create_nonce( 'atela_cwv_nonce' ),
		) );

		// wp-i18n is not a declared dependency here and no .json translation catalog
		// exists for this plugin, so wp.i18n.__() would always fall back to English -
		// pass the translated string in directly instead.
		wp_localize_script( 'atela-seo-cwv', 'atelaCwvL10n', array(
			'error' => __( 'Error: ', 'atela-seo' ),
		) );
	}

	public function add_cwv_menu() {
		add_submenu_page(
			'atela-seo',
			__( 'Performance (Core Web Vitals)', 'atela-seo' ),
			__( 'Performance (CWV)', 'atela-seo' ),
			'manage_options',
			'atela-seo-cwv',
			array( $this, 'render_cwv_page' )
		);
	}

	public function register_settings() {
		register_setting( 'atela_seo_cwv_group', 'atela_seo_pagespeed_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public function render_cwv_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key = get_option( 'atela_seo_pagespeed_api_key', '' );
		$home_url = home_url();

		?>
		<div class="wrap" style="max-width:900px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
			<h1 style="font-size:24px; font-weight:600; margin-bottom:20px; color:#1e293b;">⚡ <?php esc_html_e( 'Performance: Core Web Vitals', 'atela-seo' ); ?></h1>

			<div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
				<form method="post" action="options.php">
					<?php settings_fields( 'atela_seo_cwv_group' ); ?>

					<label for="atela_seo_pagespeed_api_key" style="font-weight:600; display:block; margin-bottom:8px;"><?php esc_html_e( 'Google PageSpeed Insights API Key', 'atela-seo' ); ?></label>
					<div style="display:flex; gap:10px; align-items:center;">
						<input type="password" id="atela_seo_pagespeed_api_key" name="atela_seo_pagespeed_api_key" value="<?php echo esc_attr( $api_key ); ?>" style="flex-grow:1; max-width:400px; border-radius:4px; border:1px solid #cbd5e1;" placeholder="AIzaSyA..." />
						<?php submit_button( __( 'Save key', 'atela-seo' ), 'secondary', 'submit', false ); ?>
					</div>
					<p class="description"><?php esc_html_e( 'To fetch the latest performance audit data from Google, generate a free key in Google Cloud Console.', 'atela-seo' ); ?></p>
				</form>
			</div>

			<?php if ( ! empty( $api_key ) ) : ?>
			<div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
					<h2 style="margin:0; font-size:18px;"><?php esc_html_e( 'Analysis for:', 'atela-seo' ); ?> <a href="<?php echo esc_url($home_url); ?>" target="_blank"><?php echo esc_html($home_url); ?></a></h2>
					<button id="atela-cwv-start-btn" class="button button-primary" style="background:#2563eb; border-color:#1d4ed8;"><?php esc_html_e( 'Start Audit (Mobile & Desktop)', 'atela-seo' ); ?></button>
				</div>

				<div id="atela-cwv-loading" style="display:none; text-align:center; padding:30px; color:#64748b;">
					<span class="spinner is-active" style="float:none; margin-right:10px;"></span> <?php esc_html_e( 'Querying Google servers (this may take a few seconds)...', 'atela-seo' ); ?>
				</div>

				<div id="atela-cwv-results" style="display:none; gap:20px;">
					<!-- Kontenery na wyniki zostaną wygenerowane przez JS -->
				</div>
			</div>


			<?php else : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Enter your PageSpeed Insights API key to unlock the analysis panel.', 'atela-seo' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function analyze_cwv_ajax() {
		check_ajax_referer( 'atela_cwv_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'No permissions.', 'atela-seo' ) );
		}

		$api_key = get_option( 'atela_seo_pagespeed_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'No API key', 'atela-seo' ) );
		}

		$url = home_url();

		// Wykonaj zapytania dla mobile i desktop równolegle (teoretycznie) - w PHP po kolei
		$mobile_data = $this->fetch_pagespeed( $url, 'mobile', $api_key );
		$desktop_data = $this->fetch_pagespeed( $url, 'desktop', $api_key );

		if ( is_wp_error( $mobile_data ) ) {
			wp_send_json_error( $mobile_data->get_error_message() );
		}

		ob_start();
		$this->render_cwv_card( __( 'Mobile (Smartphones)', 'atela-seo' ), $mobile_data );
		$this->render_cwv_card( __( 'Desktop (Computers)', 'atela-seo' ), $desktop_data );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	private function fetch_pagespeed( $url, $strategy, $api_key ) {
		$api_url = add_query_arg( array(
			'url'      => urlencode( $url ),
			'strategy' => $strategy,
			'key'      => $api_key
		), 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );

		$response = wp_remote_get( $api_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'api_error', $data['error']['message'] );
		}

		return $data;
	}

	private function render_cwv_card( $title, $data ) {
		// Wyciąganie metryk
		$score = isset( $data['lighthouseResult']['categories']['performance']['score'] ) ? $data['lighthouseResult']['categories']['performance']['score'] * 100 : 0;
		$metrics = isset( $data['lighthouseResult']['audits'] ) ? $data['lighthouseResult']['audits'] : array();

		$lcp = isset( $metrics['largest-contentful-paint']['displayValue'] ) ? $metrics['largest-contentful-paint']['displayValue'] : __( 'N/A', 'atela-seo' );
		$cls = isset( $metrics['cumulative-layout-shift']['displayValue'] ) ? $metrics['cumulative-layout-shift']['displayValue'] : __( 'N/A', 'atela-seo' );
		$fcp = isset( $metrics['first-contentful-paint']['displayValue'] ) ? $metrics['first-contentful-paint']['displayValue'] : __( 'N/A', 'atela-seo' );

		$color = $score >= 90 ? '#22c55e' : ( $score >= 50 ? '#eab308' : '#ef4444' );

		// Dynamiczny opis na podstawie wyniku
		$description = '';
		if ( $score >= 90 ) {
			$description = __( '<span style="color:#15803d; font-weight:600;">Great score!</span> Your site is fully optimized, loads instantly, and meets Google Core Web Vitals strict standards.', 'atela-seo' );
		} elseif ( $score >= 50 ) {
			$description = __( '<span style="color:#a16207; font-weight:600;">Needs improvement.</span> The site works fine but loses precious seconds. Consider image optimization (WebP), caching, and CSS/JS minification.', 'atela-seo' );
		} else {
			$description = __( '<span style="color:#b91c1c; font-weight:600;">Critical performance issue!</span> Google may drastically drop rankings for this page. Focus on hosting upgrade, removing heavy plugins, and LCP optimization.', 'atela-seo' );
		}

		?>
		<div style="flex:1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
			<h3 style="margin-top:0; font-size: 16px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom:10px;"><?php echo esc_html( $title ); ?></h3>
			<div style="display:flex; align-items:center; gap: 20px; margin-bottom: 20px;">
				<div style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 24px; color: white; background-color: <?php echo esc_attr( $color ); ?>;">
					<?php echo intval( $score ); ?>
				</div>
				<div>
					<p style="margin: 0; font-size: 14px; color: #64748b;"><?php esc_html_e( 'Performance Score', 'atela-seo' ); ?></p>
					<p style="margin: 0; font-size: 12px; color: #94a3b8;">Lighthouse Engine</p>
				</div>
			</div>

			<table style="width:100%; border-collapse: collapse; font-size: 13px;">
				<tr style="border-bottom: 1px solid #f1f5f9;">
					<td style="padding: 8px 0; color: #475569;">Largest Contentful Paint (LCP)</td>
					<td style="padding: 8px 0; text-align:right; font-weight:600;"><?php echo esc_html( $lcp ); ?></td>
				</tr>
				<tr style="border-bottom: 1px solid #f1f5f9;">
					<td style="padding: 8px 0; color: #475569;">Cumulative Layout Shift (CLS)</td>
					<td style="padding: 8px 0; text-align:right; font-weight:600;"><?php echo esc_html( $cls ); ?></td>
				</tr>
				<tr>
					<td style="padding: 8px 0; color: #475569;">First Contentful Paint (FCP)</td>
					<td style="padding: 8px 0; text-align:right; font-weight:600;"><?php echo esc_html( $fcp ); ?></td>
				</tr>
			</table>

			<div style="margin-top: 15px; padding: 12px; background: #f8fafc; border-radius: 6px; font-size: 13px; color: #334155; line-height: 1.5;">
				<?php echo wp_kses_post( $description ); ?>
			</div>
		</div>
		<?php
	}
}
