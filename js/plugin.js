(function($){
	$(document).ready(function(){
		$("#tournament-players").tagit({
			fieldName: "tournament_settings[players][]",
			autocomplete: ({
				source: function (request, response) {
					$.ajax({
						url: ajaxurl,
						data: { action: "autocomplete-username", query: request.term },
						dataType: 'json',
						type: 'GET',
						success: function (data) {
							response($.map(data, function (item) {
								return {
									label: item,
									value: item
								}
							}));
						},
						error: function (request, status, error) {
							alert(error);
						}
					})
				},
				minLength: 2
			})
		});

		$('.time-date-picker').datetimepicker({
			controlType: 'select',
			dateFormat: 'yy-mm-dd',
			timeFormat: 'hh:mm tt'
		});
	});
})(jQuery);