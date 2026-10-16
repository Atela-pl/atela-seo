<?php
/**
 * Logika panelu administracyjnego wtyczki (Gutenberg/Classic).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Admin {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_seo_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_seo_meta_box_data' ) );

		// Dodanie zakładki w głównym, lewym menu WordPressa
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Ładowanie assetów podglądu
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_preview_assets' ) );
	}

	public function enqueue_preview_assets( $hook ) {
		// Na stronie ustawień Atela SEO i na stronach edycji postów
		if ( $hook !== 'toplevel_page_atela-seo' && $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'atela-seo-admin-preview',
			ALPHA_SEO_URL . 'assets/css/admin-preview.css',
			array(),
			filemtime( ALPHA_SEO_DIR . 'assets/css/admin-preview.css' )
		);

		wp_enqueue_script(
			'atela-seo-admin-preview',
			ALPHA_SEO_URL . 'assets/js/admin-preview.js',
			array( 'jquery' ),
			filemtime( ALPHA_SEO_DIR . 'assets/js/admin-preview.js' ),
			true
		);

		wp_enqueue_script(
			'atela-seo-admin-settings',
			ALPHA_SEO_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			filemtime( ALPHA_SEO_DIR . 'assets/js/admin-settings.js' ),
			true
		);

		$options = get_option( 'atela_seo_options', array() );

		wp_localize_script( 'atela-seo-admin-preview', 'alphaAdminPreview', array(
			'site_url'  => home_url( '/' ),
			'site_name' => get_bloginfo( 'name' ),
			'site_desc' => get_bloginfo( 'description' ),
			'separator' => $options['separator'] ?? '-',
			'home_title'=> $options['home_title'] ?? '%site_name% %sep% %site_desc%',
			'home_desc' => $options['home_description'] ?? '',
		) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id_get = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		wp_localize_script( 'atela-seo-admin-settings', 'alphaAdminSettings', array(
			'ping_nonce'         => wp_create_nonce( 'atela_seo_ping' ),
			'post_thumbnail_url' => ( $post_id_get && has_post_thumbnail( $post_id_get ) ) ? esc_url( Atela_SEO_Core::get_letterboxed_image_url( get_post_thumbnail_id( $post_id_get ) ) ) : '',
		) );
	}

    public function add_admin_menu() {
        add_menu_page(
            __( 'Atela SEO - Main Settings', 'atela-seo' ),
            __( 'Atela SEO', 'atela-seo' ), // Nazwa w lewym menu WP
            'manage_options',
            'atela-seo',
            array( $this, 'render_admin_page' ),
            'dashicons-chart-area', // Ikonka wykresu w menu
            30
        );
    }

    /**
     * Oczyszczanie zapisywanych opcji głównych.
     */
    public function sanitize_options( $input ) {
        $input = is_array( $input ) ? $input : array();

        // Zaczynamy od tego, co już zapisane. WordPress ZASTĘPUJE całą opcję zwróconą wartością, a ekran
        // ustawień wysyła pola tylko aktywnej zakładki (tak samo Elementor, konektor MCP i migrator wysyłają
        // tylko to, co zmieniają). Bez scalenia zapis jednej zakładki kasował ustawienia wszystkich pozostałych.
        $existing  = get_option( 'atela_seo_options', array() );
        $existing  = is_array( $existing ) ? $existing : array();
        $sanitized = $existing;

        // Zwykłe pola tekstowe
        $text_fields = array(
            'home_title', 'home_description', 'twitter_site', 'twitter_card_type',
            'breadcrumbs_separator', 'breadcrumbs_home_text', 'breadcrumbs_blog_text', 'breadcrumbs_404_text',
            'schema_org_name', 'schema_org_phone'
        );
        foreach ( $text_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
            }
        }
        
        // Pola URL
        $url_fields = array(
            'schema_org_url', 'schema_org_logo', 'schema_org_facebook', 'schema_org_twitter',
            'schema_org_instagram', 'schema_org_linkedin', 'schema_org_youtube'
        );
        foreach ( $url_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = esc_url_raw( $input[ $field ] );
            }
        }
        
        // Email
        if ( isset( $input['schema_org_email'] ) ) {
            $sanitized['schema_org_email'] = sanitize_email( $input['schema_org_email'] );
        }

        // Checkboxy / toggle. Odznaczony checkbox nie jest wysyłany w formularzu, więc "brak w żądaniu" oznacza
        // "wyłączone" WYŁĄCZNIE dla zakładki, która była zapisywana (formularz niesie jej nazwę w polu _tab).
        // Przełączniki pozostałych zakładek w ogóle nie zostały wysłane i muszą zostać bez zmian. Wywołania
        // programowe (bez _tab) zmieniają tylko te przełączniki, które faktycznie przekazały - i liczy się ich
        // wartość, a nie sama obecność klucza (isset() jest prawdziwe także dla 0).
        $tab_toggles    = self::get_tab_toggles();
        $tab            = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
        $from_form      = '' !== $tab;
        $noindex_handled = false;

        foreach ( $tab_toggles as $toggle_tab => $keys ) {
            foreach ( $keys as $key ) {
                if ( $from_form ) {
                    if ( $tab !== $toggle_tab ) {
                        continue;
                    }
                    $sanitized[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
                } elseif ( array_key_exists( $key, $input ) ) {
                    $sanitized[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
                } else {
                    continue;
                }

                if ( 'noindex' === $key ) {
                    $noindex_handled = true;
                }
            }
        }

        // Źródłem prawdy o noindex całej witryny jest blog_public (tak czyta go ekran ustawień, front i sam
        // WordPress). Zapis zmienia je tylko wtedy, gdy noindex faktycznie ustawiono: w formularzu zakładki
        // Ogólne albo programowo na wartość inną niż dotychczasowa. W każdym innym zapisie kopia w opcji jest
        // tylko dopasowywana do blog_public - dzięki temu zapis innej zakładki, Elementora czy migratora nie
        // przestawia indeksowania całej strony, a rozjazd po zmianie w Ustawienia > Czytanie sam się goi.
        $noindex_changed_by_code = ! $from_form && $noindex_handled
            && ( ! empty( $sanitized['noindex'] ) !== ! empty( $existing['noindex'] ) );

        if ( $noindex_handled && ( $from_form || $noindex_changed_by_code ) ) {
            update_option( 'blog_public', ! empty( $sanitized['noindex'] ) ? 0 : 1 );
        } else {
            $sanitized['noindex'] = 0 === (int) get_option( 'blog_public', 1 ) ? 1 : 0;
        }

        // Radio / select dla sitemapy
        if ( isset( $input['sitemap_mode'] ) ) {
            $sanitized['sitemap_mode'] = sanitize_text_field( $input['sitemap_mode'] );
        }
        if ( isset( $input['sitemap_changefreq'] ) ) {
            $sanitized['sitemap_changefreq'] = sanitize_text_field( $input['sitemap_changefreq'] );
        }
        
        // Numeryczne (ID obrazka)
        if ( isset( $input['og_default_image_id'] ) ) {
            $sanitized['og_default_image_id'] = absint( $input['og_default_image_id'] );
        }
        
        // Tablice (post types dla sitemap)
        if ( isset( $input['sitemap_post_types'] ) && is_array( $input['sitemap_post_types'] ) ) {
            $sanitized['sitemap_post_types'] = array_map( 'sanitize_text_field', $input['sitemap_post_types'] );
        } elseif ( 'sitemap' === $tab ) {
            // Wszystkie typy odznaczone w formularzu zakładki mapy witryny - checkboxy nie wysyłają wtedy nic.
            $sanitized['sitemap_post_types'] = array();
        }
        if ( isset( $input['sitemap_taxonomies'] ) && is_array( $input['sitemap_taxonomies'] ) ) {
            $sanitized['sitemap_taxonomies'] = array_map( 'sanitize_text_field', $input['sitemap_taxonomies'] );
        }
        
        // Zmiennoprzecinkowe (Priorytety sitemap)
        $float_fields = array( 'sitemap_priority_home', 'sitemap_priority_posts', 'sitemap_priority_pages', 'sitemap_priority_tax' );
        foreach ( $float_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = floatval( $input[ $field ] );
            }
        }

        // Tekst preformatowany (np. separator, jeśli ma specjalne znaki)
        if ( isset( $input['separator'] ) ) {
            $sanitized['separator'] = sanitize_text_field( $input['separator'] );
        }

        unset( $sanitized['_tab'] );

        return $sanitized;
    }

    /**
     * Przełączniki (checkboxy) ekranu ustawień pogrupowane wg zakładek, na których występują.
     */
    private static function get_tab_toggles() {
        return array(
            'general'     => array( 'noindex' ),
            'breadcrumbs' => array( 'breadcrumbs_enabled', 'breadcrumbs_show_home', 'breadcrumbs_show_current', 'breadcrumbs_show_blog' ),
            'sitemap'     => array( 'sitemap_enabled', 'sitemap_include_posts', 'sitemap_include_pages', 'sitemap_include_categories', 'sitemap_include_images', 'sitemap_exclude_noindex' ),
        );
    }

    public function register_settings() {
        register_setting( 'atela_seo_options_group', 'atela_seo_options', array( $this, 'sanitize_options' ) );
    }

    public function render_admin_page() {
        $options = get_option( 'atela_seo_options', array() );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( '🌟 Atela SEO - Settings', 'atela-seo' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=atela-seo&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'atela-seo' ); ?></a>
                <a href="?page=atela-seo&tab=search_appearance" class="nav-tab <?php echo $active_tab == 'search_appearance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Search Appearance', 'atela-seo' ); ?></a>
                <a href="?page=atela-seo&tab=social" class="nav-tab <?php echo $active_tab == 'social' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Social Media', 'atela-seo' ); ?></a>
                <a href="?page=atela-seo&tab=breadcrumbs" class="nav-tab <?php echo $active_tab == 'breadcrumbs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Breadcrumbs', 'atela-seo' ); ?></a>
                <a href="?page=atela-seo&tab=sitemap" class="nav-tab <?php echo $active_tab == 'sitemap' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'XML Sitemap', 'atela-seo' ); ?></a>
                <a href="?page=atela-seo&tab=schema" class="nav-tab <?php echo $active_tab == 'schema' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Schema.org', 'atela-seo' ); ?></a>
            </h2>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'atela_seo_options_group' ); ?>
                <?php // Nazwa zapisywanej zakładki: tylko jej przełączniki mogą być przez ten zapis wyłączone (patrz sanitize_options). ?>
                <input type="hidden" name="atela_seo_options[_tab]" value="<?php echo esc_attr( $active_tab ); ?>" />
                
                <?php if ( $active_tab == 'general' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Indexing Settings', 'atela-seo' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="atela_seo_options[noindex]" value="1" <?php checked( 0, (int) get_option( 'blog_public', 1 ), true ); ?> />
                                <?php esc_html_e( 'Discourage search engines from indexing this site (Noindex) - useful during site development.', 'atela-seo' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <?php if ( $active_tab == 'search_appearance' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Title Separator', 'atela-seo' ); ?></th>
                        <td>
                            <select name="atela_seo_options[separator]">
                                <?php
                                $separators = array( '-', '&ndash;', '&mdash;', '&middot;', '&bull;', '|', '~', '&laquo;', '&raquo;' );
                                $current_sep = isset( $options['separator'] ) ? $options['separator'] : '-';
                                foreach ( $separators as $sep ) {
                                    echo '<option value="' . esc_attr( $sep ) . '" ' . selected( $current_sep, $sep, false ) . '>' . esc_html( $sep ) . '</option>';
                                }
                                ?>
                            </select>
                            <?php 
                            // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
                            esc_html_e( 'Choose the symbol that separates the title from the site name (e.g. in the %sep% variable).', 'atela-seo' ); 
                            ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Title (Homepage)', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[home_title]" value="<?php echo esc_attr( isset($options['home_title']) ? $options['home_title'] : '%site_name% %sep% %site_desc%' ); ?>" style="width: 100%; max-width: 500px;" />
                            <?php 
                            // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
                            esc_html_e( 'Available variables: %title%, %site_name%, %site_desc%, %sep%, %page%', 'atela-seo' ); 
                            ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Meta Description (Homepage)', 'atela-seo' ); ?></th>
                        <td>
                            <textarea name="atela_seo_options[home_description]" style="width: 100%; max-width: 500px; height: 100px;"><?php echo esc_textarea( isset($options['home_description']) ? $options['home_description'] : '' ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Available variables as above.', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- SERP Preview -->
                <div class="aseo-preview-wrap" style="max-width:660px;">
                    <h3>👁️ <?php esc_html_e( 'Google Search Preview', 'atela-seo' ); ?></h3>
                    <div id="atela-seo-serp-preview" class="aseo-serp">
                        <div class="aseo-serp__url">
                            <span class="aseo-serp__url-favicon"><?php echo esc_html( substr( get_bloginfo('name'), 0, 1 ) ); ?></span>
                            <span class="aseo-serp__url-text"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></span>
                        </div>
                        <a href="#" class="aseo-serp__title" onclick="return false;"><?php echo esc_html( $options['home_title'] ?? get_bloginfo('name') ); ?></a>
                        <div class="aseo-serp__meta"><?php echo esc_html( date_i18n( 'd M Y' ) ); ?></div>
                        <div class="aseo-serp__description"><?php echo esc_html( $options['home_description'] ?? '' ); ?></div>
                        <div class="aseo-serp__counts">
                            <span title="<?php esc_attr_e( 'SEO Title Length (optimal: 40-60 chars)', 'atela-seo' ); ?>" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;"><?php esc_html_e( 'Title:', 'atela-seo' ); ?></span><span class="aseo-serp__title-count"></span></span>
                            <span title="<?php esc_attr_e( 'Meta Description Length (optimal: 120-160 chars)', 'atela-seo' ); ?>" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;"><?php esc_html_e( 'Desc:', 'atela-seo' ); ?></span><span class="aseo-serp__desc-count"></span></span>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

                <?php if ( $active_tab == 'social' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Default OG Image', 'atela-seo' ); ?></th>
                        <td>
                            <?php
                            $default_img_id = $options['og_default_image_id'] ?? 0;
                            $default_img_url = $default_img_id ? wp_get_attachment_image_url( $default_img_id, 'thumbnail' ) : '';
                            ?>
                            <input type="hidden" name="atela_seo_options[og_default_image_id]" id="atela_seo_og_default_image_id" value="<?php echo esc_attr( $default_img_id ); ?>" />
                            <div id="atela_seo_og_default_image_preview">
                                <?php if ( $default_img_url ) : ?>
                                <img src="<?php echo esc_url( $default_img_url ); ?>" style="max-width:200px;display:block;margin-bottom:8px;" />
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="atela_seo_og_image_btn"><?php esc_html_e( 'Select image', 'atela-seo' ); ?></button>
                            <?php if ( $default_img_id ) : ?>
                            <button type="button" class="button" id="atela_seo_og_image_clear"><?php esc_html_e( 'Remove', 'atela-seo' ); ?></button>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e( 'Fallback OG image (1200×630px) if the page has no featured image or custom OG image.', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Twitter / X Site @username', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[twitter_site]" value="<?php echo esc_attr( $options['twitter_site'] ?? '' ); ?>" placeholder="e.g. atela_pl" style="width:200px;" />
                            <p class="description"><?php esc_html_e( 'Your Twitter/X account username (without @).', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Default Twitter Card Type', 'atela-seo' ); ?></th>
                        <td>
                            <select name="atela_seo_options[twitter_card_type]">
                                <option value="summary_large_image" <?php selected( $options['twitter_card_type'] ?? 'summary_large_image', 'summary_large_image' ); ?>><?php esc_html_e( 'Summary Large Image (recommended)', 'atela-seo' ); ?></option>
                                <option value="summary" <?php selected( $options['twitter_card_type'] ?? '', 'summary' ); ?>><?php esc_html_e( 'Summary', 'atela-seo' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php wp_enqueue_media(); ?>

                <!-- FB + Twitter Preview -->
                <div class="aseo-preview-wrap" style="max-width:540px;">
                    <h3>👁️ <?php esc_html_e( 'Share Preview', 'atela-seo' ); ?></h3>
                    <div class="aseo-preview-tabs">
                        <span class="aseo-preview-tabs__tab active" data-target="atela-seo-fb-preview"><?php esc_html_e( 'Facebook / LinkedIn', 'atela-seo' ); ?></span>
                        <span class="aseo-preview-tabs__tab" data-target="atela-seo-twitter-preview"><?php esc_html_e( 'Twitter / X', 'atela-seo' ); ?></span>
                    </div>

                    <div id="atela-seo-fb-preview" class="aseo-preview-panel active aseo-fb">
                        <div class="aseo-fb__image">
                            <?php
                            $default_img_id  = $options['og_default_image_id'] ?? 0;
                            $default_img_url = $default_img_id ? wp_get_attachment_image_url( $default_img_id, 'large' ) : '';
                            ?>
                            <?php if ( $default_img_url ) : ?>
                            <img src="<?php echo esc_url( $default_img_url ); ?>" />
                            <?php else : ?>
                            <div class="aseo-fb__image-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                                <?php esc_html_e( 'No OG image (1200×630px)', 'atela-seo' ); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="aseo-fb__body">
                            <div class="aseo-fb__domain"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
                            <div class="aseo-fb__title"><?php echo esc_html( $options['home_title'] ?? get_bloginfo('name') ); ?></div>
                            <div class="aseo-fb__desc"><?php echo esc_html( $options['home_description'] ?? '' ); ?></div>
                        </div>
                    </div>

                    <div id="atela-seo-twitter-preview" class="aseo-preview-panel aseo-tw">
                        <div class="aseo-tw__image">
                            <?php if ( $default_img_url ) : ?>
                            <img src="<?php echo esc_url( $default_img_url ); ?>" />
                            <?php else : ?>
                            <div class="aseo-tw__image-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                                <?php esc_html_e( 'No OG image (1200×630px)', 'atela-seo' ); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="aseo-tw__body">
                            <div class="aseo-tw__title"><?php echo esc_html( $options['home_title'] ?? get_bloginfo('name') ); ?></div>
                            <div class="aseo-tw__desc"><?php echo esc_html( $options['home_description'] ?? '' ); ?></div>
                            <div class="aseo-tw__domain"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

                <?php if ( $active_tab == 'breadcrumbs' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Enable Breadcrumbs', 'atela-seo' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_enabled]" value="1" <?php checked( 1, $options['breadcrumbs_enabled'] ?? 0, true ); ?> />
                                <?php esc_html_e( 'Enable breadcrumbs generation for the site', 'atela-seo' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Separator', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_separator]" value="<?php echo esc_attr( $options['breadcrumbs_separator'] ?? '›' ); ?>" style="width: 50px;" />
                            <p class="description"><?php esc_html_e( 'Character separating path elements (e.g. ›, », -)', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( '"Home" text', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_home_text]" value="<?php echo esc_attr( $options['breadcrumbs_home_text'] ?? __( 'Home', 'atela-seo' ) ); ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( '"Blog" text', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_blog_text]" value="<?php echo esc_attr( $options['breadcrumbs_blog_text'] ?? __( 'Blog', 'atela-seo' ) ); ?>" />
                            <p class="description"><?php esc_html_e( 'Used if the site has a separate posts page.', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( '404 text', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_404_text]" value="<?php echo esc_attr( $options['breadcrumbs_404_text'] ?? __( 'Not found', 'atela-seo' ) ); ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Display details', 'atela-seo' ); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_show_home]" value="1" <?php checked( 1, $options['breadcrumbs_show_home'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Show "Home" element', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_show_current]" value="1" <?php checked( 1, $options['breadcrumbs_show_current'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Show current page (last element)', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_show_blog]" value="1" <?php checked( 1, $options['breadcrumbs_show_blog'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Show blog page before categories', 'atela-seo' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <div style="max-width:800px; padding:15px 20px; background:#fff; border-left:4px solid #0073aa; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-top:20px;">
                    <h3 style="margin-top:0; font-size:14px;">🛠 <?php esc_html_e( 'How to embed breadcrumbs on the page?', 'atela-seo' ); ?></h3>
                    <ul style="margin-bottom:0; padding-left:20px;">
                        <li style="margin-bottom:5px;"><strong><?php esc_html_e( 'In Elementor:', 'atela-seo' ); ?></strong> <?php echo wp_kses_post( __( 'Use our dedicated widget <b>Atela SEO Breadcrumbs</b> in the widgets tab.', 'atela-seo' ) ); ?></li>
                        <li style="margin-bottom:5px;"><strong>Shortcode:</strong> <code>[atela_seo_breadcrumbs]</code> <?php esc_html_e( '(to be inserted in Gutenberg or classic editor).', 'atela-seo' ); ?></li>
                        <li><strong><?php esc_html_e( 'In the theme file (PHP):', 'atela-seo' ); ?></strong> <code>&lt;?php if ( function_exists('atela_seo_breadcrumbs') ) atela_seo_breadcrumbs(); ?&gt;</code></li>
                    </ul>
                </div>

                <div class="aseo-preview-wrap" style="max-width:800px; padding:20px; background:#f9f9f9; border:1px solid #ddd; margin-top:30px;">
                    <h3 style="margin-top:0;">👁️ <?php esc_html_e( 'Live Preview', 'atela-seo' ); ?></h3>
                    <p class="description" style="margin-bottom:15px;"><?php esc_html_e( 'This is how breadcrumbs might look on your site (the exact look depends on your theme).', 'atela-seo' ); ?></p>
                    <nav class="atela-seo-breadcrumbs-preview" style="font-size:14px; color:#555;">
                        <span><a href="#" style="color:#0073aa;text-decoration:none;" id="aseo_bc_home_preview"><?php esc_html_e( 'Home', 'atela-seo' ); ?></a></span>
                        <span class="aseo-bc-sep" style="margin:0 8px;opacity:0.6;">›</span>
                        <span><a href="#" style="color:#0073aa;text-decoration:none;" id="aseo_bc_blog_preview"><?php esc_html_e( 'Blog', 'atela-seo' ); ?></a></span>
                        <span class="aseo-bc-sep" style="margin:0 8px;opacity:0.6;">›</span>
                        <span style="font-weight:bold;color:#111;" id="aseo_bc_current_preview"><?php esc_html_e( 'Example post', 'atela-seo' ); ?></span>
                    </nav>
                </div>
                <?php endif; ?>

                <?php if ( $active_tab == 'sitemap' ) : ?>
                <?php
                $sitemap_mode = $options['sitemap_mode'] ?? 'single';
                $sitemap_url = home_url( $sitemap_mode === 'index' ? '/sitemap_index.xml' : '/sitemap.xml' );
                $last_ping   = get_option( 'atela_seo_last_ping', '' );
                $post_types  = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
                ?>
                <div style="max-width:800px; padding:14px 20px; background:#fff; border-left:4px solid #2271b1; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px;">
                    <strong>🗺️ <?php esc_html_e( 'Your sitemap:', 'atela-seo' ); ?></strong>
                    <a href="<?php echo esc_url( $sitemap_url ); ?>" id="aseo_sitemap_url_link" target="_blank" style="margin-left:8px;"><?php echo esc_html( $sitemap_url ); ?></a>
                </div>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Enable XML Sitemap', 'atela-seo' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="atela_seo_options[sitemap_enabled]" value="1" <?php checked( 1, $options['sitemap_enabled'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Generate automatic XML sitemap', 'atela-seo' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Sitemap Mode', 'atela-seo' ); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="radio" name="atela_seo_options[sitemap_mode]" value="single" <?php checked( 'single', $options['sitemap_mode'] ?? 'single' ); ?> />
                                <strong><?php esc_html_e( 'Single file', 'atela-seo' ); ?></strong> &ndash; <code>sitemap.xml</code> <?php esc_html_e( 'with all URLs (recommended for small sites)', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="atela_seo_options[sitemap_mode]" value="index" <?php checked( 'index', $options['sitemap_mode'] ?? 'single' ); ?> />
                                <strong><?php esc_html_e( 'Sitemap index', 'atela-seo' ); ?></strong> &ndash; <code>sitemap_index.xml</code> + <?php esc_html_e( 'separate files per content type (recommended for large sites)', 'atela-seo' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Sitemap Content', 'atela-seo' ); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_posts]" value="1" <?php checked( 1, $options['sitemap_include_posts'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Posts', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_pages]" value="1" <?php checked( 1, $options['sitemap_include_pages'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Pages', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_categories]" value="1" <?php checked( 1, $options['sitemap_include_categories'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Categories and Tags', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_images]" value="1" <?php checked( 1, $options['sitemap_include_images'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Include images (<image:image> tags)', 'atela-seo' ); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="atela_seo_options[sitemap_exclude_noindex]" value="1" <?php checked( 1, $options['sitemap_exclude_noindex'] ?? 1, true ); ?> />
                                <?php esc_html_e( 'Exclude pages with noindex', 'atela-seo' ); ?>
                            </label>
                        </td>
                    </tr>
                    <?php if ( ! empty( $post_types ) ) : ?>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Custom Post Types (CPT)', 'atela-seo' ); ?></th>
                        <td>
                            <?php foreach ( $post_types as $pt ) : ?>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>"
                                    <?php if ( in_array( $pt->name, (array)( $options['sitemap_post_types'] ?? [] ) ) ) echo 'checked'; ?> />
                                <?php echo esc_html( $pt->label ); ?> (<code><?php echo esc_html( $pt->name ); ?></code>)
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Change Frequency', 'atela-seo' ); ?></th>
                        <td>
                            <select name="atela_seo_options[sitemap_changefreq]">
                                <?php
                                $freqs = array( 'always' => 'Always', 'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'never' => 'Never' );
                                $cur_freq = $options['sitemap_changefreq'] ?? 'weekly';
                                foreach ( $freqs as $val => $label ) {
                                    echo '<option value="' . esc_attr( $val ) . '" ' . selected( $cur_freq, $val, false ) . '>' . esc_html( $label ) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Priorities', 'atela-seo' ); ?></th>
                        <td>
                            <table style="border-spacing:8px 4px;">
                                <tr><td style="color:#666;font-size:13px;"><?php esc_html_e( 'Homepage:', 'atela-seo' ); ?></td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_home]" value="<?php echo esc_attr( $options['sitemap_priority_home'] ?? '1.0' ); ?>" style="width:65px;"></td></tr>
                                <tr><td style="color:#666;font-size:13px;"><?php esc_html_e( 'Posts:', 'atela-seo' ); ?></td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_posts]" value="<?php echo esc_attr( $options['sitemap_priority_posts'] ?? '0.8' ); ?>" style="width:65px;"></td></tr>
                                <tr><td style="color:#666;font-size:13px;"><?php esc_html_e( 'Pages:', 'atela-seo' ); ?></td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_pages]" value="<?php echo esc_attr( $options['sitemap_priority_pages'] ?? '0.7' ); ?>" style="width:65px;"></td></tr>
                                <tr><td style="color:#666;font-size:13px;"><?php esc_html_e( 'Categories:', 'atela-seo' ); ?></td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_tax]" value="<?php echo esc_attr( $options['sitemap_priority_tax'] ?? '0.5' ); ?>" style="width:65px;"></td></tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div style="max-width:800px; padding:16px 20px; background:#f9f9f9; border:1px solid #ddd; margin-top:20px;">
                    <h3 style="margin-top:0; font-size:14px;">🔔 <?php esc_html_e( 'Ping search engines', 'atela-seo' ); ?></h3>
                    <p class="description" style="margin-bottom:12px;"><?php esc_html_e( 'Send a notification to Google and Bing that your sitemap has been updated.', 'atela-seo' ); ?></p>
                    <?php if ( $last_ping ) : ?>
                    <p style="margin-bottom:10px; font-size:12px; color:#666;">
                    <?php 
                        /* translators: %s: date and time of last ping */
                        printf( esc_html__( 'Last ping: %s', 'atela-seo' ), esc_html( $last_ping ) ); 
                    ?>
                    </p>
                    <?php endif; ?>
                    <button type="button" id="aseo_ping_btn" class="button button-secondary">🔔 <?php esc_html_e( 'Ping Google & Bing', 'atela-seo' ); ?></button>
                    <span id="aseo_ping_result" style="margin-left:10px; font-size:13px;"></span>
                </div>
                <?php endif; ?>

                <?php if ( $active_tab == 'schema' ) : ?>
                <div style="max-width:800px; padding:14px 20px; background:#fff; border-left:4px solid #2271b1; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px;">
                    <strong>🧠 Schema.org JSON-LD</strong> &ndash; <?php echo wp_kses_post( __( 'structured data automatically injected into the <code>&lt;head&gt;</code> of each page. Helps Google display <em>rich snippets</em> (e.g. author, article date, organization logo).', 'atela-seo' ) ); ?>
                </div>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Organization Name', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[schema_org_name]" value="<?php echo esc_attr( $options['schema_org_name'] ?? get_bloginfo('name') ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Full name of your company or organization.', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Organization URL', 'atela-seo' ); ?></th>
                        <td>
                            <input type="url" name="atela_seo_options[schema_org_url]" value="<?php echo esc_attr( $options['schema_org_url'] ?? home_url('/') ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Organization Logo (URL)', 'atela-seo' ); ?></th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <input type="url" id="aseo_schema_logo" name="atela_seo_options[schema_org_logo]" value="<?php echo esc_attr( $options['schema_org_logo'] ?? '' ); ?>" class="regular-text" />
                                <button type="button" class="button" id="aseo_schema_logo_btn"><?php esc_html_e( 'Select logo', 'atela-seo' ); ?></button>
                            </div>
                            <?php if ( ! empty( $options['schema_org_logo'] ) ) : ?>
                            <img src="<?php echo esc_url( $options['schema_org_logo'] ); ?>" style="max-height:60px;margin-top:8px;display:block;border:1px solid #ddd;padding:4px;background:#fff;" />
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e( 'Recommended: rectangle or square, min. 112px height.', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Contact e-mail', 'atela-seo' ); ?></th>
                        <td>
                            <input type="email" name="atela_seo_options[schema_org_email]" value="<?php echo esc_attr( $options['schema_org_email'] ?? '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Phone', 'atela-seo' ); ?></th>
                        <td>
                            <input type="text" name="atela_seo_options[schema_org_phone]" value="<?php echo esc_attr( $options['schema_org_phone'] ?? '' ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'International format, e.g. +48123456789', 'atela-seo' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Social Profiles (sameAs)', 'atela-seo' ); ?></th>
                        <td>
                            <?php
                            $social_schema = array(
                                'schema_social_facebook'  => 'Facebook',
                                'schema_social_instagram' => 'Instagram',
                                'schema_social_twitter'   => 'Twitter/X',
                                'schema_social_linkedin'  => 'LinkedIn',
                                'schema_social_youtube'   => 'YouTube',
                                'schema_social_pinterest' => 'Pinterest',
                            );
                            foreach ( $social_schema as $key => $label ) :
                            ?>
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="width:100px;color:#666;font-size:13px;"><?php echo esc_html( $label ); ?></span>
                                <input type="url" name="atela_seo_options[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $options[ $key ] ?? '' ); ?>" class="regular-text" placeholder="https://..." />
                            </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <?php submit_button( __( 'Save changes', 'atela-seo' ), 'primary', 'submit', true, array( 'style' => 'background:#2563eb; border-color:#1d4ed8;' ) ); ?>
            </form>
        </div>
        <?php
    }

	public function add_seo_meta_box() {
		$screens = array( 'post', 'page' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'atela_seo_meta_box',
				'Atela SEO',
				array( $this, 'render_meta_box_content' ),
				$screen,
				'normal',
				'high'
			);
		}
	}

	public function render_meta_box_content( $post ) {
		wp_nonce_field( 'atela_seo_save_data', 'atela_seo_meta_box_nonce' );

		$title = get_post_meta( $post->ID, '_atela_seo_title', true );
		$description = get_post_meta( $post->ID, '_atela_seo_description', true );
		$noindex = get_post_meta( $post->ID, '_atela_seo_noindex', true );
		$canonical = get_post_meta( $post->ID, '_atela_seo_canonical', true );
		$focus_keyword = get_post_meta( $post->ID, '_atela_seo_focus_keyword', true );
		$og_title = get_post_meta( $post->ID, '_atela_seo_og_title', true );
		$og_description = get_post_meta( $post->ID, '_atela_seo_og_description', true );
		$og_image_id = get_post_meta( $post->ID, '_atela_seo_og_image_id', true );
		$og_image_url = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'thumbnail' ) : '';

		wp_enqueue_media();

		// ---- SEO ----
		echo '<div style="border-bottom:1px solid #eee;margin-bottom:14px;padding-bottom:4px;"><strong>🔍 SEO</strong></div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_title" style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'SEO Title', 'atela-seo' ) . '</label>';
		echo '<input type="text" id="atela_seo_title" name="atela_seo_title" value="' . esc_attr( $title ) . '" style="width:100%;" />';
		echo '<p class="description" style="margin-top:4px;">';
		// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText
		echo esc_html__( 'Available variables: %title%, %site_name%, %site_desc%, %sep%, %page%', 'atela-seo' );
		echo '</p>';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_description" style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'Meta Description', 'atela-seo' ) . '</label>';
		echo '<textarea id="atela_seo_description" name="atela_seo_description" style="width:100%;height:70px;">' . esc_textarea( $description ) . '</textarea>';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Available variables as above.', 'atela-seo' ) . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_focus_keyword" style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'Focus Keyword', 'atela-seo' ) . '</label>';
		echo '<input type="text" id="atela_seo_focus_keyword" name="atela_seo_focus_keyword" value="' . esc_attr( $focus_keyword ) . '" style="width:100%;" />';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_canonical" style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'Canonical URL', 'atela-seo' ) . ' <span style="font-weight:normal;color:#666;">' . esc_html__( '(optional)', 'atela-seo' ) . '</span></label>';
		echo '<input type="text" id="atela_seo_canonical" name="atela_seo_canonical" value="' . esc_attr( $canonical ) . '" style="width:100%;" placeholder="' . esc_attr__( 'Leave empty for default', 'atela-seo' ) . '" />';
		echo '</div>';

		echo '<div style="margin-bottom:14px;">';
		echo '<label><input type="checkbox" name="atela_seo_noindex" value="1" ' . checked( 1, $noindex, false ) . ' /> ' . esc_html__( 'Discourage search engines from indexing this post (noindex)', 'atela-seo' ) . '</label>';
		echo '</div>';

		// ---- SOCIAL ----
		echo '<div style="border-bottom:1px solid #eee;margin-bottom:14px;padding-bottom:4px;"><strong>📣 ' . esc_html__( 'Social Media (Open Graph / Twitter)', 'atela-seo' ) . '</strong></div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_og_title" style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'OG Title', 'atela-seo' ) . ' <span style="font-weight:normal;color:#666;">' . esc_html__( '(default: SEO Title)', 'atela-seo' ) . '</span></label>';
		echo '<input type="text" id="atela_seo_og_title" name="atela_seo_og_title" value="' . esc_attr( $og_title ) . '" style="width:100%;" />';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_og_description" style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'OG Description', 'atela-seo' ) . ' <span style="font-weight:normal;color:#666;">' . esc_html__( '(default: Meta Description)', 'atela-seo' ) . '</span></label>';
		echo '<textarea id="atela_seo_og_description" name="atela_seo_og_description" style="width:100%;height:70px;">' . esc_textarea( $og_description ) . '</textarea>';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label style="display:block;font-weight:bold;margin-bottom:5px;">' . esc_html__( 'OG Image', 'atela-seo' ) . ' <span style="font-weight:normal;color:#666;">' . esc_html__( '(1200×630px, default: featured image)', 'atela-seo' ) . '</span></label>';
		echo '<input type="hidden" id="atela_seo_og_image_id" name="atela_seo_og_image_id" value="' . esc_attr( $og_image_id ) . '" />';
		if ( $og_image_url ) {
			echo '<div id="atela_seo_og_image_preview"><img src="' . esc_url( $og_image_url ) . '" style="max-width:200px;display:block;margin-bottom:8px;border:1px solid #ddd;padding:2px;" /></div>';
		} else {
			echo '<div id="atela_seo_og_image_preview"></div>';
		}
		echo '<button type="button" class="button" id="atela_seo_og_image_btn">' . esc_html__( 'Select OG image', 'atela-seo' ) . '</button> ';
		echo '<button type="button" class="button button-link-delete" id="atela_seo_og_image_clear" style="' . ( $og_image_id ? '' : 'display:none;' ) . '">' . esc_html__( 'Remove', 'atela-seo' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Image URL (1200x630px). Leave empty to use the page featured image.', 'atela-seo' ) . '</p>';
		echo '<p class="description" style="color:#0071a1;">💡 <strong>' . esc_html__( 'Automatic margins:', 'atela-seo' ) . '</strong> ' . esc_html__( 'The system automatically letterboxes non-matching images, ensuring no elements (like logos or text) are cropped when shared on FB/Twitter.', 'atela-seo' ) . '</p>';
		echo '</div>';

		// ---- LIVE PREVIEW ----
		$preview_title = $title ?: get_the_title( $post->ID );
		$preview_desc  = $description;
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$og_img_large  = $og_image_id ? Atela_SEO_Core::get_letterboxed_image_url( $og_image_id ) : '';
		$featured_img  = has_post_thumbnail( $post->ID ) ? Atela_SEO_Core::get_letterboxed_image_url( get_post_thumbnail_id( $post->ID ) ) : '';
		if ( ! $og_img_large && $featured_img ) {
			$og_img_large = $featured_img;
		}
		?>
		<div id="atela-seo-metabox-preview" class="is-mobile">
			<div style="display:flex; justify-content:flex-start; align-items:center; gap: 24px; border-bottom:1px solid #eee; margin-bottom:14px; padding-bottom:8px;">
				<strong>👁️ <?php esc_html_e( 'Live Preview', 'atela-seo' ); ?></strong>
				<label class="aseo-device-toggle" style="display:flex;align-items:center;cursor:pointer;font-size:12px;color:#555;">
					<span class="aseo-toggle-label aseo-toggle-mobile" style="margin-right:6px;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Mobile', 'atela-seo' ); ?></span>
					<div style="position:relative;width:36px;height:20px;background:#b5bbc3;border-radius:20px;margin:0 4px;transition:0.3s;" id="aseo_toggle_track">
						<div style="position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:0.3s;" id="aseo_toggle_knob"></div>
					</div>
					<span class="aseo-toggle-label aseo-toggle-desktop" style="margin-left:6px;"><?php esc_html_e( 'Desktop', 'atela-seo' ); ?></span>
				</label>
			</div>

			<div class="aseo-preview-wrap">
				<div class="aseo-preview-tabs">
					<span class="aseo-preview-tabs__tab active" data-target="atela-seo-metabox-serp"><?php esc_html_e( 'Google', 'atela-seo' ); ?></span>
					<span class="aseo-preview-tabs__tab" data-target="atela-seo-metabox-fb"><?php esc_html_e( 'Facebook', 'atela-seo' ); ?></span>
					<span class="aseo-preview-tabs__tab" data-target="atela-seo-metabox-tw"><?php esc_html_e( 'Twitter / X', 'atela-seo' ); ?></span>
				</div>

				<div id="atela-seo-metabox-serp" class="aseo-preview-panel active aseo-serp">
					<div class="aseo-serp__header">
						<div class="aseo-serp__favicon">
							<?php 
							$icon = get_site_icon_url(32); 
							if ($icon) { echo '<img src="' . esc_url($icon) . '">'; }
							else { echo '<span>' . esc_html( substr( get_bloginfo('name'), 0, 1 ) ) . '</span>'; }
							?>
						</div>
						<div class="aseo-serp__site-info">
							<div class="aseo-serp__site-name"><?php echo esc_html( get_bloginfo('name') ); ?></div>
							<div class="aseo-serp__url-text"><?php echo esc_html( $site_host ); ?></div>
						</div>
					</div>
					<a href="#" class="aseo-serp__title" onclick="return false;"><?php echo esc_html( $preview_title ); ?></a>
					<div class="aseo-serp__body">
						<div class="aseo-serp__content">
							<span class="aseo-serp__meta"><?php echo esc_html( date_i18n( 'j M Y' ) ); ?> — </span>
							<span class="aseo-serp__description"><?php echo esc_html( $preview_desc ); ?></span>
						</div>
						<div class="aseo-serp__thumbnail">
							<?php if ( $og_img_large ) : ?>
							<img src="<?php echo esc_url( $og_img_large ); ?>" />
							<?php endif; ?>
						</div>
					</div>
					<div class="aseo-serp__counts">
						<span title="<?php esc_attr_e( 'SEO Title Length (optimal: 40-60 chars)', 'atela-seo' ); ?>" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;"><?php esc_html_e( 'Title:', 'atela-seo' ); ?></span><span class="aseo-serp__title-count"></span></span>
						<span title="<?php esc_attr_e( 'Meta Description Length (optimal: 120-160 chars)', 'atela-seo' ); ?>" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;"><?php esc_html_e( 'Desc:', 'atela-seo' ); ?></span><span class="aseo-serp__desc-count"></span></span>
					</div>
				</div>

				<div id="atela-seo-metabox-fb" class="aseo-preview-panel aseo-fb">
					<div class="aseo-fb__image">
						<?php if ( $og_img_large ) : ?>
						<img src="<?php echo esc_url( $og_img_large ); ?>" />
						<?php else : ?>
						<div class="aseo-fb__image-placeholder">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
							<?php esc_html_e( 'No OG Image', 'atela-seo' ); ?>
						</div>
						<?php endif; ?>
					</div>
					<div class="aseo-fb__body">
						<div class="aseo-fb__domain"><?php echo esc_html( strtoupper( $site_host ) ); ?></div>
						<div class="aseo-fb__title"><?php echo esc_html( $og_title ?: $preview_title ); ?></div>
						<div class="aseo-fb__desc"><?php echo esc_html( $og_description ?: $preview_desc ); ?></div>
					</div>
				</div>

				<div id="atela-seo-metabox-tw" class="aseo-preview-panel aseo-tw">
					<div class="aseo-tw__image">
						<?php if ( $og_img_large ) : ?>
						<img src="<?php echo esc_url( $og_img_large ); ?>" />
						<?php else : ?>
						<div class="aseo-tw__image-placeholder">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
							<?php esc_html_e( 'No OG Image', 'atela-seo' ); ?>
						</div>
						<?php endif; ?>
					</div>
					<div class="aseo-tw__body">
						<div class="aseo-tw__title"><?php echo esc_html( $og_title ?: $preview_title ); ?></div>
						<div class="aseo-tw__desc"><?php echo esc_html( $og_description ?: $preview_desc ); ?></div>
						<div class="aseo-tw__domain"><?php echo esc_html( $site_host ); ?></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


	public function save_seo_meta_box_data( $post_id ) {
		if ( ! isset( $_POST['atela_seo_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atela_seo_meta_box_nonce'] ) ), 'atela_seo_save_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['atela_seo_title'] ) ) {
			update_post_meta( $post_id, '_atela_seo_title', sanitize_text_field( wp_unslash( $_POST['atela_seo_title'] ) ) );
		}
		if ( isset( $_POST['atela_seo_description'] ) ) {
			update_post_meta( $post_id, '_atela_seo_description', sanitize_textarea_field( wp_unslash( $_POST['atela_seo_description'] ) ) );
		}
		if ( isset( $_POST['atela_seo_focus_keyword'] ) ) {
			update_post_meta( $post_id, '_atela_seo_focus_keyword', sanitize_text_field( wp_unslash( $_POST['atela_seo_focus_keyword'] ) ) );
		}
		if ( isset( $_POST['atela_seo_canonical'] ) ) {
			update_post_meta( $post_id, '_atela_seo_canonical', esc_url_raw( wp_unslash( $_POST['atela_seo_canonical'] ) ) );
		}
		$noindex = isset( $_POST['atela_seo_noindex'] ) ? 1 : 0;
		update_post_meta( $post_id, '_atela_seo_noindex', $noindex );

		// Social / OG
		if ( isset( $_POST['atela_seo_og_title'] ) ) {
			update_post_meta( $post_id, '_atela_seo_og_title', sanitize_text_field( wp_unslash( $_POST['atela_seo_og_title'] ) ) );
		}
		if ( isset( $_POST['atela_seo_og_description'] ) ) {
			update_post_meta( $post_id, '_atela_seo_og_description', sanitize_textarea_field( wp_unslash( $_POST['atela_seo_og_description'] ) ) );
		}
		if ( isset( $_POST['atela_seo_og_image_id'] ) ) {
			update_post_meta( $post_id, '_atela_seo_og_image_id', absint( wp_unslash( $_POST['atela_seo_og_image_id'] ) ) );
		}
	}
}
