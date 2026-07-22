/**
 * Atela SEO – Live Previews
 * Podgląd SERP (Google), Facebook OG Card i Twitter Card
 * aktualizowany w czasie rzeczywistym podczas wpisywania danych.
 */
( function( $ ) {
	'use strict';

	/* =========================================================================
	 * HELPERS
	 * ======================================================================= */
	function truncate( text, maxLen ) {
		if ( ! text ) return '';
		return text.length > maxLen ? text.substring( 0, maxLen - 1 ) + '…' : text;
	}

	function escHtml( str ) {
		return $( '<div>' ).text( str ).html();
	}

	/* =========================================================================
	 * 1. GOOGLE SERP PREVIEW
	 * ======================================================================= */
	function initSerpPreview() {
		var $container = $( '#atela-seo-serp-preview' );
		if ( ! $container.length ) return;

		var $title    = $( '#atela_seo_serp_title_source, [name="atela_seo_options[home_title]"]' ).first();
		var $desc     = $( '#atela_seo_serp_desc_source, [name="atela_seo_options[home_description]"]' ).first();
		var $titleIn  = $container.find( '.aseo-serp__title' );
		var $descIn   = $container.find( '.aseo-serp__description' );
		var siteUrl   = alphaAdminPreview.site_url || window.location.origin;
		var siteName  = alphaAdminPreview.site_name || '';

		function update() {
			var rawTitle = $title.length ? $title.val() : alphaAdminPreview.home_title;
			var rawDesc  = $desc.length ? $desc.val() : alphaAdminPreview.home_desc;
			var currentSep = $( 'select[name="atela_seo_options[separator]"]' ).length ? $( 'select[name="atela_seo_options[separator]"]' ).val() : ( alphaAdminPreview.separator || '-' );

			// Podmień proste zmienne
			rawTitle = rawTitle
				.replace( /%title%/g, siteName )
				.replace( /%site_name%/g, siteName )
				.replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' )
				.replace( /%sep%/g, currentSep )
				.replace( /%page%/g, '' )
				.replace( /\s+/g, ' ' ).trim();

			rawDesc = rawDesc
				.replace( /%title%/g, siteName )
				.replace( /%site_name%/g, siteName )
				.replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' )
				.replace( /%sep%/g, currentSep )
				.replace( /%page%/g, '' )
				.replace( /\s+/g, ' ' ).trim();

			var displayTitle = truncate( rawTitle, 60 );
			var displayDesc  = truncate( rawDesc, 160 );

			var titleLen = rawTitle.length;
			var descLen  = rawDesc.length;

			$titleIn.text( displayTitle || '(brak tytułu)' );
			$descIn.text( displayDesc || '(brak opisu)' );

			// Kolorowanie licznika
			$container.find( '.aseo-serp__title-count' )
				.text( titleLen + ' / 60' )
				.removeClass( 'ok warn bad' )
				.addClass( titleLen > 60 ? 'bad' : ( titleLen >= 40 ? 'ok' : ( titleLen > 0 ? 'warn' : '' ) ) );

			$container.find( '.aseo-serp__desc-count' )
				.text( descLen + ' / 160' )
				.removeClass( 'ok warn bad' )
				.addClass( descLen > 160 ? 'bad' : ( descLen >= 120 ? 'ok' : ( descLen > 0 ? 'warn' : '' ) ) );
		}

		$title.add( $desc ).on( 'input', update );
		$( 'select[name="atela_seo_options[separator]"]' ).on( 'change', update );
		update();
	}

	/* =========================================================================
	 * 2. FACEBOOK OG PREVIEW
	 * ======================================================================= */
	function initFbPreview() {
		var $container = $( '#atela-seo-fb-preview' );
		if ( ! $container.length ) return;

		var $title    = $( '#atela_seo_fb_title_source, [name="atela_seo_options[home_title]"]' ).first();
		var $desc     = $( '#atela_seo_fb_desc_source, [name="atela_seo_options[home_description]"]' ).first();
		var siteUrl   = ( alphaAdminPreview.site_url || '' ).replace( /^https?:\/\//, '' ).split( '/' )[0];

		function update() {
			var rawTitle = $title.length ? $title.val() : alphaAdminPreview.home_title;
			var rawDesc  = $desc.length ? $desc.val() : alphaAdminPreview.home_desc;
			var currentSep = $( 'select[name="atela_seo_options[separator]"]' ).length ? $( 'select[name="atela_seo_options[separator]"]' ).val() : ( alphaAdminPreview.separator || '-' );
			
			// Prosta zamiana zmiennych
			var siteName = alphaAdminPreview.site_name || '';
			rawTitle = rawTitle.replace( /%title%/g, siteName ).replace( /%site_name%/g, siteName ).replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' ).replace( /%sep%/g, currentSep ).replace( /%page%/g, '' ).trim();
			rawDesc  = rawDesc.replace( /%title%/g, siteName ).replace( /%site_name%/g, siteName ).replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' ).replace( /%sep%/g, currentSep ).replace( /%page%/g, '' ).trim();

			var t = truncate( rawTitle || siteName, 88 );
			var d = truncate( rawDesc, 110 );

			$container.find( '.aseo-fb__title' ).text( t || '(brak tytułu)' );
			$container.find( '.aseo-fb__desc' ).text( d );
			$container.find( '.aseo-fb__domain' ).text( siteUrl.toUpperCase() );
		}

		$title.add( $desc ).on( 'input', update );
		$( 'select[name="atela_seo_options[separator]"]' ).on( 'change', update );
		update();
	}

	/* =========================================================================
	 * 3. TWITTER CARD PREVIEW
	 * ======================================================================= */
	function initTwitterPreview() {
		var $container = $( '#atela-seo-twitter-preview' );
		if ( ! $container.length ) return;

		var $title  = $( '#atela_seo_twitter_title_source, [name="atela_seo_options[home_title]"]' ).first();
		var $desc   = $( '#atela_seo_twitter_desc_source, [name="atela_seo_options[home_description]"]' ).first();
		var siteUrl = ( alphaAdminPreview.site_url || '' ).replace( /^https?:\/\//, '' ).split( '/' )[0];

		function update() {
			var rawTitle = $title.length ? $title.val() : alphaAdminPreview.home_title;
			var rawDesc  = $desc.length ? $desc.val() : alphaAdminPreview.home_desc;
			var currentSep = $( 'select[name="atela_seo_options[separator]"]' ).length ? $( 'select[name="atela_seo_options[separator]"]' ).val() : ( alphaAdminPreview.separator || '-' );

			// Prosta zamiana zmiennych
			var siteName = alphaAdminPreview.site_name || '';
			rawTitle = rawTitle.replace( /%title%/g, siteName ).replace( /%site_name%/g, siteName ).replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' ).replace( /%sep%/g, currentSep ).replace( /%page%/g, '' ).trim();
			rawDesc  = rawDesc.replace( /%title%/g, siteName ).replace( /%site_name%/g, siteName ).replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' ).replace( /%sep%/g, currentSep ).replace( /%page%/g, '' ).trim();

			var t = truncate( rawTitle || siteName, 70 );
			var d = truncate( rawDesc, 125 );

			$container.find( '.aseo-tw__title' ).text( t || '(brak tytułu)' );
			$container.find( '.aseo-tw__desc' ).text( d );
			$container.find( '.aseo-tw__domain' ).text( siteUrl );
		}

		$title.add( $desc ).on( 'input', update );
		$( 'select[name="atela_seo_options[separator]"]' ).on( 'change', update );
		update();
	}

	/* =========================================================================
	 * META BOX PREVIEWS (Classic / Gutenberg)
	 * ======================================================================= */
	function initMetaBoxPreviews() {
		if ( ! $( '#atela-seo-metabox-preview' ).length ) return;

		function getDynamicPostTitle() {
			if ( typeof wp !== 'undefined' && wp.data && wp.data.select( 'core/editor' ) ) {
				var editorTitle = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );
				if ( editorTitle ) return editorTitle;
			}
			var $classicTitle = $( '#title' );
			if ( $classicTitle.length && $classicTitle.val() ) return $classicTitle.val();
			return '';
		}

		function replaceVars( text, dynamicTitle, siteName, sep ) {
			if ( ! text ) return '';
			return text
				.replace( /%title%/g, dynamicTitle || siteName )
				.replace( /%site_name%/g, siteName )
				.replace( /%site_desc%/g, alphaAdminPreview.site_desc || '' )
				.replace( /%sep%/g, sep )
				.replace( /%page%/g, '' )
				.replace( /\s+/g, ' ' ).trim();
		}

		function updateMetaBox() {
			var title   = $( '#atela_seo_title' ).val();
			var desc    = $( '#atela_seo_description' ).val();
			var ogTitle = $( '#atela_seo_og_title' ).val() || title;
			var ogDesc  = $( '#atela_seo_og_description' ).val() || desc;
			var siteName = alphaAdminPreview.site_name || '';
			var siteUrl  = ( alphaAdminPreview.site_url || '' ).replace( /^https?:\/\//, '' ).split( '/' )[0];
			var sep      = alphaAdminPreview.separator || '-';
			var dynamicTitle = getDynamicPostTitle();
			var fallbackTitle = dynamicTitle ? dynamicTitle + ' ' + sep + ' ' + siteName : siteName;

			var parsedTitle = replaceVars( title, dynamicTitle, siteName, sep ) || fallbackTitle;
			var parsedDesc  = replaceVars( desc, dynamicTitle, siteName, sep );
			var parsedOgTitle = replaceVars( ogTitle, dynamicTitle, siteName, sep ) || fallbackTitle;
			var parsedOgDesc  = replaceVars( ogDesc, dynamicTitle, siteName, sep );

			// SERP
			$( '#atela-seo-metabox-serp .aseo-serp__title' ).text( truncate( parsedTitle, 60 ) || '(brak)' );
			$( '#atela-seo-metabox-serp .aseo-serp__description' ).text( truncate( parsedDesc, 160 ) );
			var tl = parsedTitle.length;
			var dl = parsedDesc.length;
			$( '#atela-seo-metabox-serp .aseo-serp__title-count' )
				.text( tl + '/60' )
				.removeClass('ok warn bad')
				.addClass( tl > 60 ? 'bad' : tl >= 40 ? 'ok' : tl > 0 ? 'warn' : '' );
			$( '#atela-seo-metabox-serp .aseo-serp__desc-count' )
				.text( dl + '/160' )
				.removeClass('ok warn bad')
				.addClass( dl > 160 ? 'bad' : dl >= 120 ? 'ok' : dl > 0 ? 'warn' : '' );

			// OG / FB
			$( '#atela-seo-metabox-fb .aseo-fb__title' ).text( truncate( parsedOgTitle, 88 ) || '(brak)' );
			$( '#atela-seo-metabox-fb .aseo-fb__desc' ).text( truncate( parsedOgDesc, 110 ) );
			$( '#atela-seo-metabox-fb .aseo-fb__domain' ).text( siteUrl.toUpperCase() );

			// Twitter
			$( '#atela-seo-metabox-tw .aseo-tw__title' ).text( truncate( parsedOgTitle, 70 ) || '(brak)' );
			$( '#atela-seo-metabox-tw .aseo-tw__desc' ).text( truncate( parsedOgDesc, 125 ) );
			$( '#atela-seo-metabox-tw .aseo-tw__domain' ).text( siteUrl );
		}

		$( '#atela_seo_title, #atela_seo_description, #atela_seo_og_title, #atela_seo_og_description' )
			.on( 'input', updateMetaBox );
			
		// Reaguj na pisanie tytułu w Gutenbergu lub Classic
		$( document ).on( 'input', '.editor-post-title__input, #title', updateMetaBox );
		
		// Subskrypcja Gutenberga dla opóźnionych zmian (np. po załadowaniu edytora)
		if ( typeof wp !== 'undefined' && wp.data ) {
			var lastTitle = '';
			wp.data.subscribe( function() {
				var t = getDynamicPostTitle();
				if ( t !== lastTitle ) {
					lastTitle = t;
					updateMetaBox();
				}
			});
		}

		// Aktualizuj też gdy zmienia się OG image
		$( document ).on( 'atela_seo_og_image_changed', function( e, url ) {
			var $fbImg = $( '#atela-seo-metabox-fb .aseo-fb__image' );
			var $twImg = $( '#atela-seo-metabox-tw .aseo-tw__image' );
			var $serpImg = $( '#atela-seo-metabox-serp .aseo-serp__thumbnail' );

			if ( url ) {
				$fbImg.html( '<img src="' + url + '" />' );
				$twImg.html( '<img src="' + url + '" />' );
				$serpImg.html( '<img src="' + url + '" />' ).css('display', '');
			} else {
				var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
				$fbImg.html( '<div class="aseo-fb__image-placeholder">' + svg + '<br>Brak obrazu OG</div>' );
				$twImg.html( '<div class="aseo-tw__image-placeholder">' + svg + '<br>Brak obrazu OG</div>' );
				$serpImg.html( '' ).css('display', 'none');
			}
			updateMetaBox();
		});

		// Obsługa przełącznika Mobile/Desktop
		var $previewWrap = $( '#atela-seo-metabox-preview' );
		var $toggleTrack = $( '#aseo_toggle_track' );
		var $toggleKnob  = $( '#aseo_toggle_knob' );
		var isDesktop = localStorage.getItem( 'atela_seo_preview_desktop' ) === 'true';

		function applyToggleState() {
			if ( isDesktop ) {
				$previewWrap.removeClass( 'is-mobile' ).addClass( 'is-desktop' );
				$toggleKnob.css( 'left', '18px' );
				$toggleTrack.css( 'background', '#2271b1' );
			} else {
				$previewWrap.removeClass( 'is-desktop' ).addClass( 'is-mobile' );
				$toggleKnob.css( 'left', '2px' );
				$toggleTrack.css( 'background', '#b5bbc3' );
			}
		}
		
		applyToggleState();

		$( '.aseo-device-toggle' ).on( 'click', function(e) {
			e.preventDefault();
			isDesktop = !isDesktop;
			localStorage.setItem( 'atela_seo_preview_desktop', isDesktop );
			applyToggleState();
		});

		updateMetaBox();
	}

	/* =========================================================================
	 * TABS UI (przełączanie zakładek w podglądzie)
	 * ======================================================================= */
	function initPreviewTabs() {
		$( document ).on( 'click', '.aseo-preview-tabs__tab', function() {
			var $tab  = $( this );
			var target = $tab.data( 'target' );
			var $wrap  = $tab.closest( '.aseo-preview-wrap' );

			$wrap.find( '.aseo-preview-tabs__tab' ).removeClass( 'active' );
			$tab.addClass( 'active' );

			$wrap.find( '.aseo-preview-panel' ).hide();
			$wrap.find( '#' + target ).show();
		} );
	}

	/* =========================================================================
	 * GLOBAL IMAGE CHANGE HANDLER
	 * ======================================================================= */
	function initGlobalImageHandler() {
		$( document ).on( 'atela_seo_global_image_changed', function( e, url ) {
			var $fbImg = $( '#atela-seo-fb-preview .aseo-fb__image' );
			var $twImg = $( '#atela-seo-twitter-preview .aseo-tw__image' );

			if ( url ) {
				$fbImg.html( '<img src="' + url + '" />' );
				$twImg.html( '<img src="' + url + '" />' );
			} else {
				var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
				$fbImg.html( '<div class="aseo-fb__image-placeholder">' + svg + '<br>Brak obrazu OG</div>' );
				$twImg.html( '<div class="aseo-tw__image-placeholder">' + svg + '<br>Brak obrazu OG</div>' );
			}
		} );
	}

	/* =========================================================================
	 * INIT
	 * ======================================================================= */
	$( function() {
		initSerpPreview();
		initFbPreview();
		initTwitterPreview();
		initMetaBoxPreviews();
		initPreviewTabs();
		initGlobalImageHandler();
	} );

} )( jQuery );
