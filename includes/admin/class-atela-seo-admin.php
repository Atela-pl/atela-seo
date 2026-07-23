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
            'Atela SEO - Ustawienia Główne',
            'Atela SEO', // Nazwa w lewym menu WP
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
        $sanitized = array();
        
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

        // Checkboxy / toggle
        $sanitized['noindex'] = isset( $input['noindex'] ) ? 1 : 0;
        update_option( 'blog_public', $sanitized['noindex'] ? 0 : 1 );
        $sanitized['sitemap_enabled'] = isset( $input['sitemap_enabled'] ) ? 1 : 0;
        $sanitized['breadcrumbs_enabled'] = isset( $input['breadcrumbs_enabled'] ) ? 1 : 0;
        $sanitized['breadcrumbs_show_home'] = isset( $input['breadcrumbs_show_home'] ) ? 1 : 0;
        $sanitized['breadcrumbs_show_current'] = isset( $input['breadcrumbs_show_current'] ) ? 1 : 0;
        $sanitized['breadcrumbs_show_blog'] = isset( $input['breadcrumbs_show_blog'] ) ? 1 : 0;

        $sanitized['sitemap_include_posts'] = isset( $input['sitemap_include_posts'] ) ? 1 : 0;
        $sanitized['sitemap_include_pages'] = isset( $input['sitemap_include_pages'] ) ? 1 : 0;
        $sanitized['sitemap_include_categories'] = isset( $input['sitemap_include_categories'] ) ? 1 : 0;
        $sanitized['sitemap_include_images'] = isset( $input['sitemap_include_images'] ) ? 1 : 0;
        $sanitized['sitemap_exclude_noindex'] = isset( $input['sitemap_exclude_noindex'] ) ? 1 : 0;
        
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

        return $sanitized;
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
            <h1>🌟 Atela SEO - Ustawienia</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=atela-seo&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Ogólne</a>
                <a href="?page=atela-seo&tab=search_appearance" class="nav-tab <?php echo $active_tab == 'search_appearance' ? 'nav-tab-active' : ''; ?>">Wygląd w Wyszukiwarce</a>
                <a href="?page=atela-seo&tab=social" class="nav-tab <?php echo $active_tab == 'social' ? 'nav-tab-active' : ''; ?>">Social Media</a>
                <a href="?page=atela-seo&tab=breadcrumbs" class="nav-tab <?php echo $active_tab == 'breadcrumbs' ? 'nav-tab-active' : ''; ?>">Okruszki</a>
                <a href="?page=atela-seo&tab=sitemap" class="nav-tab <?php echo $active_tab == 'sitemap' ? 'nav-tab-active' : ''; ?>">XML Sitemap</a>
                <a href="?page=atela-seo&tab=schema" class="nav-tab <?php echo $active_tab == 'schema' ? 'nav-tab-active' : ''; ?>">Schema.org</a>
            </h2>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'atela_seo_options_group' ); ?>
                
                <?php if ( $active_tab == 'general' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Ustawienia Indeksowania</th>
                        <td>
                            <label>
                                <input type="checkbox" name="atela_seo_options[noindex]" value="1" <?php checked( 0, (int) get_option( 'blog_public', 1 ), true ); ?> />
                                Zablokuj indeksowanie całej witryny (Noindex) - przydatne podczas budowy strony.
                            </label>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <?php if ( $active_tab == 'search_appearance' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Separator Tytułu</th>
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
                            <p class="description">Wybierz znak oddzielający tytuł od nazwy witryny (np. w zmiennej %sep%).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Title (Strona Główna)</th>
                        <td>
                            <input type="text" name="atela_seo_options[home_title]" value="<?php echo esc_attr( isset($options['home_title']) ? $options['home_title'] : '%site_name% %sep% %site_desc%' ); ?>" style="width: 100%; max-width: 500px;" />
                            <p class="description">Dostępne zmienne: %title%, %site_name%, %site_desc%, %sep%, %page%</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Meta Description (Strona Główna)</th>
                        <td>
                            <textarea name="atela_seo_options[home_description]" style="width: 100%; max-width: 500px; height: 100px;"><?php echo esc_textarea( isset($options['home_description']) ? $options['home_description'] : '' ); ?></textarea>
                            <p class="description">Dostępne zmienne j.w.</p>
                        </td>
                    </tr>
                </table>

                <!-- SERP Preview -->
                <div class="aseo-preview-wrap" style="max-width:660px;">
                    <h3>👁️ Podgląd w wynikach Google</h3>
                    <div id="atela-seo-serp-preview" class="aseo-serp">
                        <div class="aseo-serp__url">
                            <span class="aseo-serp__url-favicon"><?php echo esc_html( substr( get_bloginfo('name'), 0, 1 ) ); ?></span>
                            <span class="aseo-serp__url-text"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></span>
                        </div>
                        <a href="#" class="aseo-serp__title" onclick="return false;"><?php echo esc_html( $options['home_title'] ?? get_bloginfo('name') ); ?></a>
                        <div class="aseo-serp__meta"><?php echo esc_html( date_i18n( 'd M Y' ) ); ?></div>
                        <div class="aseo-serp__description"><?php echo esc_html( $options['home_description'] ?? '' ); ?></div>
                        <div class="aseo-serp__counts">
                            <span title="Długość Tytułu SEO (optymalnie 40-60 znaków)" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;">Tytuł:</span><span class="aseo-serp__title-count"></span></span>
                            <span title="Długość Meta Description (optymalnie 120-160 znaków)" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;">Opis:</span><span class="aseo-serp__desc-count"></span></span>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

                <?php if ( $active_tab == 'social' ) : ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Domyślny obraz OG</th>
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
                            <button type="button" class="button" id="atela_seo_og_image_btn">Wybierz obraz</button>
                            <?php if ( $default_img_id ) : ?>
                            <button type="button" class="button" id="atela_seo_og_image_clear">Usuń</button>
                            <?php endif; ?>
                            <p class="description">Obraz fallback OG (1200×630px) jeśli strona nie ma featured image ani własnego OG image.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Twitter / X @username witryny</th>
                        <td>
                            <input type="text" name="atela_seo_options[twitter_site]" value="<?php echo esc_attr( $options['twitter_site'] ?? '' ); ?>" placeholder="np. atela_pl" style="width:200px;" />
                            <p class="description">Nazwa użytkownika Twojego konta Twitter/X (bez @).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Domyślny typ Twitter Card</th>
                        <td>
                            <select name="atela_seo_options[twitter_card_type]">
                                <option value="summary_large_image" <?php selected( $options['twitter_card_type'] ?? 'summary_large_image', 'summary_large_image' ); ?>>Summary Large Image (zalecany)</option>
                                <option value="summary" <?php selected( $options['twitter_card_type'] ?? '', 'summary' ); ?>>Summary</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php wp_enqueue_media(); ?>

                <!-- FB + Twitter Preview -->
                <div class="aseo-preview-wrap" style="max-width:540px;">
                    <h3>👁️ Podgląd udostępnienia</h3>
                    <div class="aseo-preview-tabs">
                        <span class="aseo-preview-tabs__tab active" data-target="atela-seo-fb-preview">Facebook / LinkedIn</span>
                        <span class="aseo-preview-tabs__tab" data-target="atela-seo-twitter-preview">Twitter / X</span>
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
                                Brak obrazu OG (1200×630px)
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
                                Brak obrazu OG (1200×630px)
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
                        <th scope="row">Włącz okruszki (Breadcrumbs)</th>
                        <td>
                            <label>
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_enabled]" value="1" <?php checked( 1, $options['breadcrumbs_enabled'] ?? 0, true ); ?> />
                                Włącz generowanie okruszków dla witryny
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Separator</th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_separator]" value="<?php echo esc_attr( $options['breadcrumbs_separator'] ?? '›' ); ?>" style="width: 50px;" />
                            <p class="description">Znak oddzielający elementy ścieżki (np. ›, », -)</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Tekst "Strona Główna"</th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_home_text]" value="<?php echo esc_attr( $options['breadcrumbs_home_text'] ?? 'Strona Główna' ); ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Tekst "Blog"</th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_blog_text]" value="<?php echo esc_attr( $options['breadcrumbs_blog_text'] ?? 'Blog' ); ?>" />
                            <p class="description">Używane, jeśli strona posiada osobną stronę z wpisami.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Tekst dla 404</th>
                        <td>
                            <input type="text" name="atela_seo_options[breadcrumbs_404_text]" value="<?php echo esc_attr( $options['breadcrumbs_404_text'] ?? 'Nie znaleziono' ); ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Szczegóły wyświetlania</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_show_home]" value="1" <?php checked( 1, $options['breadcrumbs_show_home'] ?? 1, true ); ?> />
                                Pokaż element "Strona Główna"
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_show_current]" value="1" <?php checked( 1, $options['breadcrumbs_show_current'] ?? 1, true ); ?> />
                                Pokaż bieżącą stronę (ostatni element)
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="atela_seo_options[breadcrumbs_show_blog]" value="1" <?php checked( 1, $options['breadcrumbs_show_blog'] ?? 1, true ); ?> />
                                Pokaż stronę bloga przed kategoriami
                            </label>
                        </td>
                    </tr>
                </table>

                <div style="max-width:800px; padding:15px 20px; background:#fff; border-left:4px solid #0073aa; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-top:20px;">
                    <h3 style="margin-top:0; font-size:14px;">🛠 Jak osadzić okruszki na stronie?</h3>
                    <ul style="margin-bottom:0; padding-left:20px;">
                        <li style="margin-bottom:5px;"><strong>W Elementorze:</strong> Użyj naszego dedykowanego widgetu <b>Atela SEO Okruszki</b> w zakładce widgetów.</li>
                        <li style="margin-bottom:5px;"><strong>Shortcode:</strong> <code>[atela_seo_breadcrumbs]</code> (do wstawienia w Gutenbergu lub klasycznym edytorze).</li>
                        <li><strong>W pliku motywu (PHP):</strong> <code>&lt;?php if ( function_exists('atela_seo_breadcrumbs') ) atela_seo_breadcrumbs(); ?&gt;</code></li>
                    </ul>
                </div>

                <div class="aseo-preview-wrap" style="max-width:800px; padding:20px; background:#f9f9f9; border:1px solid #ddd; margin-top:30px;">
                    <h3 style="margin-top:0;">👁️ Podgląd Live</h3>
                    <p class="description" style="margin-bottom:15px;">Tak mogą wyglądać okruszki na Twojej stronie (wygląd zależy też od Twojego motywu).</p>
                    <nav class="atela-seo-breadcrumbs-preview" style="font-size:14px; color:#555;">
                        <span><a href="#" style="color:#0073aa;text-decoration:none;" id="aseo_bc_home_preview">Strona Główna</a></span>
                        <span class="aseo-bc-sep" style="margin:0 8px;opacity:0.6;">›</span>
                        <span><a href="#" style="color:#0073aa;text-decoration:none;" id="aseo_bc_blog_preview">Blog</a></span>
                        <span class="aseo-bc-sep" style="margin:0 8px;opacity:0.6;">›</span>
                        <span style="font-weight:bold;color:#111;" id="aseo_bc_current_preview">Przykładowy wpis</span>
                    </nav>
                </div>
                <?php endif; ?>

                <?php if ( $active_tab == 'sitemap' ) : ?>
                <?php
                $sitemap_url = home_url( '/sitemap.xml' );
                $last_ping   = get_option( 'atela_seo_last_ping', '' );
                $post_types  = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
                ?>
                <div style="max-width:800px; padding:14px 20px; background:#fff; border-left:4px solid #2271b1; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px;">
                    <strong>🗺️ Twoja mapa witryny:</strong>
                    <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" style="margin-left:8px;"><?php echo esc_html( $sitemap_url ); ?></a>
                </div>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Włącz XML Sitemap</th>
                        <td>
                            <label>
                                <input type="checkbox" name="atela_seo_options[sitemap_enabled]" value="1" <?php checked( 1, $options['sitemap_enabled'] ?? 1, true ); ?> />
                                Generuj automatyczną mapę witryny `sitemap.xml`
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Tryb sitemapy</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="radio" name="atela_seo_options[sitemap_mode]" value="single" <?php checked( 'single', $options['sitemap_mode'] ?? 'single' ); ?> />
                                <strong>Pojedynczy plik</strong> &ndash; <code>sitemap.xml</code> ze wszystkimi URL-ami (polecany dla małych witryn)
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="atela_seo_options[sitemap_mode]" value="index" <?php checked( 'index', $options['sitemap_mode'] ?? 'single' ); ?> />
                                <strong>Indeks sitemap</strong> &ndash; <code>sitemap_index.xml</code> + osobne pliki per typ treści (polecany dla dużych witryn)
                            </label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Zawartość sitemapy</th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_posts]" value="1" <?php checked( 1, $options['sitemap_include_posts'] ?? 1, true ); ?> />
                                Wpisy (Posts)
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_pages]" value="1" <?php checked( 1, $options['sitemap_include_pages'] ?? 1, true ); ?> />
                                Strony (Pages)
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_categories]" value="1" <?php checked( 1, $options['sitemap_include_categories'] ?? 1, true ); ?> />
                                Kategorie i tagi
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="atela_seo_options[sitemap_include_images]" value="1" <?php checked( 1, $options['sitemap_include_images'] ?? 1, true ); ?> />
                                Dodaj obrazy (tagi &lt;image:image&gt;)
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="atela_seo_options[sitemap_exclude_noindex]" value="1" <?php checked( 1, $options['sitemap_exclude_noindex'] ?? 1, true ); ?> />
                                Pomiń strony z noindex
                            </label>
                        </td>
                    </tr>
                    <?php if ( ! empty( $post_types ) ) : ?>
                    <tr valign="top">
                        <th scope="row">Typy wpisów (CPT)</th>
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
                        <th scope="row">Częstotliwość zmian</th>
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
                        <th scope="row">Priorytety</th>
                        <td>
                            <table style="border-spacing:8px 4px;">
                                <tr><td style="color:#666;font-size:13px;">Strona główna:</td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_home]" value="<?php echo esc_attr( $options['sitemap_priority_home'] ?? '1.0' ); ?>" style="width:65px;"></td></tr>
                                <tr><td style="color:#666;font-size:13px;">Wpisy:</td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_posts]" value="<?php echo esc_attr( $options['sitemap_priority_posts'] ?? '0.8' ); ?>" style="width:65px;"></td></tr>
                                <tr><td style="color:#666;font-size:13px;">Strony:</td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_pages]" value="<?php echo esc_attr( $options['sitemap_priority_pages'] ?? '0.7' ); ?>" style="width:65px;"></td></tr>
                                <tr><td style="color:#666;font-size:13px;">Kategorie:</td><td><input type="number" step="0.1" min="0" max="1" name="atela_seo_options[sitemap_priority_tax]" value="<?php echo esc_attr( $options['sitemap_priority_tax'] ?? '0.5' ); ?>" style="width:65px;"></td></tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div style="max-width:800px; padding:16px 20px; background:#f9f9f9; border:1px solid #ddd; margin-top:20px;">
                    <h3 style="margin-top:0; font-size:14px;">🔔 Pinguj wyszukiwarki</h3>
                    <p class="description" style="margin-bottom:12px;">Wyślij powiadomienie do Google i Bing, że Twoja mapa witryny została zaktualizowana.</p>
                    <?php if ( $last_ping ) : ?>
                    <p style="margin-bottom:10px; font-size:12px; color:#666;">Ostatni ping: <?php echo esc_html( $last_ping ); ?></p>
                    <?php endif; ?>
                    <button type="button" id="aseo_ping_btn" class="button button-secondary">🔔 Pinguj Google i Bing</button>
                    <span id="aseo_ping_result" style="margin-left:10px; font-size:13px;"></span>
                </div>
                <?php endif; ?>

                <?php if ( $active_tab == 'schema' ) : ?>
                <div style="max-width:800px; padding:14px 20px; background:#fff; border-left:4px solid #2271b1; box-shadow:0 1px 1px rgba(0,0,0,.04); margin-bottom:20px;">
                    <strong>🧠 Schema.org JSON-LD</strong> &ndash; dane strukturalne wstrzykiwane automatycznie do <code>&lt;head&gt;</code> każdej strony. Pomagają Google wyświetlać <em>rich snippets</em> (np. autora, datę artykułu, logo organizacji).
                </div>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Nazwa organizacji</th>
                        <td>
                            <input type="text" name="atela_seo_options[schema_org_name]" value="<?php echo esc_attr( $options['schema_org_name'] ?? get_bloginfo('name') ); ?>" class="regular-text" />
                            <p class="description">Pełna nazwa Twojej firmy lub organizacji.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">URL organizacji</th>
                        <td>
                            <input type="url" name="atela_seo_options[schema_org_url]" value="<?php echo esc_attr( $options['schema_org_url'] ?? home_url('/') ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Logo organizacji (URL)</th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <input type="url" id="aseo_schema_logo" name="atela_seo_options[schema_org_logo]" value="<?php echo esc_attr( $options['schema_org_logo'] ?? '' ); ?>" class="regular-text" />
                                <button type="button" class="button" id="aseo_schema_logo_btn">Wybierz logo</button>
                            </div>
                            <?php if ( ! empty( $options['schema_org_logo'] ) ) : ?>
                            <img src="<?php echo esc_url( $options['schema_org_logo'] ); ?>" style="max-height:60px;margin-top:8px;display:block;border:1px solid #ddd;padding:4px;background:#fff;" />
                            <?php endif; ?>
                            <p class="description">Zalecane: prostokąt lub kwadrat, min. 112px wysokości.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">E-mail kontaktowy</th>
                        <td>
                            <input type="email" name="atela_seo_options[schema_org_email]" value="<?php echo esc_attr( $options['schema_org_email'] ?? '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Telefon</th>
                        <td>
                            <input type="text" name="atela_seo_options[schema_org_phone]" value="<?php echo esc_attr( $options['schema_org_phone'] ?? '' ); ?>" class="regular-text" />
                            <p class="description">Format międzynarodowy, np. +48123456789</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Profile społecznościowe (sameAs)</th>
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

                <?php submit_button(); ?>
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
		echo '<label for="atela_seo_title" style="display:block;font-weight:bold;margin-bottom:5px;">SEO Title</label>';
		echo '<input type="text" id="atela_seo_title" name="atela_seo_title" value="' . esc_attr( $title ) . '" style="width:100%;" />';
		echo '<p class="description" style="margin-top:4px;">Dostępne zmienne: %title%, %site_name%, %site_desc%, %sep%, %page%</p>';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_description" style="display:block;font-weight:bold;margin-bottom:5px;">Meta Description</label>';
		echo '<textarea id="atela_seo_description" name="atela_seo_description" style="width:100%;height:70px;">' . esc_textarea( $description ) . '</textarea>';
		echo '<p class="description" style="margin-top:4px;">Dostępne zmienne j.w.</p>';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_focus_keyword" style="display:block;font-weight:bold;margin-bottom:5px;">Fraza kluczowa</label>';
		echo '<input type="text" id="atela_seo_focus_keyword" name="atela_seo_focus_keyword" value="' . esc_attr( $focus_keyword ) . '" style="width:100%;" />';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_canonical" style="display:block;font-weight:bold;margin-bottom:5px;">Canonical URL <span style="font-weight:normal;color:#666;">(opcjonalnie)</span></label>';
		echo '<input type="text" id="atela_seo_canonical" name="atela_seo_canonical" value="' . esc_attr( $canonical ) . '" style="width:100%;" placeholder="Pozostaw puste dla domyślnego" />';
		echo '</div>';

		echo '<div style="margin-bottom:14px;">';
		echo '<label><input type="checkbox" name="atela_seo_noindex" value="1" ' . checked( 1, $noindex, false ) . ' /> Blokuj indeksowanie (noindex)</label>';
		echo '</div>';

		// ---- SOCIAL ----
		echo '<div style="border-bottom:1px solid #eee;margin-bottom:14px;padding-bottom:4px;"><strong>📣 Social Media (Open Graph / Twitter)</strong></div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_og_title" style="display:block;font-weight:bold;margin-bottom:5px;">OG Title <span style="font-weight:normal;color:#666;">(domyślnie: SEO Title)</span></label>';
		echo '<input type="text" id="atela_seo_og_title" name="atela_seo_og_title" value="' . esc_attr( $og_title ) . '" style="width:100%;" />';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label for="atela_seo_og_description" style="display:block;font-weight:bold;margin-bottom:5px;">OG Description <span style="font-weight:normal;color:#666;">(domyślnie: Meta Description)</span></label>';
		echo '<textarea id="atela_seo_og_description" name="atela_seo_og_description" style="width:100%;height:70px;">' . esc_textarea( $og_description ) . '</textarea>';
		echo '</div>';

		echo '<div style="margin-bottom:10px;">';
		echo '<label style="display:block;font-weight:bold;margin-bottom:5px;">OG Image <span style="font-weight:normal;color:#666;">(1200×630px, domyślnie: featured image)</span></label>';
		echo '<input type="hidden" id="atela_seo_og_image_id" name="atela_seo_og_image_id" value="' . esc_attr( $og_image_id ) . '" />';
		if ( $og_image_url ) {
			echo '<div id="atela_seo_og_image_preview"><img src="' . esc_url( $og_image_url ) . '" style="max-width:200px;display:block;margin-bottom:8px;border:1px solid #ddd;padding:2px;" /></div>';
		} else {
			echo '<div id="atela_seo_og_image_preview"></div>';
		}
		echo '<button type="button" class="button" id="atela_seo_og_image_btn">Wybierz obraz OG</button> ';
		echo '<button type="button" class="button button-link-delete" id="atela_seo_og_image_clear" style="' . ( $og_image_id ? '' : 'display:none;' ) . '">Usuń</button>';
		echo '<p class="description">Adres URL obrazu (1200x630px). Zostaw puste – użyje featured image strony.</p>';
		echo '<p class="description" style="color:#0071a1;">💡 <strong>Automatyczne marginesy:</strong> System automatycznie utworzy tło dla niepasujących obrazków, dzięki czemu żadne elementy (np. logo czy napisy) nie zostaną ucięte po udostępnieniu na FB/Twitterze.</p>';
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
				<strong>👁️ Podgląd Live</strong>
				<label class="aseo-device-toggle" style="display:flex;align-items:center;cursor:pointer;font-size:12px;color:#555;">
					<span class="aseo-toggle-label aseo-toggle-mobile" style="margin-right:6px;font-weight:600;color:#1d2327;">Mobile</span>
					<div style="position:relative;width:36px;height:20px;background:#b5bbc3;border-radius:20px;margin:0 4px;transition:0.3s;" id="aseo_toggle_track">
						<div style="position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:0.3s;" id="aseo_toggle_knob"></div>
					</div>
					<span class="aseo-toggle-label aseo-toggle-desktop" style="margin-left:6px;">Wersja dla komputera</span>
				</label>
			</div>

			<div class="aseo-preview-wrap">
				<div class="aseo-preview-tabs">
					<span class="aseo-preview-tabs__tab active" data-target="atela-seo-metabox-serp">Google</span>
					<span class="aseo-preview-tabs__tab" data-target="atela-seo-metabox-fb">Facebook</span>
					<span class="aseo-preview-tabs__tab" data-target="atela-seo-metabox-tw">Twitter / X</span>
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
						<span title="Długość Tytułu SEO (optymalnie 40-60 znaków)" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;">Tytuł:</span><span class="aseo-serp__title-count"></span></span>
						<span title="Długość Meta Description (optymalnie 120-160 znaków)" style="display:inline-flex;align-items:center;cursor:help;"><span style="font-size:11px;color:#666;margin-right:4px;">Opis:</span><span class="aseo-serp__desc-count"></span></span>
					</div>
				</div>

				<div id="atela-seo-metabox-fb" class="aseo-preview-panel aseo-fb">
					<div class="aseo-fb__image">
						<?php if ( $og_img_large ) : ?>
						<img src="<?php echo esc_url( $og_img_large ); ?>" />
						<?php else : ?>
						<div class="aseo-fb__image-placeholder">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
							Brak obrazu OG
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
							Brak obrazu OG
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
