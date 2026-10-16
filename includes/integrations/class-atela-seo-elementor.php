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

	/** Wartość 'default' przełącznika noindex - patrz komentarz w register_document_controls(). */
	const NOINDEX_SENTINEL = 'atela-unset';

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

		// Stara kopia pól SEO w ustawieniach strony (patrz purge_settings_shadow) czyścimy przy
		// otwarciu edytora, zanim Elementor zbuduje z niej model, i podajemy stan noindex z bazy.
		add_action( 'elementor/editor/init', array( $this, 'prepare_settings_on_editor_open' ) );

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
			wp_send_json_error( __( 'No permissions.', 'atela-seo' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid post_id.', 'atela-seo' ) );
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
			wp_send_json_error( __( 'No permissions.', 'atela-seo' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
		}

		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description   = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$noindex       = isset( $_POST['noindex'] ) ? absint( wp_unslash( $_POST['noindex'] ) ) : 0;
		$canonical     = isset( $_POST['canonical'] ) ? esc_url_raw( wp_unslash( $_POST['canonical'] ) ) : '';
		$focus_keyword = isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : '';

		update_post_meta( $post_id, '_atela_seo_title',         $title );
		update_post_meta( $post_id, '_atela_seo_description',   $description );
		update_post_meta( $post_id, '_atela_seo_noindex',       $noindex );
		update_post_meta( $post_id, '_atela_seo_canonical',     $canonical );
		update_post_meta( $post_id, '_atela_seo_focus_keyword', $focus_keyword );

		wp_send_json_success( array( 'message' => __( 'SEO data saved.', 'atela-seo' ) ) );
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
				'label' => __( 'SEO Metadata', 'atela-seo' ),
				'tab'   => 'atela_seo',
			)
		);

		$document->add_control(
			'_atela_seo_title',
			array(
				'label'       => __( 'SEO Title', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_title', true ),
				'label_block' => true,
				'description' => __( 'Recommended length: 40-60 characters.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_description',
			array(
				'label'       => __( 'Meta Description', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => get_post_meta( $post_id, '_atela_seo_description', true ),
				'rows'        => 5,
				'description' => __( 'Recommended length: 120-160 characters.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_noindex',
			array(
				'label'        => __( 'Discourage indexing (noindex)', 'atela-seo' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'atela-seo' ),
				'label_off'    => __( 'No', 'atela-seo' ),
				'return_value' => 'yes',
				// 'default' celowo NIE jest stanem z bazy. Elementor wycina z żądania zapisu
				// wartości równe 'default', a ten 'default' jest stały przez całą sesję edytora -
				// więc po "wyłącz -> zapisz -> włącz" w jednej sesji ponowne włączenie równało się
				// 'default' i w ogóle nie trafiało do zapisu (baza zostawała na 0 przy włączonym
				// przełączniku w UI). Wartownik różni się od każdego realnego stanu ('yes' / ''),
				// więc klucz jest wysyłany ZAWSZE. Faktyczny stan podajemy przy otwarciu edytora
				// jako wartość zapisaną (patrz sync_noindex_into_settings).
				'default'      => self::NOINDEX_SENTINEL,
				'description'  => __( 'Turn on to stop search engines from indexing this page.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_canonical',
			array(
				'label'       => __( 'Canonical URL', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_canonical', true ),
				'label_block' => true,
				'description' => __( 'Leave empty for default. Change if this content is a copy of another page.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_focus_keyword',
			array(
				'label'       => __( 'Focus Keyword', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_focus_keyword', true ),
				'label_block' => true,
				'description' => __( 'The main keyword you are optimizing this page for.', 'atela-seo' ),
			)
		);

		$document->end_controls_section();

		// ---- Sekcja Social Media ----
		$document->start_controls_section(
			'atela_seo_social_section',
			array(
				'label' => __( '📣 Social Media (Open Graph / Twitter)', 'atela-seo' ),
				'tab'   => 'atela_seo',
			)
		);

		$document->add_control(
			'_atela_seo_og_title',
			array(
				'label'       => __( 'OG Title', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_og_title', true ),
				'label_block' => true,
				'description' => __( 'Title used when shared on FB/LinkedIn. Defaults to SEO Title.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_og_description',
			array(
				'label'       => __( 'OG Description', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => get_post_meta( $post_id, '_atela_seo_og_description', true ),
				'rows'        => 3,
				'description' => __( 'Description used when shared. Defaults to Meta Description.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_og_image_url',
			array(
				'label'       => __( 'OG Image URL', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_og_image_url', true ),
				'label_block' => true,
				'description' => __( 'Image URL (1200x630px). Leave empty to use featured image.', 'atela-seo' ),
			)
		);

		$document->add_control(
			'_atela_seo_twitter_creator',
			array(
				'label'       => __( 'Twitter/X @author (creator)', 'atela-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => get_post_meta( $post_id, '_atela_seo_twitter_creator', true ),
				'label_block' => true,
				'description' => __( 'Author username on Twitter/X (without @). Optional.', 'atela-seo' ),
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
			$options = is_array( $options ) ? $options : array();

			if ( isset( $data['settings']['_atela_seo_global_title'] ) ) {
				$options['home_title'] = sanitize_text_field( $data['settings']['_atela_seo_global_title'] );
			}
			if ( isset( $data['settings']['_atela_seo_global_desc'] ) ) {
				$options['home_description'] = sanitize_textarea_field( $data['settings']['_atela_seo_global_desc'] );
			}

			// Noindex całej witryny zmieniamy TYLKO, gdy użytkownik przestawił przełącznik w tej sesji edytora.
			// Przełącznik ma wartownika w 'default', więc Elementor wysyła go przy każdym zapisie Ustawień
			// Witryny (także gdy zmieniono np. kolor) - porównujemy go ze stanem z chwili otwarcia edytora.
			// Dawniej brak klucza oznaczał "wyłączone": każdy zapis Ustawień Witryny mógł przestawić indeksowanie.
			$baseline = get_transient( $this->kit_noindex_baseline_key() );
			if ( isset( $data['settings']['_atela_seo_global_noindex'] ) && false !== $baseline ) {
				$requested = $data['settings']['_atela_seo_global_noindex'];
				if ( in_array( $requested, array( 'yes', '' ), true ) && $requested !== $baseline ) {
					update_option( 'blog_public', 'yes' === $requested ? 0 : 1 );
					set_transient( $this->kit_noindex_baseline_key(), $requested, DAY_IN_SECONDS );
				}
			}
			// Źródłem prawdy jest blog_public - kopia w opcji ma tylko za nim nadążać.
			$options['noindex'] = 0 === (int) get_option( 'blog_public', 1 ) ? 1 : 0;

			update_option( 'atela_seo_options', $options );
			$this->purge_kit_shadow();
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
			$noindex = $data['settings']['_atela_seo_noindex'];
			// Tylko 'yes' (włączone) i '' (wyłączone) to realne stany przełącznika. Wartownik
			// (stan sprzed zsynchronizowania z bazą) ani nic innego nie może po cichu zmienić noindex.
			if ( 'yes' === $noindex ) {
				update_post_meta( $post_id, '_atela_seo_noindex', 1 );
			} elseif ( '' === $noindex ) {
				update_post_meta( $post_id, '_atela_seo_noindex', 0 );
			}
		}
		// Brak klucza = "bez zmian". Dawniej brak klucza był traktowany jako "wyłączone"
		// (else => 0), przez co KAŻDY Update strony z włączonym noindex po cichu go wyłączał,
		// nawet gdy edytowano zupełnie coś innego - Elementor nie wysyła ustawień równych 'default'.
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

		$this->purge_settings_shadow( $post_id );
	}

	/**
	 * Otwarcie edytora: czyścimy starą kopię pól SEO i podajemy przełącznikowi noindex
	 * stan z bazy (jego 'default' to wartownik). Dotyczy tylko stron i wpisów - tak jak
	 * sama zakładka (register_document_controls).
	 */
	public function prepare_settings_on_editor_open() {
		if ( current_user_can( 'manage_options' ) ) {
			$this->prepare_kit_settings();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! $this->is_page_or_post_document( $post_id ) ) {
			return;
		}

		$this->purge_settings_shadow( $post_id );
		$this->sync_noindex_into_settings( $post_id );
	}

	private function is_page_or_post_document( $post_id ) {
		try {
			$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		} catch ( \Throwable $e ) {
			return false;
		}

		return $document instanceof \Elementor\Core\DocumentTypes\PageBase
			|| $document instanceof \Elementor\Core\DocumentTypes\Post;
	}

	private function sync_noindex_into_settings( $post_id ) {
		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['_atela_seo_noindex'] = get_post_meta( $post_id, '_atela_seo_noindex', true ) ? 'yes' : '';
		update_post_meta( $post_id, '_elementor_page_settings', $settings );
	}

	private function kit_noindex_baseline_key() {
		return 'atela_seo_kit_noindex_' . get_current_user_id();
	}

	private function get_kit_id() {
		try {
			return (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	/**
	 * Otwarcie edytora: zakładka Atela w Ustawieniach Witryny dostaje aktualny stan noindex (z blog_public)
	 * jako wartość zapisaną, a ten stan zapamiętujemy jako punkt odniesienia dla zapisu. Stara kopia tytułu
	 * i opisu strony głównej z ustawień Kit jest usuwana - te wartości żyją w opcji wtyczki, a kopia pokazywałaby
	 * (i przy zapisie przywracała) nieaktualne dane zmienione w międzyczasie np. przez konektor.
	 */
	private function prepare_kit_settings() {
		$kit_id = $this->get_kit_id();
		if ( ! $kit_id ) {
			return;
		}

		$state    = 0 === (int) get_option( 'blog_public', 1 ) ? 'yes' : '';
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();

		unset( $settings['_atela_seo_global_title'], $settings['_atela_seo_global_desc'] );
		$settings['_atela_seo_global_noindex'] = $state;

		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
		set_transient( $this->kit_noindex_baseline_key(), $state, DAY_IN_SECONDS );
	}

	private function purge_kit_shadow() {
		$kit_id = $this->get_kit_id();
		if ( ! $kit_id ) {
			return;
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( is_array( $settings ) && ( isset( $settings['_atela_seo_global_title'] ) || isset( $settings['_atela_seo_global_desc'] ) ) ) {
			unset( $settings['_atela_seo_global_title'], $settings['_atela_seo_global_desc'] );
			update_post_meta( $kit_id, '_elementor_page_settings', $settings );
		}
	}

	/**
	 * Elementor zapisuje ustawienia strony (też nasze klucze _atela_seo_*) w
	 * _elementor_page_settings jako kopię. Źródłem prawdy są metadane posta, więc
	 * kopię usuwamy po każdym zapisie i przy otwarciu edytora - inaczej przy następnym
	 * Update stara wartość mogłaby nadpisać nowszą zmienioną poza Elementorem (np. noindex).
	 */
	private function purge_settings_shadow( $post_id ) {
		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$changed = false;
		foreach ( array_keys( $settings ) as $key ) {
			if ( 0 === strpos( (string) $key, '_atela_seo_' ) ) {
				unset( $settings[ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_post_meta( $post_id, '_elementor_page_settings', $settings );
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
					'label' => __( 'Global SEO Settings', 'atela-seo' ),
					'tab'   => $this->get_id(),
				)
			);

			$this->add_control(
				'_atela_seo_global_title',
				array(
					'label'       => __( 'Title (Homepage)', 'atela-seo' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => $options['home_title'] ?? '',
					'label_block' => true,
				)
			);

			$this->add_control(
				'_atela_seo_global_desc',
				array(
					'label'   => __( 'Meta Description (Homepage)', 'atela-seo' ),
					'type'    => \Elementor\Controls_Manager::TEXTAREA,
					'default' => $options['home_description'] ?? '',
					'rows'    => 4,
				)
			);

			$this->add_control(
				'_atela_seo_global_noindex',
				array(
					'label'        => __( 'Discourage indexing (noindex)', 'atela-seo' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'atela-seo' ),
					'label_off'    => __( 'No', 'atela-seo' ),
					'return_value' => 'yes',
					// Wartownik, nie stan z bazy: patrz komentarz w Atela_SEO_Elementor::save_elementor_seo_data().
					'default'      => Atela_SEO_Elementor::NOINDEX_SENTINEL,
				)
			);

			$this->end_controls_section();
		}
	}
}
