<?php
/**
 * Integracja wtyczki Atela SEO z edytorem Elementor.
 *
 * - Wstrzykuje dedykowaną zakładkę "Atela SEO" przez JavaScript.
 * - Obsługuje AJAX do odczytu i zapisu metadanych SEO posta.
 * - Rejestruje zakładkę w Ustawieniach Witryny (Kit) przez PHP.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Elementor {

	public function __construct() {
		// Ładowanie skryptów/styli tylko w edytorze Elementora
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );

		// Rejestrujemy własną zakładkę bezpośrednio – konstruktor jest wywoływany
		// na hooku elementor/init (priorytet 10), controls_manager jest już dostępny
		$this->register_custom_tab();

		// Kontrolki PHP per-strona w naszej własnej zakładce
		add_action( 'elementor/documents/register_controls', array( $this, 'register_document_controls' ) );

		// Zapisywanie danych z Elementora
		add_action( 'elementor/document/after_save', array( $this, 'save_elementor_seo_data' ), 10, 2 );

		// Rejestracja zakładki w Ustawieniach Witryny (Site Settings)
		add_action( 'elementor/kit/register_tabs', array( $this, 'register_kit_tabs' ) );

		// Rejestracja niestandardowych widgetów (Okruszki)
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Handlery AJAX (zalogowani użytkownicy)
		add_action( 'wp_ajax_atela_seo_get_post_meta',  array( $this, 'ajax_get_post_meta' ) );
		add_action( 'wp_ajax_atela_seo_save_post_meta', array( $this, 'ajax_save_post_meta' ) );
	}

	/* -------------------------------------------------------------------------
	 * Rejestracja niestandardowej zakładki w Controls_Manager
	 * ---------------------------------------------------------------------- */
	public function register_custom_tab() {
		\Elementor\Plugin::$instance->controls_manager->add_tab(
			'atela_seo',
			'🌟 Atela SEO'
		);
	}

	/* -------------------------------------------------------------------------
	 * Rejestracja widgetów Elementora
	 * ---------------------------------------------------------------------- */
	public function register_widgets( $widgets_manager ) {
		require_once ALPHA_SEO_DIR . 'includes/integrations/class-atela-seo-breadcrumbs-widget.php';
		$widgets_manager->register( new \Atela_SEO_Breadcrumbs_Widget() );
	}

	/* -------------------------------------------------------------------------
	 * Ładowanie assetów edytora
	 * ---------------------------------------------------------------------- */
	public function enqueue_editor_assets() {
		wp_enqueue_style(
			'atela-seo-editor',
			ALPHA_SEO_URL . 'assets/css/atela-seo-editor.css',
			array(),
			ALPHA_SEO_VERSION
		);

		wp_enqueue_script(
			'atela-seo-editor',
			ALPHA_SEO_URL . 'assets/js/atela-seo-editor.js',
			array( 'jquery', 'elementor-editor' ),
			ALPHA_SEO_VERSION,
			true  // w stopce, po Elementorze
		);

		// Dane przekazane do JS (nonce, URL AJAX)
		wp_localize_script(
			'atela-seo-editor',
			'alphaSeoPanelData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'atela_seo_editor_nonce' ),
			)
		);
	}

	/* -------------------------------------------------------------------------
	 * AJAX – odczyt metadanych posta
	 * ---------------------------------------------------------------------- */
	public function ajax_get_post_meta() {
		check_ajax_referer( 'atela_seo_editor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Brak uprawnień.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'Nieprawidłowy post_id.' );
		}

		wp_send_json_success( array(
			'title'         => get_post_meta( $post_id, '_atela_seo_title', true ),
			'description'   => get_post_meta( $post_id, '_atela_seo_description', true ),
			'noindex'       => get_post_meta( $post_id, '_atela_seo_noindex', true ),
			'canonical'     => get_post_meta( $post_id, '_atela_seo_canonical', true ),
			'focus_keyword' => get_post_meta( $post_id, '_atela_seo_focus_keyword', true ),
		) );
	}

	/* -------------------------------------------------------------------------
	 * AJAX – zapis metadanych posta
	 * ---------------------------------------------------------------------- */
	public function ajax_save_post_meta() {
		check_ajax_referer( 'atela_seo_editor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Brak uprawnień.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( 'Nieprawidłowy post.' );
		}

		update_post_meta( $post_id, '_atela_seo_title',         sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
		update_post_meta( $post_id, '_atela_seo_description',   sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ) );
		update_post_meta( $post_id, '_atela_seo_noindex',       intval( $_POST['noindex'] ?? 0 ) );
		update_post_meta( $post_id, '_atela_seo_canonical',     esc_url_raw( wp_unslash( $_POST['canonical'] ?? '' ) ) );
		update_post_meta( $post_id, '_atela_seo_focus_keyword', sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ?? '' ) ) );

		wp_send_json_success( array( 'message' => 'Dane SEO zapisane.' ) );
	}

	/* -------------------------------------------------------------------------
	 * Ustawienia Witryny (Elementor Site Settings / Kit)
	 * ---------------------------------------------------------------------- */
	public function register_kit_tabs( $kit ) {
		if ( class_exists( '\Elementor\Core\Kits\Documents\Tabs\Tab_Base' ) ) {
			$kit->register_tab( 'atela-seo-global-settings', 'Atela_SEO_Kit_Tab' );
		}
	}

	/* -------------------------------------------------------------------------
	 * Kontrolki PHP per-strona w dedykowanej zakładce Atela SEO
	 * ---------------------------------------------------------------------- */
	public function register_document_controls( $document ) {
		if (
			! $document instanceof \Elementor\Core\DocumentTypes\PageBase &&
			! $document instanceof \Elementor\Core\DocumentTypes\Post
		) {
			return;
		}

		$post_id = $document->get_main_id();

		// Nasza własna zakładka 'atela_seo' (zarejestrowana przez register_custom_tab)
		$document->start_controls_section(
			'atela_seo_section',
			array(
				'label' => 'Metadane SEO',
				'tab'   => 'atela_seo',
			)
		);

		$document->add_control(
			'_atela_seo_title',
			array(
				'label'       => 'SEO Title',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_title', true ),
				'label_block' => true,
				'description' => 'Zalecana długość: 40–60 znaków.',
			)
		);

		$document->add_control(
			'_atela_seo_description',
			array(
				'label'       => 'Meta Description',
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => get_post_meta( $post_id, '_atela_seo_description', true ),
				'rows'        => 5,
				'description' => 'Zalecana długość: 120–160 znaków.',
			)
		);

		$document->add_control(
			'_atela_seo_noindex',
			array(
				'label'        => 'Blokuj indeksowanie (noindex)',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Tak',
				'label_off'    => 'Nie',
				'return_value' => 'yes',
				'default'      => get_post_meta( $post_id, '_atela_seo_noindex', true ) ? 'yes' : '',
				'description'  => 'Włącz, aby wyszukiwarki nie indeksowały tej strony.',
			)
		);

		$document->add_control(
			'_atela_seo_canonical',
			array(
				'label'       => 'Adres kanoniczny (Canonical URL)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_canonical', true ),
				'label_block' => true,
				'description' => 'Pozostaw puste, aby użyć domyślnego. Zmień, jeśli ta treść to kopia innej strony.',
			)
		);

		$document->add_control(
			'_atela_seo_focus_keyword',
			array(
				'label'       => 'Fraza kluczowa (Focus Keyword)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_focus_keyword', true ),
				'label_block' => true,
				'description' => 'Główna fraza, na którą optymalizujesz tę stronę (na potrzeby analizy).',
			)
		);

		$document->end_controls_section();

		// ---- Sekcja Social Media ----
		$document->start_controls_section(
			'atela_seo_social_section',
			array(
				'label' => '📣 Social Media (Open Graph / Twitter)',
				'tab'   => 'atela_seo',
			)
		);

		$document->add_control(
			'_atela_seo_og_title',
			array(
				'label'       => 'OG Title',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_og_title', true ),
				'label_block' => true,
				'description' => 'Tytuł wyświetlany przy udostępnieniu na Facebooku/LinkedIn. Domyślnie używa SEO Title.',
			)
		);

		$document->add_control(
			'_atela_seo_og_description',
			array(
				'label'       => 'OG Description',
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => get_post_meta( $post_id, '_atela_seo_og_description', true ),
				'rows'        => 3,
				'description' => 'Opis wyświetlany przy udostępnieniu. Domyślnie używa Meta Description.',
			)
		);

		$document->add_control(
			'_atela_seo_og_image_url',
			array(
				'label'       => 'OG Image URL',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_og_image_url', true ),
				'label_block' => true,
				'description' => 'Adres URL obrazu (1200×630px). Zostaw puste – użyje featured image strony.',
			)
		);

		$document->add_control(
			'_atela_seo_twitter_creator',
			array(
				'label'       => 'Twitter/X @autor (creator)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_twitter_creator', true ),
				'label_block' => true,
				'description' => 'Nick autora na Twitter/X (bez @). Opcjonalne.',
			)
		);

		$document->end_controls_section();
	}

	/* -------------------------------------------------------------------------
	 * Zapis danych z kontrolek PHP Elementora (po kliknięciu "Opublikuj")
	 * ---------------------------------------------------------------------- */
	public function save_elementor_seo_data( $document, $data ) {
		// Zapis globalnych ustawień witryny (Kit)
		if (
			class_exists( '\Elementor\Core\Kits\Documents\Kit' ) &&
			$document instanceof \Elementor\Core\Kits\Documents\Kit
		) {
			$options = get_option( 'atela_seo_options', array() );

			if ( isset( $data['settings']['_atela_seo_global_title'] ) ) {
				$options['home_title'] = sanitize_text_field( $data['settings']['_atela_seo_global_title'] );
			}
			if ( isset( $data['settings']['_atela_seo_global_desc'] ) ) {
				$options['home_description'] = sanitize_textarea_field( $data['settings']['_atela_seo_global_desc'] );
			}
			if ( isset( $data['settings']['_atela_seo_global_noindex'] ) ) {
				$options['noindex'] = $data['settings']['_atela_seo_global_noindex'] === 'yes' ? 1 : 0;
			} else {
				$options['noindex'] = 0;
			}

			update_option( 'atela_seo_options', $options );
			return;
		}

		// Zapis metadanych per-strona (z zakładki Atela SEO)
		$post_id = $document->get_main_id();

		if ( isset( $data['settings']['_atela_seo_title'] ) ) {
			update_post_meta( $post_id, '_atela_seo_title', sanitize_text_field( $data['settings']['_atela_seo_title'] ) );
		}
		if ( isset( $data['settings']['_atela_seo_description'] ) ) {
			update_post_meta( $post_id, '_atela_seo_description', sanitize_textarea_field( $data['settings']['_atela_seo_description'] ) );
		}
		if ( isset( $data['settings']['_atela_seo_noindex'] ) ) {
			update_post_meta( $post_id, '_atela_seo_noindex', $data['settings']['_atela_seo_noindex'] === 'yes' ? 1 : 0 );
		} else {
			// Switcher nie wysyła wartości gdy jest wyłączony, więc ustawiamy 0
			update_post_meta( $post_id, '_atela_seo_noindex', 0 );
		}
		if ( isset( $data['settings']['_atela_seo_canonical'] ) ) {
			update_post_meta( $post_id, '_atela_seo_canonical', esc_url_raw( $data['settings']['_atela_seo_canonical'] ) );
		}
		if ( isset( $data['settings']['_atela_seo_focus_keyword'] ) ) {
			update_post_meta( $post_id, '_atela_seo_focus_keyword', sanitize_text_field( $data['settings']['_atela_seo_focus_keyword'] ) );
		}

		// Social / OG
		if ( isset( $data['settings']['_atela_seo_og_title'] ) ) {
			update_post_meta( $post_id, '_atela_seo_og_title', sanitize_text_field( $data['settings']['_atela_seo_og_title'] ) );
		}
		if ( isset( $data['settings']['_atela_seo_og_description'] ) ) {
			update_post_meta( $post_id, '_atela_seo_og_description', sanitize_textarea_field( $data['settings']['_atela_seo_og_description'] ) );
		}
		if ( isset( $data['settings']['_atela_seo_og_image_url'] ) ) {
			update_post_meta( $post_id, '_atela_seo_og_image_url', esc_url_raw( $data['settings']['_atela_seo_og_image_url'] ) );
		}
		if ( isset( $data['settings']['_atela_seo_twitter_creator'] ) ) {
			update_post_meta( $post_id, '_atela_seo_twitter_creator', sanitize_text_field( $data['settings']['_atela_seo_twitter_creator'] ) );
		}
	}
}


