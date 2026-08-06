jQuery(function($) {
	$('#atela-cwv-start-btn').on('click', function(e) {
		e.preventDefault();
		var btn = $(this);
		btn.prop('disabled', true);
		$('#atela-cwv-results').hide();
		$('#atela-cwv-loading').show();

		$.post(ajaxurl, {
			action: 'atela_seo_analyze_cwv',
			nonce: atelaSeoCwv.cwv_nonce
		}, function(response) {
			btn.prop('disabled', false);
			$('#atela-cwv-loading').hide();
			if (response.success) {
				$('#atela-cwv-results').html(response.data.html).css('display', 'flex');
			} else {
				alert((typeof atelaCwvL10n !== 'undefined' ? atelaCwvL10n.error : 'Error: ') + response.data);
			}
		});
	});
});
