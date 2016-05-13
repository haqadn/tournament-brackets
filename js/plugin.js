(function($){
	$(document).ready(function(){
		if($('#username').length)
		$('#username').autocomplete({
			source: gt_info.ajaxurl+'?action=autocomplete-username',
			minLength: 2
		});

		if( typeof $.fn.datetimepicker != 'undefined' )
		$('.time-date-picker').datetimepicker({
			controlType: 'select',
			dateFormat: 'yy-mm-dd',
			timeFormat: 'HH:mm:ss z'
		});

		if( typeof $.fn.countdown != 'undefined' )
		$('.countdown').each(function(){
			$(this).countdown({
				end_time: $(this).data('end-time')
			});
		});

		//Highlight a team when hovered
		$(".m_segment").on("mouseover mouseout",function () {
			var $this = $(this);
			var winnderId = $this.attr("data-team-id");
			var $teams = $("[data-team-id="+winnderId+"]");
			$teams.toggleClass('highlight').parent().toggleClass('highlight');
		});
		// highlight each round with its similar reversed round
		function highlightRounds(roundClass){
			roundClass.on('mouseover mouseout',function(){
				roundClass.toggleClass('focus')
			})
		}
		highlightRounds($('.r_64'));
		highlightRounds($('.r_32'));
		highlightRounds($('.r_16'));
		highlightRounds($('.r_8'));
		highlightRounds($('.r_4'));
		highlightRounds($('.r_2'));

		$('#tournament-registration-form').submit(function(e){
			var el = $(this);
			e.preventDefault();

			$.get(
				el.attr('action'),
				el.serialize(),
				function(data){
					var result = el.find('.result');

					console.log( data.message );
					result.text(data.message);

					result.fadeIn();
					if( data.success ){
						el.get(0).reset();
					}

					setTimeout(function(){
						result.fadeOut();
						result.text('');
					}, 4000);
				},
				'json'
			);
		});
	});

	$(window).load(function(){

		if( typeof $.fn.modal != 'undefined' )
		$('.page-load-modal').modal();

	});
})(jQuery);