/* =============================================================================
 * Klasa zakładki Ustawień Witryny (Site Settings Kit Tab)
 * ========================================================================== */
if ( class_exists( '\Elementor\Core\Kits\Documents\Tabs\Tab_Base' ) ) {
	class Atela_SEO_Kit_Tab extends \Elementor\Core\Kits\Documents\Tabs\Tab_Base {

		public function get_id() {
			return 'atela-seo-global-settings';
		}

		public function get_title() {
			return '🌟 Atela SEO';
		}

		public function get_icon() {
			return 'eicon-meta-data';
		}

		public function get_group() {
			return 'settings';
		}

		public function get_help_url() {
			return '';
		}

		protected function register_tab_controls() {
			$options = get_option( 'atela_seo_options', array() );

			$this->start_controls_section(
				'atela_seo_global_section',
				array(
					'label' => 'Globalne Ustawienia SEO',
					'tab'   => $this->get_id(),
				)
			);

			$this->add_control(
				'_atela_seo_global_title',
				array(
					'label'       => 'Title (Strona Główna)',
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => $options['home_title'] ?? '',
					'label_block' => true,
				)
			);

			$this->add_control(
				'_atela_seo_global_desc',
				array(
					'label'   => 'Meta Description (Strona Główna)',
					'type'    => \Elementor\Controls_Manager::TEXTAREA,
					'default' => $options['home_description'] ?? '',
					'rows'    => 4,
				)
			);

			$this->add_control(
				'_atela_seo_global_noindex',
				array(
					'label'        => 'Zablokuj indeksowanie (Noindex)',
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => 'Tak',
					'label_off'    => 'Nie',
					'return_value' => 'yes',
					'default'      => ( ! empty( $options['noindex'] ) ) ? 'yes' : '',
				)
			);

			$this->end_controls_section();
		}
	}
}
