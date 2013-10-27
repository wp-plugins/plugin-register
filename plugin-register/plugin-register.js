jQuery(function(){
	jQuery('#pluginregister .rangereport').hide();
	jQuery('#range24hours.rangereport').show();
	jQuery('a.rangebutton').click(function(){
		var a = jQuery(this);
		jQuery('div.rangereport').hide();
		jQuery(a.attr('href')).show();
		return false;
	});
});