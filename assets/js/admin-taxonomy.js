jQuery(function($){
	function updateCount(inputId, countId, min, max) {
		var $input = $(inputId);
		if(!$input.length) return;
		var len = $input.val().length;
		var color = len === 0 ? '#999' : (len >= min && len <= max ? '#0a6b0a' : (len < min ? '#92400e' : '#c00'));
		$(countId).html('<span style="color:' + color + ';font-weight:600;">' + len + '</span> znaków' + (len > 0 ? ' (' + min + '–' + max + ' zalecane)' : ''));
	}
	$('#atela_seo_tax_title').on('input', function(){ 
		updateCount('#atela_seo_tax_title', '#atela_seo_tax_title_count', 50, 60); 
	}).trigger('input');
	
	$('#atela_seo_tax_description').on('input', function(){ 
		updateCount('#atela_seo_tax_description', '#atela_seo_tax_desc_count', 150, 160); 
	}).trigger('input');
});
