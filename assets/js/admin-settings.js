jQuery(function($){
	// 1. OG Image Picker (Ustawienia ogólne)
	var frame;
	$('#atela_seo_og_image_btn').not('#atela_seo_meta_box #atela_seo_og_image_btn').on('click', function(e){
		e.preventDefault();
		if(frame){frame.open();return;}
		frame = wp.media({ title: 'Wybierz obraz OG', button:{text:'Wybierz'}, multiple:false });
		frame.on('select', function(){
			var a = frame.state().get('selection').first().toJSON();
			$('#atela_seo_og_default_image_id').val(a.id);
			$('#atela_seo_og_default_image_preview').html('<img src="'+a.url+'" style="max-width:200px;display:block;margin-bottom:8px;" />');
			$(document).trigger('atela_seo_global_image_changed', [a.url]);
		});
		frame.open();
	});
	$('#atela_seo_og_image_clear').not('#atela_seo_meta_box #atela_seo_og_image_clear').on('click', function(e){
		e.preventDefault();
		$('#atela_seo_og_default_image_id').val('');
		$('#atela_seo_og_default_image_preview').html('');
		$(document).trigger('atela_seo_global_image_changed', ['']);
	});

	// 2. Podgląd na żywo dla okruszków (Breadcrumbs)
	function updateBcPreview() {
		var sep = $('input[name="atela_seo_options[breadcrumbs_separator]"]').val() || '›';
		var homeText = $('input[name="atela_seo_options[breadcrumbs_home_text]"]').val() || 'Strona Główna';
		var blogText = $('input[name="atela_seo_options[breadcrumbs_blog_text]"]').val() || 'Blog';
		
		var showHome = $('input[name="atela_seo_options[breadcrumbs_show_home]"]').is(':checked');
		var showCurrent = $('input[name="atela_seo_options[breadcrumbs_show_current]"]').is(':checked');
		var showBlog = $('input[name="atela_seo_options[breadcrumbs_show_blog]"]').is(':checked');

		$('.aseo-bc-sep').text(sep);
		$('#aseo_bc_home_preview').text(homeText);
		$('#aseo_bc_blog_preview').text(blogText);

		$('#aseo_bc_home_preview').parent().toggle(showHome);
		$('#aseo_bc_home_preview').parent().next('.aseo-bc-sep').toggle(showHome && (showBlog || showCurrent));
		
		$('#aseo_bc_blog_preview').parent().toggle(showBlog);
		$('#aseo_bc_blog_preview').parent().next('.aseo-bc-sep').toggle(showBlog && showCurrent);
		
		$('#aseo_bc_current_preview').toggle(showCurrent);
	}
	
	$('input[name^="atela_seo_options[breadcrumbs"]').on('input change', updateBcPreview);
	if ($('.atela-seo-breadcrumbs-preview').length) {
		updateBcPreview();
	}

	// 3. Ping Search Engines (Sitemap)
	$('#aseo_ping_btn').on('click', function(e){
		e.preventDefault();
		var $btn = $(this);
		$btn.prop('disabled', true).text('Pingowanie...');
		if (typeof alphaAdminSettings !== 'undefined') {
			$.post(ajaxurl, {
				action: 'atela_seo_ping_search_engines',
				nonce: alphaAdminSettings.ping_nonce
			}, function(resp){
				$btn.prop('disabled', false).text('🔔 Pinguj Google i Bing');
				if(resp.success){
					$('#aseo_ping_result').css('color','#0a6b0a').text('✓ ' + resp.data);
				} else {
					$('#aseo_ping_result').css('color','#c00').text('✗ ' + resp.data);
				}
			});
		}
	});

	// 4. Schema Logo Picker
	$('#aseo_schema_logo_btn').on('click', function(e){
		e.preventDefault();
		var schemaFrame = wp.media({ title: 'Wybierz logo', button: { text: 'Użyj' }, multiple: false });
		schemaFrame.on('select', function(){
			var url = schemaFrame.state().get('selection').first().toJSON().url;
			$('#aseo_schema_logo').val(url);
		});
		schemaFrame.open();
	});

	// 5. OG Image Picker w Meta Box (Edytor wpisu)
	var alphaOgFrame;
	$(document).on('click', '#atela_seo_meta_box #atela_seo_og_image_btn', function(e){
		e.preventDefault();
		if(alphaOgFrame){alphaOgFrame.open();return;}
		alphaOgFrame = wp.media({title:'Wybierz obraz OG',button:{text:'Wybierz'},multiple:false});
		alphaOgFrame.on('select',function(){
			var a=alphaOgFrame.state().get('selection').first().toJSON();
			$('#atela_seo_og_image_id').val(a.id);
			$('#atela_seo_og_image_preview').html('<img src="'+a.url+'" style="max-width:200px;display:block;margin-bottom:8px;border:1px solid #ddd;padding:2px;" />');
			$('#atela_seo_og_image_clear').show();
			$(document).trigger('atela_seo_og_image_changed', [a.url]);
		});
		alphaOgFrame.open();
	});
	
	$(document).on('click', '#atela_seo_meta_box #atela_seo_og_image_clear', function(e){
		e.preventDefault();
		$('#atela_seo_og_image_id').val('');
		$('#atela_seo_og_image_preview').html('');
		$(this).hide();
		var fallback_img = '';
		if (typeof alphaAdminSettings !== 'undefined' && alphaAdminSettings.post_thumbnail_url) {
			fallback_img = alphaAdminSettings.post_thumbnail_url;
		}
		$(document).trigger('atela_seo_og_image_changed', [fallback_img]);
	});
});
