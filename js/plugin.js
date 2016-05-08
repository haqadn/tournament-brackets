(function($){
	$(document).ready(function(){
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

	});

	$(window).load(function(){

		if( typeof $.fn.modal != 'undefined' )
		$('.page-load-modal').modal();

	});
})(jQuery);