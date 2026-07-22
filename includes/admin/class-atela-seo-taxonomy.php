<?php
/**
 * Moduł SEO dla taksonomii (Kategorie, Tagi, własne taksonomie).
 *
 * Dodaje pola meta (title, description, noindex) do ekranów edycji
 * Kategorii i Tagów (i innych publicznych taksonomii).
 * Dane są zapisywane jako term meta i renderowane we front-endzie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atela_SEO_Taxonomy {

	/** @var string[] Taksonomie obsługiwane przez wtyczkę */
	private $taxonomies = array();

	public function __construct() {
		add_action( 'init', array( $this, 'setup_taxonomies' ) );

		// Pola na ekranach edycji kategorii/tagów
		add_action( 'admin_init', array( $this, 'register_term_form_hooks' ) );

		// Zapis meta
		add_action( 'edited_term',  array( $this, 'save_term_meta' ), 10, 3 );
		add_action( 'created_term', array( $this, 'save_term_meta' ), 10, 3 );

		// Renderowanie tagów meta we front-endzie
		add_action( 'wp_head', array( $this, 'render_taxonomy_meta' ), 2 );
	}

	/* -------------------------------------------------------------------------
	 * Ustal listę taksonomii do obsługi
	 * ---------------------------------------------------------------------- */
	public function setup_taxonomies() {
		$public_taxons = get_taxonomies( array( 'public' => true ), 'names' );
		// Pomijamy taksonomie Elementora
		$exclude = array( 'elementor_library_type', 'elementor_library_category' );
		$this->taxonomies = array_values( array_diff( $public_taxons, $exclude ) );
	}

	/* -------------------------------------------------------------------------
	 * Rejestracja hooków formularzy dla każdej taksonomii
	 * ---------------------------------------------------------------------- */
	public function register_term_form_hooks() {
		foreach ( $this->taxonomies as $tax ) {
			// Formularz edycji istniejącego termu
			add_action( "{$tax}_edit_form_fields",   array( $this, 'render_edit_form_fields' ),   10, 2 );
			// Formularz dodawania nowego termu
			add_action( "{$tax}_add_form_fields",    array( $this, 'render_add_form_fields' ),    10, 1 );
		}
	}

	/* -------------------------------------------------------------------------
	 * Pola w formularzu EDYCJI istniejącej kategorii/tagu
	 * ---------------------------------------------------------------------- */
	public function render_edit_form_fields( $term, $taxonomy ) {
		$title       = get_term_meta( $term->term_id, '_atela_seo_title', true );
		$description = get_term_meta( $term->term_id, '_atela_seo_description', true );
		$noindex     = get_term_meta( $term->term_id, '_atela_seo_noindex', true );

		wp_nonce_field( 'atela_seo_term_meta_' . $term->term_id, 'atela_seo_term_nonce' );
		?>
		<tr class="form-field">
			<td colspan="2">
				<hr style="margin:16px 0 8px; border:none; border-top:1px solid #ddd;" />
				<h3 style="margin:0 0 4px; font-size:14px; color:#1d2327;">
					🌟 Atela SEO
				</h3>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="atela_seo_tax_title">Tytuł SEO</label>
			</th>
			<td>
				<input
					type="text"
					id="atela_seo_tax_title"
					name="atela_seo_tax_title"
					value="<?php echo esc_attr( $title ); ?>"
					class="large-text"
					placeholder="<?php echo esc_attr( $term->name ); ?> (domyślny)"
				/>
				<p class="description">Nadpisuje domyślny tytuł strony kategorii/tagu. Zalecane: 50–60 znaków.</p>
				<div style="font-size:11px;margin-top:4px;" id="atela_seo_tax_title_count"></div>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="atela_seo_tax_description">Meta Description</label>
			</th>
			<td>
				<textarea
					id="atela_seo_tax_description"
					name="atela_seo_tax_description"
					rows="3"
					class="large-text"
					placeholder="Opisz tę kategorię/tag (150–160 znaków)..."
				><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description">Krótki opis widoczny w wynikach Google. Zalecane: 150–160 znaków.</p>
				<div style="font-size:11px;margin-top:4px;" id="atela_seo_tax_desc_count"></div>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">Noindex</th>
			<td>
				<label>
					<input
						type="checkbox"
						name="atela_seo_tax_noindex"
						value="1"
						<?php checked( 1, (int) $noindex ); ?>
					/>
					Ukryj tę kategorię/tag w wynikach wyszukiwania (noindex)
				</label>
				<p class="description">Przydatne dla kategorii "Bez kategorii" lub tymczasowych tagów.</p>
			</td>
		</tr>
		<?php $this->render_counter_script(); ?>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * Pola w formularzu DODAWANIA nowej kategorii/tagu
	 * ---------------------------------------------------------------------- */
	public function render_add_form_fields( $taxonomy ) {
		wp_nonce_field( 'atela_seo_term_meta_new', 'atela_seo_term_nonce' );
		?>
		<div class="form-field" style="border-top:1px solid #ddd; margin-top:16px; padding-top:16px;">
			<h3 style="margin:0 0 12px; font-size:14px; color:#1d2327;">🌟 Atela SEO</h3>
		</div>
		<div class="form-field">
			<label for="atela_seo_tax_title_new">Tytuł SEO</label>
			<input
				type="text"
				id="atela_seo_tax_title_new"
				name="atela_seo_tax_title"
				class="large-text"
				placeholder="Zostaw puste, aby użyć domyślnego"
			/>
			<p>Nadpisuje domyślny tytuł strony tej kategorii/tagu.</p>
		</div>
		<div class="form-field">
			<label for="atela_seo_tax_description_new">Meta Description</label>
			<textarea
				id="atela_seo_tax_description_new"
				name="atela_seo_tax_description"
				rows="3"
				class="large-text"
				placeholder="Opis w wynikach Google (150–160 znaków)..."
			></textarea>
		</div>
		<div class="form-field">
			<label>
				<input type="checkbox" name="atela_seo_tax_noindex" value="1" />
				Noindex – ukryj w wynikach wyszukiwania
			</label>
		</div>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * Zapis term meta po zapisaniu formularza
	 * ---------------------------------------------------------------------- */
	public function save_term_meta( $term_id, $tt_id, $taxonomy ) {
		if ( ! isset( $_POST['atela_seo_term_nonce'] ) ) {
			return;
		}

		$nonce_action = isset( $_POST['tag_ID'] )
			? 'atela_seo_term_meta_' . (int) $_POST['tag_ID']
			: 'atela_seo_term_meta_new';

		if ( ! wp_verify_nonce( $_POST['atela_seo_term_nonce'], $nonce_action ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$title       = isset( $_POST['atela_seo_tax_title'] )       ? sanitize_text_field( $_POST['atela_seo_tax_title'] )       : '';
		$description = isset( $_POST['atela_seo_tax_description'] ) ? sanitize_textarea_field( $_POST['atela_seo_tax_description'] ) : '';
		$noindex     = isset( $_POST['atela_seo_tax_noindex'] )     ? 1 : 0;

		update_term_meta( $term_id, '_atela_seo_title',       $title );
		update_term_meta( $term_id, '_atela_seo_description', $description );
		update_term_meta( $term_id, '_atela_seo_noindex',     $noindex );
	}

	/* -------------------------------------------------------------------------
	 * Renderowanie tagów meta we front-endzie (strony taksonomii)
	 * ---------------------------------------------------------------------- */
	public function render_taxonomy_meta() {
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term || ! isset( $term->term_id ) ) {
			return;
		}

		$title       = get_term_meta( $term->term_id, '_atela_seo_title', true );
		$description = get_term_meta( $term->term_id, '_atela_seo_description', true );
		$noindex     = get_term_meta( $term->term_id, '_atela_seo_noindex', true );

		// Noindex
		if ( $noindex ) {
			echo '<meta name="robots" content="noindex, follow" />' . "\n";
		}

		// Meta description
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		// Open Graph dla taksonomii
		if ( $title ) {
			echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		}
		if ( $description ) {
			echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}

	/* -------------------------------------------------------------------------
	 * Liczniki znaków (JS)
	 * ---------------------------------------------------------------------- */
	private function render_counter_script() {
		?>
		<script>
		jQuery(function($){
			function updateCount(inputId, countId, min, max) {
				var len = $(inputId).val().length;
				var color = len === 0 ? '#999' : (len >= min && len <= max ? '#0a6b0a' : (len < min ? '#92400e' : '#c00'));
				$(countId).html('<span style="color:' + color + ';font-weight:600;">' + len + '</span> znaków' + (len > 0 ? ' (' + min + '–' + max + ' zalecane)' : ''));
			}
			$('#atela_seo_tax_title').on('input', function(){ updateCount('#atela_seo_tax_title', '#atela_seo_tax_title_count', 50, 60); }).trigger('input');
			$('#atela_seo_tax_description').on('input', function(){ updateCount('#atela_seo_tax_description', '#atela_seo_tax_desc_count', 150, 160); }).trigger('input');
		});
		</script>
		<?php
	}
}
