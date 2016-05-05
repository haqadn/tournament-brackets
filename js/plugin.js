(function($){
	$(document).ready(function(){
		if( typeof $.fn.datetimepicker != 'undefined' )
		$('.time-date-picker').datetimepicker({
			controlType: 'select',
			dateFormat: 'yy-mm-dd',
			timeFormat: 'hh:mm tt'
		});

		if( typeof $.fn.countdown != 'undefined' )
		$('.countdown').each(function(){
			$(this).countdown({
				end_time: $(this).data('end-time')
			});
		});
	});

	$(window).load(function(){

		if( typeof $.fn.modal != 'undefined' )
		$('.page-load-modal').modal();

	});
})(jQuery);