(function($){
	$(document).ready(function(){
		$('.time-date-picker').datetimepicker({
			controlType: 'select',
			dateFormat: 'yy-mm-dd',
			timeFormat: 'hh:mm tt'
		});
	});
})(jQuery);