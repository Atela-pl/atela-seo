/**
 * Atela SEO – Elementor Editor Integration
 *
 * Dodaje nową, samodzielną zakładkę "Atela SEO" do panelu bocznego
 * edytora Elementora (na równi z Ustawienia / Styl / Zaawansowane).
 *
 * Opiera się na wewnętrznym API Elementora (Backbone/Marionette).
 */
( function( $ ) {
	'use strict';

	// Czekamy aż Elementor w pełni się załaduje
	$( window ).on( 'elementor:init', function() {

		// ---------------------------------------------------------------------------
		// 1. REJESTRACJA ZAKŁADKI – dodajemy nową zakładkę do panelu ustawień strony
		// ---------------------------------------------------------------------------
		elementor.hooks.addFilter(
			'panel/pages/menu/items',
			function( menuItems ) {
				return menuItems;
			}
		);

		// Główny hoook Elementora do zarządzania zakładkami w PageSettings
		var AlphaSeoTabView = elementorModules.editor.views.BaseSettings.extend( {
			id: 'panel-atela-seo',

			getTemplateId: function() {
				return 'tmpl-atela-seo-panel';
			},
		} );

		// ---------------------------------------------------------------------------
		// 2. WIDOK ZAKŁADKI – szablon Backbone dla zawartości naszej zakładki
		// ---------------------------------------------------------------------------
		var AlphaSeoView = Backbone.View.extend( {
			el: '#elementor-atela-seo-panel',
			className: 'elementor-panel-scheme-item',

			events: {
				'input  #atela-seo-title':        'onTitleChange',
				'input  #atela-seo-description':  'onDescChange',
				'change #atela-seo-noindex':       'onNoindexChange',
				'click  #atela-seo-save-btn':      'onSave',
			},

			initialize: function() {
				this.postId = elementor.config.document.id;
				this.nonce  = alphaSeoPanelData.nonce;
				this.render();
				this.loadData();
			},

			render: function() {
				var html = wp.template( 'atela-seo-panel' )( {} );
				$( '#atela-seo-panel-placeholder' ).html( html );
			},

			loadData: function() {
				var self = this;
				$.ajax( {
					url:  alphaSeoPanelData.ajax_url,
					type: 'POST',
					data: {
						action:   'atela_seo_get_post_meta',
						post_id:  self.postId,
						nonce:    self.nonce,
					},
					success: function( response ) {
						if ( response.success ) {
							$( '#atela-seo-title' ).val( response.data.title );
							$( '#atela-seo-description' ).val( response.data.description );
							$( '#atela-seo-noindex' ).prop( 'checked', response.data.noindex );
							self.updateCounters();
						}
					},
				} );
			},

			updateCounters: function() {
				var titleLen = $( '#atela-seo-title' ).val().length;
				var descLen  = $( '#atela-seo-description' ).val().length;

				$( '#atela-seo-title-count' ).text( titleLen + ' / 60' )
					.toggleClass( 'atela-seo-count--ok',  titleLen >= 40 && titleLen <= 60 )
					.toggleClass( 'atela-seo-count--warn', titleLen > 0 && titleLen < 40 )
					.toggleClass( 'atela-seo-count--bad',  titleLen > 60 );

				$( '#atela-seo-desc-count' ).text( descLen + ' / 160' )
					.toggleClass( 'atela-seo-count--ok',  descLen >= 120 && descLen <= 160 )
					.toggleClass( 'atela-seo-count--warn', descLen > 0 && descLen < 120 )
					.toggleClass( 'atela-seo-count--bad',  descLen > 160 );
			},

			onTitleChange:   function() { this.updateCounters(); },
			onDescChange:    function() { this.updateCounters(); },
			onNoindexChange: function() {},

			onSave: function() {
				var self = this;
				var $btn = $( '#atela-seo-save-btn' );
				$btn.text( 'Zapisuję...' ).prop( 'disabled', true );

				$.ajax( {
					url:  alphaSeoPanelData.ajax_url,
					type: 'POST',
					data: {
						action:      'atela_seo_save_post_meta',
						post_id:     self.postId,
						nonce:       self.nonce,
						title:       $( '#atela-seo-title' ).val(),
						description: $( '#atela-seo-description' ).val(),
						noindex:     $( '#atela-seo-noindex' ).is( ':checked' ) ? 1 : 0,
						canonical:   $( '#atela-seo-canonical' ).val(),
						focus_keyword: $( '#atela-seo-focus_keyword' ).val(),
					},
					success: function( response ) {
						if ( response.success ) {
							$btn.text( '✅ Zapisano!' );
						} else {
							$btn.text( '❌ Błąd zapisu' );
						}
						setTimeout( function() {
							$btn.text( 'Zapisz ustawienia SEO' ).prop( 'disabled', false );
						}, 2000 );
					},
					error: function() {
						$btn.text( '❌ Błąd połączenia' ).prop( 'disabled', false );
					},
				} );
			},
		} );

		// ---------------------------------------------------------------------------
		// 3. HOOK DO PANELU – wstrzykujemy zakładkę do górnego paska nawigacji
		// ---------------------------------------------------------------------------
		elementor.hooks.addAction( 'panel/open_editor/widget', function() {} );

		// Czekamy na załadowanie UI edytora i wstrzykujemy zakładkę
		elementor.once( 'document:loaded', function() {
			injectAlphaSeoTab();
		} );

		// Fallback – jeśli 'document:loaded' już minął
		if ( elementor.documents && elementor.documents.getCurrent && elementor.documents.getCurrent() ) {
			injectAlphaSeoTab();
		}

		function injectAlphaSeoTab() {
			// Sprawdzamy czy zakładka nie jest już dodana
			if ( $( '#elementor-panel-atela-seo-tab' ).length ) {
				return;
			}

			var $tabsWrapper = $( '#elementor-panel-page-settings-menu-sticky, .elementor-panel-navigation' ).first();

			if ( ! $tabsWrapper.length ) {
				// Spróbuj ponownie po chwili, bo UI może się jeszcze ładować
				setTimeout( injectAlphaSeoTab, 800 );
				return;
			}

			// Tworzymy przycisk zakładki
			var $tab = $(
				'<div id="elementor-panel-atela-seo-tab" ' +
				'class="elementor-component-tab elementor-panel-navigation-tab" ' +
				'data-tab="atela-seo" ' +
				'title="Atela SEO">' +
				'<i class="eicon-meta-data"></i>' +
				'<span class="atela-seo-tab-label">Atela SEO</span>' +
				'</div>'
			);

			$tabsWrapper.append( $tab );

			// Tworzymy kontener na zawartość zakładki
			var $panelContent = $(
				'<div id="atela-seo-panel-placeholder" ' +
				'class="atela-seo-panel-content" ' +
				'style="display:none;">' +
				'</div>'
			);

			$( '#elementor-panel-inner, .elementor-panel-inner' ).first().append( $panelContent );

			// Obsługa kliknięcia
			$tab.on( 'click', function() {
				// Dezaktywuj wszystkie inne zakładki
				$( '.elementor-panel-navigation-tab' ).removeClass( 'elementor-active' );
				$tab.addClass( 'elementor-active' );

				// Ukryj domyślne sekcje panelu
				$( '#elementor-panel-page-settings-sticky-bar, ' +
				   '#elementor-panel-page-settings-controls, ' +
				   '.elementor-panel-scheme-items, ' +
				   '.elementor-panel-footer-inner' ).hide();

				// Pokaż nasz panel
				$( '#atela-seo-panel-placeholder' ).show();

				// Inicjalizuj widok jeśli nie był jeszcze zainicjowany
				if ( ! $( '#atela-seo-panel-placeholder' ).data( 'atela-seo-initialized' ) ) {
					renderAlphaSeoPanel();
					$( '#atela-seo-panel-placeholder' ).data( 'atela-seo-initialized', true );
				}
			} );

			// Przy kliknięciu innych zakładek – chowamy nasz panel
			$( document ).on( 'click', '.elementor-panel-navigation-tab:not(#elementor-panel-atela-seo-tab)', function() {
				$( '#atela-seo-panel-placeholder' ).hide();
				$( '#elementor-panel-page-settings-sticky-bar, ' +
				   '#elementor-panel-page-settings-controls, ' +
				   '.elementor-panel-scheme-items, ' +
				   '.elementor-panel-footer-inner' ).show();
			} );
		}

		// ---------------------------------------------------------------------------
		// 4. RENDEROWANIE ZAWARTOŚCI PANELU
		// ---------------------------------------------------------------------------
		function renderAlphaSeoPanel() {
			var postId = elementor.config.document.id;
			var nonce  = alphaSeoPanelData.nonce;

			var panelHtml =
				'<div class="atela-seo-panel">' +
				'  <div class="atela-seo-header">' +
				'    <span class="atela-seo-icon">🌟</span>' +
				'    <span class="atela-seo-title-text">Atela SEO</span>' +
				'  </div>' +
				'  <div class="atela-seo-section">' +
				'    <label class="atela-seo-label">SEO Title</label>' +
				'    <input type="text" id="atela-seo-title" class="atela-seo-input" maxlength="100" placeholder="Tytuł strony dla wyszukiwarek..." />' +
				'    <span id="atela-seo-title-count" class="atela-seo-count">0 / 60</span>' +
				'  </div>' +
				'  <div class="atela-seo-section">' +
				'    <label class="atela-seo-label">Meta Description</label>' +
				'    <textarea id="atela-seo-description" class="atela-seo-textarea" rows="4" maxlength="300" placeholder="Opis strony wyświetlany w wynikach Google..."></textarea>' +
				'    <span id="atela-seo-desc-count" class="atela-seo-count">0 / 160</span>' +
				'  </div>' +
				'  <div class="atela-seo-section atela-seo-section--row">' +
				'    <label class="atela-seo-label-inline">Noindex (blokuj indeksowanie)</label>' +
				'    <label class="atela-seo-toggle">' +
				'      <input type="checkbox" id="atela-seo-noindex" />' +
				'      <span class="atela-seo-toggle-slider"></span>' +
				'    </label>' +
				'  </div>' +
				'  <div class="atela-seo-section">' +
				'    <label class="atela-seo-label">Adres kanoniczny (Canonical URL)</label>' +
				'    <input type="text" id="atela-seo-canonical" class="atela-seo-input" placeholder="Pozostaw puste dla domyślnego..." />' +
				'  </div>' +
				'  <div class="atela-seo-section">' +
				'    <label class="atela-seo-label">Fraza kluczowa (Focus Keyword)</label>' +
				'    <input type="text" id="atela-seo-focus_keyword" class="atela-seo-input" placeholder="Główna fraza do analizy..." />' +
				'  </div>' +
				'  <button id="atela-seo-save-btn" class="atela-seo-btn">Zapisz ustawienia SEO</button>' +
				'</div>';

			$( '#atela-seo-panel-placeholder' ).html( panelHtml );

			// Pobierz dane z serwera
			$.ajax( {
				url:  alphaSeoPanelData.ajax_url,
				type: 'POST',
				data: {
					action:  'atela_seo_get_post_meta',
					post_id: postId,
					nonce:   nonce,
				},
				success: function( response ) {
					if ( response.success ) {
						$( '#atela-seo-title' ).val( response.data.title );
						$( '#atela-seo-description' ).val( response.data.description );
						$( '#atela-seo-noindex' ).prop( 'checked', !! parseInt( response.data.noindex ) );
						$( '#atela-seo-canonical' ).val( response.data.canonical );
						$( '#atela-seo-focus_keyword' ).val( response.data.focus_keyword );
						updateCounters();
					}
				},
			} );

			// Liczniki znaków
			function updateCounters() {
				var titleLen = $( '#atela-seo-title' ).val().length;
				var descLen  = $( '#atela-seo-description' ).val().length;

				var $tc = $( '#atela-seo-title-count' ).text( titleLen + ' / 60' )
					.removeClass( 'atela-seo-count--ok atela-seo-count--warn atela-seo-count--bad' );
				if ( titleLen > 60 )                   $tc.addClass( 'atela-seo-count--bad' );
				else if ( titleLen >= 40 )             $tc.addClass( 'atela-seo-count--ok' );
				else if ( titleLen > 0 )               $tc.addClass( 'atela-seo-count--warn' );

				var $dc = $( '#atela-seo-desc-count' ).text( descLen + ' / 160' )
					.removeClass( 'atela-seo-count--ok atela-seo-count--warn atela-seo-count--bad' );
				if ( descLen > 160 )                   $dc.addClass( 'atela-seo-count--bad' );
				else if ( descLen >= 120 )             $dc.addClass( 'atela-seo-count--ok' );
				else if ( descLen > 0 )                $dc.addClass( 'atela-seo-count--warn' );
			}

			$( document ).on( 'input', '#atela-seo-title, #atela-seo-description', updateCounters );

			// Zapis
			$( document ).on( 'click', '#atela-seo-save-btn', function() {
				var $btn = $( this );
				$btn.text( 'Zapisuję...' ).prop( 'disabled', true );

				$.ajax( {
					url:  alphaSeoPanelData.ajax_url,
					type: 'POST',
					data: {
						action:      'atela_seo_save_post_meta',
						post_id:     postId,
						nonce:       nonce,
						title:       $( '#atela-seo-title' ).val(),
						description: $( '#atela-seo-description' ).val(),
						noindex:     $( '#atela-seo-noindex' ).is( ':checked' ) ? 1 : 0,
						canonical:   $( '#atela-seo-canonical' ).val(),
						focus_keyword: $( '#atela-seo-focus_keyword' ).val(),
					},
					success: function( response ) {
						$btn.text( response.success ? '✅ Zapisano!' : '❌ Błąd zapisu' );
						setTimeout( function() {
							$btn.text( 'Zapisz ustawienia SEO' ).prop( 'disabled', false );
						}, 2500 );
					},
					error: function() {
						$btn.text( '❌ Błąd połączenia' ).prop( 'disabled', false );
					},
				} );
			} );
		}

	} ); // end elementor:init

} )( jQuery );
