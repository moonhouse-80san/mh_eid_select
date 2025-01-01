$(function() {
	$('.filter-btn').click(function() {
			var select = $(this).data('select');
			$('.content-item').hide();
			$('.content-item[data-select="' + select + '"]').show();
			$('.filter-btn').removeClass('active');
			$(this).addClass('active');
	}).first().click();
});